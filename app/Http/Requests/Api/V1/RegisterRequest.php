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
        $channel = $this->input('verification_channel');

        if (is_string($this->input('email')) && trim($this->input('email')) !== '') {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        } else {
            $this->merge(['email' => null]);
        }

        $phone = $this->input('phone');
        if (is_string($phone) && trim($phone) !== '') {
            $this->merge(['phone' => PhoneNormalizer::normalize($phone) ?? trim($phone)]);
        } elseif ($channel === 'phone') {
            $this->merge(['phone' => null]);
        } else {
            $this->merge(['phone' => null]);
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
            'verification_channel' => ['required', Rule::in(['email', 'phone'])],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email',
                Rule::requiredIf(fn(): bool => $this->input('verification_channel') === 'email'),
                Rule::prohibitedIf(fn(): bool => $this->input('verification_channel') === 'phone'),
            ],
            'phone' => [
                'nullable',
                'string',
                new NigerianPhoneNumber(),
                'unique:users,phone',
                Rule::requiredIf(fn(): bool => $this->input('verification_channel') === 'phone'),
                Rule::prohibitedIf(fn(): bool => $this->input('verification_channel') === 'email'),
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
            'email.prohibited' => 'Use phone number sign up when phone verification is selected.',
            'phone.prohibited' => 'Use email sign up when email verification is selected.',
        ];
    }
}
