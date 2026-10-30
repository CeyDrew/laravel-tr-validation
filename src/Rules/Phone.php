<?php

declare(strict_types=1);

namespace CeyDrew\TrValidation\Rules;

use Closure;
use CeyDrew\TrValidation\Tr;
use Illuminate\Contracts\Validation\ValidationRule;

final class Phone implements ValidationRule
{
    public function __construct(private readonly bool $mobileOnly = false)
    {
    }

    /** Accept only mobile numbers (5xx). */
    public static function mobile(): self
    {
        return new self(mobileOnly: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Tr::isPhone($value, $this->mobileOnly)) {
            $fail('tr-validation::validation.'.($this->mobileOnly ? 'tr_mobile' : 'tr_phone'))->translate();
        }
    }
}
