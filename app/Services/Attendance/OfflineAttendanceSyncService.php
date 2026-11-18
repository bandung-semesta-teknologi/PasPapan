<?php

namespace App\Services\Attendance;

use App\Contracts\AttendanceServiceInterface;
use App\Models\Attendance;
use App\Models\AttendanceOfflineSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;

class OfflineAttendanceSyncService
{
    public function __construct(
        private readonly DeviceAttendanceService $deviceAttendanceService,
        private readonly AttendanceServiceInterface $attendanceService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function sync(User $user, array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $submission = AttendanceOfflineSubmission::query()->firstOrNew([
                'user_id' => $user->id,
                'client_uuid' => (string) $item['client_uuid'],
            ]);

            if ($submission->exists && $submission->status === 'processed') {
                $results[] = $this->result($submission);

                continue;
            }

            try {
                $capturedAt = Carbon::createFromFormat('Y-m-d H:i:s', (string) $item['timestamp']);
            } catch (\Throwable) {
                $submission->update(['status' => 'failed', 'error_message' => 'Invalid timestamp format.']);
                $results[] = $this->result($submission);

                continue;
            }

            if ($capturedAt->isFuture() || $capturedAt->diffInHours(now()) > 48) {
                $submission->update(['status' => 'failed', 'error_message' => 'Timestamp is invalid or expired.']);
                $results[] = $this->result($submission);

                continue;
            }

            $submission->fill([
                'barcode_data' => (string) $item['barcode_data'],
                'latitude' => (float) $item['latitude'],
                'longitude' => (float) $item['longitude'],
                'accuracy' => isset($item['accuracy']) ? (float) $item['accuracy'] : null,
                'gps_variance' => isset($item['gps_variance']) ? (float) $item['gps_variance'] : null,
                'captured_at' => $capturedAt,
                'synced_at' => now(),
                'payload' => [
                    'mock_location_detected' => (bool) ($item['mock_location_detected'] ?? false),
                    'cached_location' => (bool) ($item['cached_location'] ?? false),
                    'device_changed' => (bool) ($item['device_changed'] ?? false),
                    'device_info_missing' => (bool) ($item['device_info_missing'] ?? false),
                    'device_id' => $item['device_id'] ?? null,
                    'device_info' => $item['device_info'] ?? null,
                    'platform' => $item['platform'] ?? null,
                    'face_confidence' => $item['face_confidence'] ?? null,
                    'face_verification_failed' => (bool) ($item['face_verification_failed'] ?? false),
                    'face_verification_skipped' => (bool) ($item['face_verification_skipped'] ?? false),
                    'qr_token_retries' => (int) ($item['qr_token_retries'] ?? 0),
                    'photo_present' => filled($item['photo_base64'] ?? null),
                ],
            ])->save();

            $result = $this->deviceAttendanceService->saveBarcodeScan(
                userId: $user->id,
                barcodePayload: (string) $item['barcode_data'],
                latitude: (float) $item['latitude'],
                longitude: (float) $item['longitude'],
                timestamp: (string) $item['timestamp'],
                gpsAccuracy: isset($item['accuracy']) ? (float) $item['accuracy'] : null,
                gpsVariance: isset($item['gps_variance']) ? (float) $item['gps_variance'] : null,
                mockLocationDetected: (bool) ($item['mock_location_detected'] ?? false),
                offlineSubmitted: true,
                cachedLocation: (bool) ($item['cached_location'] ?? false),
                deviceChanged: (bool) ($item['device_changed'] ?? false),
                deviceInfoMissing: (bool) ($item['device_info_missing'] ?? false),
                deviceId: isset($item['device_id']) ? (string) $item['device_id'] : null,
                deviceInfo: isset($item['device_info']) && is_array($item['device_info']) ? $item['device_info'] : null,
                platform: isset($item['platform']) ? (string) $item['platform'] : null,
                faceConfidence: isset($item['face_confidence']) ? (float) $item['face_confidence'] : null,
                faceVerificationFailed: (bool) ($item['face_verification_failed'] ?? false),
                faceVerificationSkipped: (bool) ($item['face_verification_skipped'] ?? false),
                qrTokenRetries: (int) ($item['qr_token_retries'] ?? 0),
                activityDetails: $item['activity_details'] ?? null,
            );

            if (! ($result['ok'] ?? false)) {
                $submission->update([
                    'status' => 'failed',
                    'error_message' => (string) ($result['message'] ?? 'Failed to process offline attendance.'),
                ]);

                $results[] = $this->result($submission);

                continue;
            }

            $attendance = $result['attendance'];
            $photoPath = $this->storePhoto($user, $item['photo_base64'] ?? null);

            if ($photoPath !== null) {
                $this->attachPhotoPath($attendance, $photoPath, (string) $result['action']);
            }

            $submission->update([
                'status' => 'processed',
                'action' => (string) $result['action'],
                'processed_attendance_id' => $attendance->id,
                'photo_path' => $photoPath,
                'risk_score' => (int) ($attendance->risk_score ?? 0),
                'risk_level' => (string) ($attendance->risk_level ?? 'low'),
                'error_message' => null,
            ]);

            $results[] = $this->result($submission);
        }

        return $results;
    }

    private function storePhoto(User $user, mixed $photoBase64): ?string
    {
        if (! is_string($photoBase64) || trim($photoBase64) === '') {
            return null;
        }

        return $this->attendanceService->storeAttendancePhoto(
            $photoBase64,
            $user->id.'_offline_'.time().'.jpg',
        );
    }

    private function attachPhotoPath(Attendance $attendance, string $photoPath, string $action): void
    {
        $attachments = [];

        if ($attendance->attachment) {
            $decoded = json_decode($attendance->attachment, true);
            $attachments = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : ['in' => $attendance->attachment];
        }

        $attachments[$action === 'check_out' ? 'out' : 'in'] = $photoPath;

        $attendance->forceFill(['attachment' => json_encode($attachments)])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function result(AttendanceOfflineSubmission $submission): array
    {
        return [
            'client_uuid' => $submission->client_uuid,
            'status' => $submission->status,
            'action' => $submission->action,
            'attendance_id' => $submission->processed_attendance_id,
            'risk_score' => $submission->risk_score,
            'risk_level' => $submission->risk_level,
            'error_message' => $submission->error_message,
        ];
    }
}
