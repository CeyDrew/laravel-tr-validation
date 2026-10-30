<?php

declare(strict_types=1);

namespace CeyDrew\TrValidation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

final class TrValidationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'tr-validation');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/tr-validation'),
        ], 'tr-validation-translations');

        $checks = [
            'tckn' => static fn ($v) => Tr::isTckn($v),
            'vkn' => static fn ($v) => Tr::isVkn($v),
            'iban_tr' => static fn ($v) => Tr::isIban($v),
            'tr_phone' => static fn ($v) => Tr::isPhone($v),
            'tr_mobile' => static fn ($v) => Tr::isPhone($v, mobileOnly: true),
            'tr_plate' => static fn ($v) => Tr::isPlate($v),
        ];

        foreach ($checks as $rule => $check) {
            Validator::extend($rule, static fn ($attribute, $value) => $check($value));

            // Use the package message in the current locale unless the app defines validation.<rule> itself.
            Validator::replacer($rule, static function ($message, $attribute, $r, $parameters, $validator) use ($rule) {
                if ($validator->getTranslator()->has("validation.$rule")) {
                    return $message;
                }

                return $validator->getTranslator()->get("tr-validation::validation.$rule", [
                    'attribute' => $validator->getDisplayableAttribute($attribute),
                ]);
            });
        }
    }
}
