<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\NigerianPhoneNumber;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email')) && trim($this->input('email')) !== '') {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        } else {
            $this->merge(['email' => null]);
        }

        $phone = $this->input('phone');
        if (is_string($phone) && trim($phone) !== '') {
            $this->merge(['phone' => PhoneNormalizer::normalize($phone) ?? trim($phone)]);
        } else {
            $this->merge(['phone' => null]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', new NigerianPhoneNumber(), 'required_without:email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', Rule::in(['user', 'vendor'])],
            'portal' => ['nullable', Rule::in(['marketplace'])],
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
