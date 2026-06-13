<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyNewDeviceOtpRequest extends FormRequest
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
            'device_verification_token' => ['required', 'string', 'min:32', 'max:128'],
            'code' => ['required', 'digits:6'],
            'role' => ['nullable', Rule::in(['user', 'vendor'])],
        ];
    }
}
