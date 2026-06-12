<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Foundation\Http\FormRequest;

class DeviceActivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:255'],
            'mac_address' => ['required', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:255'],
        ];
    }
}
