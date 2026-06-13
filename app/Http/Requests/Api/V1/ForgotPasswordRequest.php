<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\NormalizesPasswordResetContact;
use App\Rules\NigerianPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForgotPasswordRequest extends FormRequest
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
            'role' => ['nullable', Rule::in(['user', 'vendor', 'admin'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => 'Please enter your email or phone number.',
            'phone.required_without' => 'Please enter your email or phone number.',
        ];
    }
}
