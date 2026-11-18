<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeviceBarcodeScanRequest extends FormRequest
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
            'activity_details' => ['nullable', 'string', 'max:5000'],
            'barcode_data' => ['required', 'string', 'max:2048'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timestamp' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'gps_variance' => ['nullable', 'numeric', 'min:0'],
            'mock_location_detected' => ['nullable', 'boolean'],
            'offline_submitted' => ['nullable', 'boolean'],
            'cached_location' => ['nullable', 'boolean'],
            'device_changed' => ['nullable', 'boolean'],
            'device_info_missing' => ['nullable', 'boolean'],
            'device_id' => ['nullable', 'string', 'max:160'],
            'device_info' => ['nullable', 'array'],
            'platform' => ['nullable', 'string', 'max:40'],
            'face_confidence' => ['nullable', 'numeric', 'between:0,1'],
            'face_verification_failed' => ['nullable', 'boolean'],
            'face_verification_skipped' => ['nullable', 'boolean'],
            'qr_token_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }
}
