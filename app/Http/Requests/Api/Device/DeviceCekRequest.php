<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Foundation\Http\FormRequest;

class DeviceCekRequest extends FormRequest
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
            'firmware_version' => ['required', 'string', 'max:255'],
        ];
    }
}
