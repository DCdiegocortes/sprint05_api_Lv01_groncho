<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SingleEmoji implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isSingleEmoji = is_string($value)
            && preg_match_all('/\X/u', $value) === 1
            && preg_match('/[\p{Extended_Pictographic}\p{Regional_Indicator}]/u', $value) === 1
            && preg_match('/[\p{L}\p{N}\s]/u', $value) === 0;

        if (! $isSingleEmoji) {
            $fail('The :attribute must be a single emoji.');
        }
    }
}
