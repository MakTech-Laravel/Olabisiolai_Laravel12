<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPasswordResetContact;
use App\Rules\NigerianPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyForgotPasswordTokenRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->passwordResetContactRules(),
            'phone' => ['nullable', 'string', new NigerianPhoneNumber(), 'required_without:email'],
            'code' => ['nullable', 'digits:6'],
            'token' => ['required', 'string', 'size:64'],
            'role' => ['nullable', Rule::in(['user', 'vendor', 'admin'])],
        ];
    }
}
