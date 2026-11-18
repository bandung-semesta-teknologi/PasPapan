<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeviceBarcodeScanRequest;
use App\Services\Attendance\DeviceAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BarcodeScanController extends Controller
{
    public function __construct(
        protected DeviceAttendanceService $deviceAttendanceService,
    ) {}

    public function __invoke(DeviceBarcodeScanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_int($userId) || is_string($userId), 401);

        try {
            $result = $this->deviceAttendanceService->saveBarcodeScan(
                userId: $userId,
                barcodePayload: $validated['barcode_data'],
                latitude: (float) $validated['latitude'],
                longitude: (float) $validated['longitude'],
                timestamp: $validated['timestamp'] ?? null,
                gpsAccuracy: isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
                gpsVariance: isset($validated['gps_variance']) ? (float) $validated['gps_variance'] : null,
                mockLocationDetected: (bool) ($validated['mock_location_detected'] ?? false),
                offlineSubmitted: (bool) ($validated['offline_submitted'] ?? false),
                cachedLocation: (bool) ($validated['cached_location'] ?? false),
                deviceChanged: (bool) ($validated['device_changed'] ?? false),
                deviceInfoMissing: (bool) ($validated['device_info_missing'] ?? false),
                deviceId: isset($validated['device_id']) ? (string) $validated['device_id'] : null,
                deviceInfo: $validated['device_info'] ?? null,
                platform: isset($validated['platform']) ? (string) $validated['platform'] : null,
                faceConfidence: isset($validated['face_confidence']) ? (float) $validated['face_confidence'] : null,
                faceVerificationFailed: (bool) ($validated['face_verification_failed'] ?? false),
                faceVerificationSkipped: (bool) ($validated['face_verification_skipped'] ?? false),
                qrTokenRetries: (int) ($validated['qr_token_retries'] ?? 0),
                activityDetails: $validated['activity_details'] ?? null,
            );

            if (! $result['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['status']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Barcode data saved successfully',
                'attendance_id' => $result['attendance']->id,
                'action' => $result['action'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to save device barcode data.', [
                'user_id' => $userId,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save barcode data.',
            ], 422);
        }
    }
}
