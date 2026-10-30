<?php

declare(strict_types=1);

namespace CeyDrew\TrValidation\Rules;

use Closure;
use CeyDrew\TrValidation\Tr;
use Illuminate\Contracts\Validation\ValidationRule;

final class Tckn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Tr::isTckn($value)) {
            $fail('tr-validation::validation.tckn')->translate();
        }
    }
}
