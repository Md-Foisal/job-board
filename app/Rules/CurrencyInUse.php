<?php

namespace App\Rules;

use App\Support\SalaryCurrencies;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A three-letter code from the currencies in use today. The forms offer
 * only those, so this mostly catches an old posting whose currency has
 * since been withdrawn, and says so instead of "the selected value is
 * invalid".
 */
class CurrencyInUse implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SalaryCurrencies::isInUse($value)) {
            $fail(__(':code is not a currency in use today. Choose one from the list.', [
                'code' => is_string($value) ? mb_substr($value, 0, 3) : '',
            ]));
        }
    }
}
