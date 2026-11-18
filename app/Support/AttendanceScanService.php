<?php

namespace App\Support;

use App\Contracts\AttendanceServiceInterface;
use App\Helpers\Editions;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Barcode;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\AttendanceRiskScoringService;
use Ballen\Distical\Calculator as DistanceCalculator;
use Ballen\Distical\Entities\LatLong;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class AttendanceScanService
{
    public function __construct(
        private readonly DynamicBarcodeTokenService $dynamicBarcodeTokenService,
        private readonly AttendanceRiskScoringService $attendanceRiskScoring,
        private readonly AttendanceRiskScorer $attendanceRiskScorer,
        private readonly AttendanceServiceInterface $attendanceService,
    ) {}

    /**
     * @param  array<int, float>|null  $coords
     */
    public function validateScan(User $user, ?int $shiftId, ?array $coords, string $barcodePayload): true|string
    {
        if ($coords === null) {
            return __('Invalid location');
        }

        if ($shiftId === null) {
            return __('Invalid shift');
        }

        $attendanceForDay = $this->attendanceForDay($user);

        if ($this->blocksAttendance($attendanceForDay)) {
            return __('Anda tidak dapat melakukan absensi karena sedang Cuti/Izin/Sakit.');
        }

        $scanContext = $this->dynamicBarcodeTokenService->resolveScannedBarcodeWithSource($barcodePayload);
        $barcode = $scanContext['barcode'] ?? null;

        if (! $barcode) {
            return __('Invalid barcode');
        }

        if ($attendanceForDay?->time_in && $attendanceForDay->time_out) {
            return __('Attendance for today is already complete.');
        }

        if (
            $attendanceForDay?->time_in &&
            ! $attendanceForDay->time_out &&
            $attendanceForDay->barcode_id &&
            (int) $attendanceForDay->barcode_id !== (int) $barcode->id
        ) {
            return __('Please scan the same checkpoint used for check in.');
        }

        $distance = $this->calculateDistance(
            new LatLong((float) $coords[0], (float) $coords[1]),
            new LatLong((float) $barcode->latLng['lat'], (float) $barcode->latLng['lng'])
        );

        if ($distance > $barcode->radius) {
            return __('Location out of range').": {$distance}m. Max: {$barcode->radius}m";
        }

        return true;
    }

    /**
     * @param  array<int, float>|null  $coords
     * @return array<string, mixed>
     */
    public function performScan(
        User $user,
        ?int $shiftId,
        ?array $coords,
        string $barcodePayload,
        ?string $photo,
        ?string $note,
        int $gracePeriod,
        ?float $gpsAccuracy,
        ?float $gpsVariance,
        array $riskContext = [],
        ?string $activityDetails = null,
    ): array {
        $validation = $this->validateScan($user, $shiftId, $coords, $barcodePayload);

        if ($validation !== true) {
            return [
                'ok' => false,
                'message' => $validation,
            ];
        }

        $activityValidation = Validator::make(
            ['activity_details' => $activityDetails],
            ['activity_details' => ['nullable', 'string', 'max:5000']],
        );

        if ($activityValidation->fails()) {
            return ['ok' => false, 'message' => $activityValidation->errors()->first('activity_details')];
        }

        if ((int) Setting::getValue('feature.require_photo', 1) === 1 && empty($photo)) {
            return [
                'ok' => false,
                'message' => 'Photo required',
            ];
        }

        $scanContext = $this->dynamicBarcodeTokenService->resolveScannedBarcodeWithSource($barcodePayload);
        $barcode = $scanContext['barcode'];
        $scanSource = $scanContext['source'] ?? 'static';
        $attendanceForDay = $this->attendanceForDay($user);
        $distance = $this->calculateDistance(
            new LatLong((float) $coords[0], (float) $coords[1]),
            new LatLong((float) $barcode->latLng['lat'], (float) $barcode->latLng['lng'])
        );

        if (! $attendanceForDay || $attendanceForDay->time_in === null) {
            $attendance = $this->createAttendance(
                user: $user,
                barcode: $barcode,
                shiftId: (int) $shiftId,
                coords: $coords,
                photo: $photo,
                gracePeriod: $gracePeriod,
                gpsAccuracy: $gpsAccuracy,
                gpsVariance: $gpsVariance,
                distance: $distance,
                scanSource: $scanSource,
                riskContext: $riskContext,
            );
            $message = __('Attendance In Successful');
            ActivityLog::record(
                $scanSource === 'dynamic' ? 'Dynamic Check In' : 'Check In',
                'User checked in via '.($scanSource === 'dynamic' ? 'dynamic barcode' : 'barcode').': '.$barcode->name
            );
        } else {
            $attendance = $this->checkoutAttendance(
                attendance: $attendanceForDay,
                barcode: $barcode,
                coords: $coords,
                photo: $photo,
                note: $note,
                activityDetails: $activityDetails,
                gpsAccuracy: $gpsAccuracy,
                gpsVariance: $gpsVariance,
                distance: $distance,
                scanSource: $scanSource,
                riskContext: $riskContext,
            );
            $message = __('Attendance Out Successful');
            ActivityLog::record(
                $scanSource === 'dynamic' ? 'Dynamic Check Out' : 'Check Out',
                'User checked out via '.($scanSource === 'dynamic' ? 'dynamic barcode' : 'barcode').': '.$barcode->name
            );
        }

        if ($scanSource === 'dynamic') {
            $this->dynamicBarcodeTokenService->consumeScannedToken($barcode, $barcodePayload);
        }

        $attendance = $attendance->fresh(['shift']);
        Attendance::clearUserAttendanceCache($user, Carbon::parse($attendance->date));

        return [
            'ok' => true,
            'attendance' => $attendance,
            'message' => $message,
            'scan_source' => $scanSource,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(User $user): array
    {
        $shifts = Shift::query()->get();
        $attendance = $this->attendanceForDay($user);
        $shiftId = $attendance?->shift_id;

        if ($shiftId === null) {
            $shiftId = $this->resolveDefaultShiftId($user, $shifts);
        }

        $attendanceLocked = Editions::attendanceLocked();
        $faceVerificationRequired = ! $attendanceLocked && filter_var(
            Setting::getValue('attendance.require_face_verification', true),
            FILTER_VALIDATE_BOOLEAN
        );

        $requiresFaceEnrollment = ! $attendanceLocked && (
            filter_var(
                Setting::getValue('attendance.require_face_enrollment', false),
                FILTER_VALIDATE_BOOLEAN
            ) || $this->attendanceService->shouldEnforceFaceEnrollment() || $faceVerificationRequired
        );

        return [
            'shifts' => $shifts,
            'attendance' => $attendance,
            'shift_id' => $shiftId,
            'grace_period' => (int) Setting::getValue('attendance.grace_period', 0),
            'time_settings' => [
                'format' => Setting::getValue('app.time_format', '24'),
                'show_seconds' => (bool) Setting::getValue('app.show_seconds', false),
            ],
            'user_face_descriptor' => $user->hasFaceRegistered() ? $user->faceDescriptor->descriptor : null,
            'requires_face_verification' => $user->hasFaceRegistered() && $faceVerificationRequired,
            'requires_face_enrollment_redirect' => $requiresFaceEnrollment && ! $user->hasFaceRegistered(),
            'approved_absence' => $this->blocksAttendance($attendance) ? $attendance : null,
        ];
    }

    /**
     * @param  array<int, float>  $coords
     */
    private function createAttendance(
        User $user,
        Barcode $barcode,
        int $shiftId,
        array $coords,
        ?string $photo,
        int $gracePeriod,
        ?float $gpsAccuracy,
        ?float $gpsVariance,
        int $distance,
        string $scanSource,
        array $riskContext,
    ): Attendance {
        $now = Carbon::now();
        $shift = Shift::query()->findOrFail($shiftId);
        $shiftStart = Carbon::parse($shift->start_time)->setDate($now->year, $now->month, $now->day);
        $status = $now->gt($shiftStart->copy()->addMinutes($gracePeriod)) ? 'late' : 'present';
        $attachmentPath = $this->savePhoto($user, $photo);

        return $this->saveAttendanceRequest(
            user: $user,
            barcode: $barcode,
            date: $now->format('Y-m-d'),
            timeIn: $now,
            status: $status,
            attachmentPath: $attachmentPath,
            shift: $shift,
            coords: $coords,
            gpsAccuracy: $gpsAccuracy,
            gpsVariance: $gpsVariance,
            distance: $distance,
            scanSource: $scanSource,
            riskContext: $riskContext,
        );
    }

    /**
     * @param  array<int, float>  $coords
     */
    private function checkoutAttendance(
        Attendance $attendance,
        Barcode $barcode,
        array $coords,
        ?string $photo,
        ?string $note,
        ?string $activityDetails,
        ?float $gpsAccuracy,
        ?float $gpsVariance,
        int $distance,
        string $scanSource,
        array $riskContext,
    ): Attendance {
        $existingAttachment = $attendance->attachment;
        $attachments = [];

        if ($existingAttachment) {
            $decoded = json_decode($existingAttachment, true);
            $attachments = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : ['in' => $existingAttachment];
        }

        if ($photo) {
            $attachments['out'] = $this->savePhoto($attendance->user, $photo);
        }

        $isSuspicious = $attendance->is_suspicious ?? false;
        $suspiciousReasons = $attendance->suspicious_reason ? explode('; ', $attendance->suspicious_reason) : [];

        if ($gpsAccuracy !== null && $gpsAccuracy < 5) {
            $isSuspicious = true;
            $suspiciousReasons[] = 'Checkout accuracy too perfect: '.$gpsAccuracy.'m';
        }

        if ($gpsVariance !== null && $gpsVariance == 0.0) {
            $isSuspicious = true;
            $suspiciousReasons[] = 'Checkout zero GPS variance';
        }

        $risk = $this->attendanceRiskScoring->evaluate(
            attendance: $attendance,
            barcode: $barcode,
            shift: $attendance->shift,
            event: 'check_out',
            context: [
                ...$riskContext,
                'distance' => $distance,
                'gps_accuracy' => $gpsAccuracy,
                'gps_variance' => $gpsVariance,
                'source' => $scanSource,
                'barcode_source' => $scanSource,
            ],
        );
        $risk = $this->attendanceRiskScorer->merge(
            $attendance->risk_factors,
            (int) ($attendance->risk_score ?? 0),
            $risk,
        );

        $attendance->update([
            'time_out' => Carbon::now(),
            'latitude_out' => (float) $coords[0],
            'longitude_out' => (float) $coords[1],
            'accuracy_out' => $gpsAccuracy,
            'gps_variance_out' => $gpsVariance,
            'attachment' => json_encode($attachments),
            'note' => $note ?: $attendance->note,
            'activity_details' => filled($activityDetails) ? trim($activityDetails) : null,
            'is_suspicious' => $isSuspicious || $risk['score'] >= 25,
            'suspicious_reason' => $isSuspicious ? implode('; ', array_unique($suspiciousReasons)) : null,
            'risk_score' => $risk['score'],
            'risk_level' => $risk['level'],
            'risk_factors' => $risk['factors'],
            'risk_evaluated_at' => $risk['evaluated_at'],
        ]);

        return $attendance;
    }

    /**
     * @param  array<int, float>  $coords
     */
    private function saveAttendanceRequest(
        User $user,
        Barcode $barcode,
        string $date,
        Carbon $timeIn,
        string $status,
        ?string $attachmentPath,
        Shift $shift,
        array $coords,
        ?float $gpsAccuracy,
        ?float $gpsVariance,
        int $distance,
        string $scanSource,
        array $riskContext,
    ): Attendance {
        $isSuspicious = false;
        $suspiciousReasons = [];

        if ($gpsAccuracy !== null && $gpsAccuracy < 5) {
            $isSuspicious = true;
            $suspiciousReasons[] = 'Accuracy too perfect: '.$gpsAccuracy.'m';
        }

        if ($gpsVariance !== null && $gpsVariance == 0.0) {
            $isSuspicious = true;
            $suspiciousReasons[] = 'Zero GPS variance (static location)';
        }

        $overrideable = Attendance::query()
            ->where('user_id', $user->id)
            ->where('date', $date)
            ->where(function ($query) {
                $query->whereIn('status', ['rejected', 'absent', 'sick', 'excused'])
                    ->orWhere('approval_status', Attendance::STATUS_REJECTED);
            })
            ->first();

        $payload = [
            'barcode_id' => $barcode->id,
            'time_in' => $timeIn,
            'time_out' => null,
            'shift_id' => $shift->id,
            'latitude_in' => (float) $coords[0],
            'longitude_in' => (float) $coords[1],
            'accuracy_in' => $gpsAccuracy,
            'gps_variance_in' => $gpsVariance,
            'latitude' => (float) $coords[0],
            'longitude' => (float) $coords[1],
            'status' => $status,
            'note' => null,
            'attachment' => $attachmentPath ? json_encode(['in' => $attachmentPath]) : null,
            'rejection_note' => null,
            'approval_status' => Attendance::STATUS_APPROVED,
            'is_suspicious' => $isSuspicious,
            'suspicious_reason' => $isSuspicious ? implode('; ', $suspiciousReasons) : null,
        ];

        $riskProbe = new Attendance(array_merge($payload, [
            'user_id' => $user->id,
            'date' => $date,
        ]));
        $risk = $this->attendanceRiskScoring->evaluate(
            attendance: $riskProbe,
            barcode: $barcode,
            shift: $shift,
            event: 'check_in',
            context: [
                ...$riskContext,
                'distance' => $distance,
                'gps_accuracy' => $gpsAccuracy,
                'gps_variance' => $gpsVariance,
                'source' => $scanSource,
                'barcode_source' => $scanSource,
            ],
        );

        $payload = array_merge($payload, [
            'is_suspicious' => $isSuspicious || $risk['score'] >= 25,
            'risk_score' => $risk['score'],
            'risk_level' => $risk['level'],
            'risk_factors' => $risk['factors'],
            'risk_evaluated_at' => $risk['evaluated_at'],
        ]);

        if ($overrideable) {
            $overrideable->update($payload);

            return $overrideable;
        }

        return Attendance::query()->create(array_merge($payload, [
            'user_id' => $user->id,
            'date' => $date,
        ]));
    }

    private function savePhoto(User $user, ?string $photo): ?string
    {
        if (! $photo) {
            return null;
        }

        $imageName = $user->id.'_'.time().'.jpg';

        return $this->attendanceService->storeAttendancePhoto($photo, $imageName);
    }

    private function calculateDistance(LatLong $a, LatLong $b): int
    {
        return (int) floor((new DistanceCalculator($a, $b))->get()->asKilometres() * 1000);
    }

    private function attendanceForDay(User $user): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();
    }

    private function blocksAttendance(?Attendance $attendance): bool
    {
        return $attendance !== null
            && in_array($attendance->status, ['sick', 'excused', 'permission', 'leave'], true)
            && $attendance->approval_status === Attendance::STATUS_APPROVED;
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     */
    private function resolveDefaultShiftId(User $user, Collection $shifts): ?int
    {
        $schedule = Schedule::query()
            ->where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->first();

        if ($schedule?->shift_id) {
            return $schedule->shift_id;
        }

        $shiftTimes = $shifts->pluck('start_time')->all();

        if ($shiftTimes === []) {
            return null;
        }

        $closest = ExtendedCarbon::now()->closestFromDateArray($shiftTimes);

        if (! $closest) {
            return null;
        }

        return $shifts
            ->first(fn (Shift $shift) => $shift->start_time === $closest->format('H:i:s'))
            ?->id;
    }
}
