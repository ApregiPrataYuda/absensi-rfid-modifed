<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Foundation\Http\FormRequest;

class DeviceAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uid' => ['required', 'string', 'max:255'],
            'scanned_at' => ['required', 'date'],
        ];
    }
}
