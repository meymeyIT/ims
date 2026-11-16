<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts local and international formats: 012 345 678, 012-345-678, +855 12 345 678, (023) 123 456.
 * Requires 8 to 15 digits in total.
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;
        $digits = preg_replace('/\D/', '', $value);

        if (! preg_match('/^\+?[0-9\s\-()]+$/', $value) || strlen($digits) < 8 || strlen($digits) > 15) {
            $fail('The :attribute must be a valid phone number (8–15 digits; spaces, +, - and brackets allowed).');
        }
    }
}
