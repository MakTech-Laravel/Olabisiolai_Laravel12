<?php

namespace App\Rules;

use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NigerianPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNormalizer::isValidNigerian($value)) {
            $fail('Enter a valid Nigerian phone number (e.g. 08012345678 or +234 812 345 6789).');
        }
    }
}
