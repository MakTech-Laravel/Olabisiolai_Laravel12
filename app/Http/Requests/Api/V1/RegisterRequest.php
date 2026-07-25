<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\NigerianPhoneNumber;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email')) && trim($this->input('email')) !== '') {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }

        $phone = $this->input('phone');
        if (is_string($phone) && trim($phone) !== '') {
            $this->merge(['phone' => PhoneNormalizer::normalize($phone) ?? trim($phone)]);
        }

        // Legacy clients may still send verification_channel; both contacts are now required.
        if (! $this->filled('verification_channel')) {
            $this->merge(['verification_channel' => 'both']);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'verification_channel' => ['sometimes', Rule::in(['email', 'phone', 'both'])],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'phone' => [
                'required',
                'string',
                new NigerianPhoneNumber(),
                'unique:users,phone',
            ],
            'role' => ['required', Rule::in(['user', 'vendor'])],
            'password' => ['required', 'confirmed', 'min:8'],
            'wants_marketing_emails' => ['nullable', 'boolean'],
            'ref' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'phone.required' => 'Please enter your phone number.',
        ];
    }
}
