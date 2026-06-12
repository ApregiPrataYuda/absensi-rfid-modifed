<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminStoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/\S/'],
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('devices', 'serial_number'),
            ],
        ];
    }
}
