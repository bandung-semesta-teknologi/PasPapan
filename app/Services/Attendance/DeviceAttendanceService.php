<?php

namespace App\Services\Attendance;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Support\AttendanceRiskScorer;
use App\Support\DynamicBarcodeTokenService;
use Ballen\Distical\Calculator as DistanceCalculator;
use Ballen\Distical\Entities\LatLong;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeviceAttendanceService
{
    public function __construct(
        protected DynamicBarcodeTokenService $dynamicBarcodeTokenService,
        protected AttendanceRiskScorer $attendanceRiskScorer,
    ) {}

    public function saveBarcodeScan(
        int|string $userId,
        string $barcodePayload,
        float $latitude,
        float $longitude,
        ?string $timestamp = null,
        ?float $gpsAccuracy = null,
        ?float $gpsVariance = null,
        bool $mockLocationDetected = false,
        bool $offlineSubmitted = false,
        bool $cachedLocation = false,
        bool $deviceChanged = false,
        bool $deviceInfoMissing = false,
        ?string $deviceId = null,
        ?array $deviceInfo = null,
        ?string $platform = null,
        ?float $faceConfidence = null,
        bool $faceVerificationFailed = false,
        bool $faceVerificationSkipped = false,
        int $qrTokenRetries = 0,
        ?string $activityDetails = null,
    ): array {
        $scanContext = $this->dynamicBarcodeTokenService->resolveScannedBarcodeWithSource($barcodePayload);
        $barcode = $scanContext['barcode'];
        $scanSource = $scanContext['source'] ?? 'static';

        if (! $barcode) {
            return [
                'ok' => false,
                'message' => 'Invalid barcode',
                'status' => 422,
            ];
        }

        $distance = $this->calculateDistance(
            new LatLong($latitude, $longitude),
            new LatLong((float) $barcode->latLng['lat'], (float) $barcode->latLng['lng'])
        );

        if ($distance > $barcode->radius) {
            return [
                'ok' => false,
                'message' => "Location out of range: {$distance}m. Max: {$barcode->radius}m",
                'status' => 422,
            ];
        }

        $attendanceTime = $timestamp
            ? Carbon::createFromFormat('Y-m-d H:i:s', $timestamp)
            : now();

        return DB::transaction(function () use (
            $userId,
            $barcode,
            $scanSource,
            $latitude,
            $longitude,
            $attendanceTime,
            $gpsAccuracy,
            $gpsVariance,
            $distance,
            $mockLocationDetected,
            $offlineSubmitted,
            $cachedLocation,
            $deviceChanged,
            $deviceInfoMissing,
            $deviceId,
            $deviceInfo,
            $platform,
            $faceConfidence,
            $faceVerificationFailed,
            $faceVerificationSkipped,
            $qrTokenRetries,
            $barcodePayload,
            $activityDetails,
        ): array {
            $attendance = Attendance::query()
                ->where('user_id', $userId)
                ->where('date', $attendanceTime->toDateString())
                ->lockForUpdate()
                ->first();

            $attendance ??= new Attendance([
                'user_id' => $userId,
                'date' => $attendanceTime->toDateString(),
            ]);

            if (
                $attendance->exists &&
                in_array($attendance->status, ['sick', 'excused', 'permission', 'leave'], true) &&
                $attendance->approval_status === Attendance::STATUS_APPROVED
            ) {
                return [
                    'ok' => false,
                    'message' => 'Attendance is blocked because the user is on approved leave.',
                    'status' => 422,
                ];
            }

            if (is_null($attendance->time_in)) {
                $attendance->fill([
                    'barcode_id' => $barcode->id,
                    'time_in' => $attendanceTime,
                    'latitude_in' => $latitude,
                    'longitude_in' => $longitude,
                    'accuracy_in' => $gpsAccuracy,
                    'gps_variance_in' => $gpsVariance,
                    'status' => $attendance->status === 'absent' ? 'present' : ($attendance->status ?: 'present'),
                ]);
                $action = 'check_in';
            } elseif (is_null($attendance->time_out)) {
                if ((int) $attendance->barcode_id !== (int) $barcode->id) {
                    return [
                        'ok' => false,
                        'message' => __('Please scan the same checkpoint used for check in.'),
                        'status' => 422,
                    ];
                }

                $attendance->fill([
                    'barcode_id' => $attendance->barcode_id,
                    'time_out' => $attendanceTime,
                    'activity_details' => filled($activityDetails) ? trim($activityDetails) : null,
                    'latitude_out' => $latitude,
                    'longitude_out' => $longitude,
                    'accuracy_out' => $gpsAccuracy,
                    'gps_variance_out' => $gpsVariance,
                ]);
                $action = 'check_out';
            } else {
                return [
                    'ok' => false,
                    'message' => 'Attendance for today is already complete.',
                    'status' => 409,
                ];
            }

            $risk = $this->attendanceRiskScorer->score(
                attendance: $attendance,
                barcode: $barcode,
                shift: $attendance->shift,
                event: $action,
                context: [
                    'distance' => $distance,
                    'gps_accuracy' => $gpsAccuracy,
                    'gps_variance' => $gpsVariance,
                    'mock_location_detected' => $mockLocationDetected,
                    'offline_submitted' => $offlineSubmitted,
                    'cached_location' => $cachedLocation,
                    'device_changed' => $deviceChanged,
                    'device_info_missing' => $deviceInfoMissing,
                    'device_id' => $deviceId,
                    'device_info' => $deviceInfo,
                    'platform' => $platform,
                    'face_confidence' => $faceConfidence,
                    'face_verification_failed' => $faceVerificationFailed,
                    'face_verification_skipped' => $faceVerificationSkipped,
                    'qr_token_retries' => $qrTokenRetries,
                    'source' => $scanSource,
                    'barcode_source' => $scanSource,
                    'captured_at' => $attendanceTime,
                    'received_at' => now(),
                ],
            );

            if ($attendance->exists) {
                $risk = $this->attendanceRiskScorer->merge(
                    $attendance->risk_factors,
                    (int) ($attendance->risk_score ?? 0),
                    $risk,
                );
            }

            $attendance->fill([
                'is_suspicious' => (bool) $attendance->is_suspicious || $risk['score'] >= 25,
                'risk_score' => $risk['score'],
                'risk_level' => $risk['level'],
                'risk_factors' => $risk['factors'],
                'risk_evaluated_at' => $risk['evaluated_at'],
            ]);

            $attendance->save();

            if ($scanSource === 'dynamic') {
                $this->dynamicBarcodeTokenService->consumeScannedToken($barcode, $barcodePayload);
            }

            Attendance::clearUserAttendanceCache($attendance->user, Carbon::parse($attendance->date));
            ActivityLog::record(
                $scanSource === 'dynamic'
                    ? ($action === 'check_in' ? 'Dynamic Check In' : 'Dynamic Check Out')
                    : ($action === 'check_in' ? 'Check In' : 'Check Out'),
                ($action === 'check_in' ? 'User checked in via ' : 'User checked out via ')
                    .($scanSource === 'dynamic' ? 'dynamic barcode: ' : 'barcode: ')
                    .$barcode->name
            );

            return [
                'ok' => true,
                'attendance' => $attendance,
                'action' => $action,
            ];
        });
    }

    public function uploadPhoto(int|string $userId, UploadedFile $photo, ?float $latitude = null, ?float $longitude = null): array
    {
        $path = $photo->store(
            'attendance_photos/'.now()->format('Y/m/d'),
            ['disk' => 'local']
        );

        $attendance = Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('date', now()->toDateString())
            ->first();

        $attachments = $this->decodeAttachmentPayload($attendance?->attachment);
        $slot = $this->resolvePhotoSlot($attachments);

        if ($attendance) {
            $attendance->update([
                'attachment' => json_encode(array_merge($attachments, [$slot => $path])),
                'latitude_in' => $slot === 'in' ? ($latitude ?? $attendance->latitude_in) : $attendance->latitude_in,
                'longitude_in' => $slot === 'in' ? ($longitude ?? $attendance->longitude_in) : $attendance->longitude_in,
                'latitude_out' => $slot === 'out' ? ($latitude ?? $attendance->latitude_out) : $attendance->latitude_out,
                'longitude_out' => $slot === 'out' ? ($longitude ?? $attendance->longitude_out) : $attendance->longitude_out,
            ]);
        } else {
            $attachments[$slot] = $path;
            $attendance = Attendance::create([
                'user_id' => $userId,
                'date' => now()->toDateString(),
                'attachment' => json_encode($attachments),
                'latitude_in' => $latitude,
                'longitude_in' => $longitude,
                'status' => 'present',
            ]);
        }

        Attendance::clearUserAttendanceCache($attendance->user, Carbon::parse($attendance->date));

        return [
            'attendance' => $attendance,
            'slot' => $slot,
        ];
    }

    protected function calculateDistance(LatLong $a, LatLong $b): int
    {
        return (int) floor((new DistanceCalculator($a, $b))->get()->asKilometres() * 1000);
    }

    protected function decodeAttachmentPayload(?string $attachment): array
    {
        if (! $attachment) {
            return [];
        }

        $decoded = json_decode($attachment, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['in' => $attachment];
    }

    protected function resolvePhotoSlot(array $attachments): string
    {
        if (! isset($attachments['in'])) {
            return 'in';
        }

        if (! isset($attachments['out'])) {
            return 'out';
        }

        return 'out';
    }
}
