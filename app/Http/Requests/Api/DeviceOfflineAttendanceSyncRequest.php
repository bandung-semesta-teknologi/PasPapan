<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeviceOfflineAttendanceSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.client_uuid' => ['required', 'string', 'max:120'],
            'items.*.activity_details' => ['nullable', 'string', 'max:5000'],
            'items.*.barcode_data' => ['required', 'string', 'max:2048'],
            'items.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'items.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'items.*.timestamp' => ['required', 'date_format:Y-m-d H:i:s'],
            'items.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'items.*.gps_variance' => ['nullable', 'numeric', 'min:0'],
            'items.*.mock_location_detected' => ['nullable', 'boolean'],
            'items.*.cached_location' => ['nullable', 'boolean'],
            'items.*.device_changed' => ['nullable', 'boolean'],
            'items.*.device_info_missing' => ['nullable', 'boolean'],
            'items.*.device_id' => ['nullable', 'string', 'max:160'],
            'items.*.device_info' => ['nullable', 'array'],
            'items.*.platform' => ['nullable', 'string', 'max:40'],
            'items.*.face_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'items.*.face_verification_failed' => ['nullable', 'boolean'],
            'items.*.face_verification_skipped' => ['nullable', 'boolean'],
            'items.*.qr_token_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'items.*.photo_base64' => ['nullable', 'string', 'max:7000000'],
        ];
    }
}
