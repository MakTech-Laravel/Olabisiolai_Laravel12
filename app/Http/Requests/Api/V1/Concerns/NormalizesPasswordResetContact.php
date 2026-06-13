<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Support\PhoneNormalizer;
use Illuminate\Support\Str;

trait NormalizesPasswordResetContact
{
    protected function preparePasswordResetContactValidation(): void
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
    protected function passwordResetContactRules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'min:10', 'max:20', 'required_without:email'],
        ];
    }
}
