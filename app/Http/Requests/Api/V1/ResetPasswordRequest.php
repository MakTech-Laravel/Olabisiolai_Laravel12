<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPasswordResetContact;
use App\Rules\NigerianPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResetPasswordRequest extends FormRequest
{
    use NormalizesPasswordResetContact;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->preparePasswordResetContactValidation();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->passwordResetContactRules(),
            'phone' => ['nullable', 'string', new NigerianPhoneNumber(), 'required_without:email'],
            'token' => ['required', 'string', 'size:64'],
            'role' => ['nullable', Rule::in(['user', 'vendor', 'admin'])],
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/\d/',
                'regex:/[^a-zA-Z0-9]/',
            ],
        ];
    }
}
