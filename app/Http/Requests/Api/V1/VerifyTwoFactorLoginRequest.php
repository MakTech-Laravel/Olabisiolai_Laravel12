<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTwoFactorLoginRequest extends FormRequest
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
            'two_factor_token' => ['required', 'string', 'min:32', 'max:128'],
            'code' => ['required', 'string', 'max:32'],
            'role' => ['sometimes', 'nullable', 'string', 'in:user,vendor'],
        ];
    }
}
