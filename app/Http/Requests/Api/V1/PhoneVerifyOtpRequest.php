<?php

namespace App\Http\Requests\Api\V1;

use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PhoneVerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone) && trim($phone) !== '') {
            $this->merge([
                'phone' => PhoneNormalizer::normalize($phone) ?? trim($phone),
            ]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:10', 'max:20'],
            'code' => ['required', 'digits:6'],
            'role' => ['nullable', Rule::in(['user', 'vendor'])],
        ];
    }
}
