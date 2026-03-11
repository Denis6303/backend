<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BooleanString implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->isBooleanLike($value)) {
            $fail("The {$attribute} field must be a boolean.");
        }
    }

    public static function cast(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function isBooleanLike(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_int($value)) {
            return in_array($value, [0, 1], true);
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true);
        }

        return false;
    }
}

