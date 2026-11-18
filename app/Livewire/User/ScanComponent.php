<?php

namespace App\Livewire\User;

use App\Models\Attendance;
use App\Support\AttendanceScanService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ScanComponent extends Component
{
    protected AttendanceScanService $attendanceScan;

    public ?Attendance $attendance = null;

    public $shift_id = null;

    public $shifts = null;

    public ?array $currentLiveCoords = null;

    public string $successMsg = '';

    public bool $isAbsence = false;

    // Settings Cache
    public $gracePeriod = 0;

    public $photo = null;

    public $timeSettings = [];

    // Face Recognition
    public ?array $userFaceDescriptor = null;

    public ?Attendance $approvedAbsence = null;

    public bool $requiresFaceVerification = false;

    // GPS Accuracy for Fake GPS Detection
    public ?float $gpsAccuracy = null;

    public ?float $gpsVariance = null;

    public bool $cachedLocation = false;

    public bool $mockLocationDetected = false;

    public bool $deviceChanged = false;

    public bool $deviceInfoMissing = false;

    public ?string $deviceId = null;

    public ?string $platform = null;

    public ?float $faceConfidence = null;

    public bool $faceVerificationFailed = false;

    public bool $faceVerificationSkipped = false;

    public function boot(AttendanceScanService $attendanceScan): void
    {
        $this->attendanceScan = $attendanceScan;
    }

    public function validateBarcode(string $barcode, ?float $lat = null, ?float $lng = null)
    {
        if ($lat !== null && $lng !== null) {
            $this->currentLiveCoords = [$lat, $lng];
        }

        return $this->attendanceScan->validateScan(
            Auth::user(),
            $this->shift_id,
            $this->currentLiveCoords,
            $barcode,
        );
    }

    public function scan(string $barcode, ?float $lat = null, ?float $lng = null, ?string $photo = null, ?string $note = null, ?string $activityDetails = null)
    {
        $this->photo = $photo;

        if ($lat !== null && $lng !== null) {
            $this->currentLiveCoords = [$lat, $lng];
        }

        $result = $this->attendanceScan->performScan(
            user: Auth::user(),
            shiftId: $this->shift_id,
            coords: $this->currentLiveCoords,
            barcodePayload: $barcode,
            photo: $this->photo,
            note: $note,
            activityDetails: $activityDetails,
            gracePeriod: (int) $this->gracePeriod,
            gpsAccuracy: $this->gpsAccuracy,
            gpsVariance: $this->gpsVariance,
            riskContext: [
                'cached_location' => $this->cachedLocation,
                'mock_location_detected' => $this->mockLocationDetected,
                'device_changed' => $this->deviceChanged,
                'device_info_missing' => $this->deviceInfoMissing,
                'device_id' => $this->deviceId,
                'platform' => $this->platform,
                'face_confidence' => $this->faceConfidence,
                'face_verification_failed' => $this->faceVerificationFailed,
                'face_verification_skipped' => $this->faceVerificationSkipped,
                'received_at' => now(),
            ],
        );

        if (! ($result['ok'] ?? false)) {
            return $result['message'] ?? __('Unable to process attendance.');
        }

        $attendance = $result['attendance'];
        $this->successMsg = (string) $result['message'];
        $this->setAttendance($attendance);
        $this->dispatch('attendance-recorded');
        session()->flash('success', $this->successMsg);

        return true;
    }

    protected function setAttendance(Attendance $attendance)
    {
        $this->attendance = $attendance;
        $this->shift_id = $attendance->shift_id;
        $this->isAbsence = in_array($attendance->status, ['sick', 'excused']) && $attendance->approval_status === Attendance::STATUS_APPROVED;
    }

    public function getAttendance()
    {
        if (is_null($this->attendance)) {
            return null;
        }

        return [
            'time_in' => $this->attendance?->time_in,
            'time_out' => $this->attendance?->time_out,
            'latitude_in' => $this->attendance?->latitude_in,
            'longitude_in' => $this->attendance?->longitude_in,
            'latitude_out' => $this->attendance?->latitude_out,
            'longitude_out' => $this->attendance?->longitude_out,
            'shift_end_time' => $this->attendance?->shift?->end_time,
        ];
    }

    public function mount()
    {
        $user = Auth::user();
        $state = $this->attendanceScan->bootstrap($user);

        $this->shifts = $state['shifts'];
        $this->shift_id = $state['shift_id'];
        $this->gracePeriod = $state['grace_period'];
        $this->timeSettings = $state['time_settings'];
        $this->userFaceDescriptor = $state['user_face_descriptor'];
        $this->requiresFaceVerification = $state['requires_face_verification'];
        $this->approvedAbsence = $state['approved_absence'];

        if ($state['attendance']) {
            $this->setAttendance($state['attendance']);
        }

        if ($state['requires_face_enrollment_redirect']) {
            return redirect()->route('face.enrollment');
        }
    }

    public function render()
    {
        return view('livewire.user.scan');
    }
}
