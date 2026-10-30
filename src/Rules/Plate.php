<?php

declare(strict_types=1);

namespace CeyDrew\TrValidation\Rules;

use Closure;
use CeyDrew\TrValidation\Tr;
use Illuminate\Contracts\Validation\ValidationRule;

final class Plate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Tr::isPlate($value)) {
            $fail('tr-validation::validation.tr_plate')->translate();
        }
    }
}
