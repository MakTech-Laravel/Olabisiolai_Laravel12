<?php

namespace App\Http\Requests\Api\V1;

use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class VerifyOtpRequest extends FormRequest
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
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'min:13', 'max:20'],
        ];
    }
}
