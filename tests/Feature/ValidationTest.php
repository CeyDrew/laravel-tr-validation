<?php

declare(strict_types=1);

namespace CeyDrew\TrValidation\Tests\Feature;

use CeyDrew\TrValidation\Rules\Iban;
use CeyDrew\TrValidation\Rules\Phone;
use CeyDrew\TrValidation\Rules\Plate;
use CeyDrew\TrValidation\Rules\Tckn;
use CeyDrew\TrValidation\Rules\Vkn;
use CeyDrew\TrValidation\TrValidationServiceProvider;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase;

final class ValidationTest extends TestCase
{
    private const VALID = [
        'tc' => '10000000146',
        'vergi' => '1234567890',
        'iban' => 'TR33 0006 1005 1978 6457 8413 26',
        'tel' => '0212 555 12 34',
        'cep' => '0543 413 54 61',
        'plaka' => '34 ABC 123',
    ];

    private const INVALID = [
        'tc' => '10000000147',
        'vergi' => '1234567891',
        'iban' => 'TR34 0006 1005 1978 6457 8413 26',
        'tel' => '123',
        'cep' => '0212 555 12 34',
        'plaka' => '99 A 1',
    ];

    protected function getPackageProviders($app): array
    {
        return [TrValidationServiceProvider::class];
    }

    public function test_string_rules(): void
    {
        $rules = ['tc' => 'tckn', 'vergi' => 'vkn', 'iban' => 'iban_tr', 'tel' => 'tr_phone', 'cep' => 'tr_mobile', 'plaka' => 'tr_plate'];

        $this->assertTrue(Validator::make(self::VALID, $rules)->passes());
        $this->assertSame(array_keys($rules), Validator::make(self::INVALID, $rules)->errors()->keys());
    }

    public function test_rule_objects(): void
    {
        $rules = ['tc' => [new Tckn], 'vergi' => [new Vkn], 'iban' => [new Iban], 'tel' => [new Phone], 'cep' => [Phone::mobile()], 'plaka' => [new Plate]];

        $this->assertTrue(Validator::make(self::VALID, $rules)->passes());
        $this->assertSame(array_keys($rules), Validator::make(self::INVALID, $rules)->errors()->keys());
    }

    public function test_messages_follow_the_locale(): void
    {
        $this->app->setLocale('tr');
        $this->assertSame(
            'tc geçerli bir T.C. kimlik numarası olmalıdır.',
            Validator::make(['tc' => '1'], ['tc' => 'tckn'])->errors()->first('tc')
        );
        $this->assertSame(
            'cep geçerli bir cep telefonu numarası olmalıdır.',
            Validator::make(['cep' => '1'], ['cep' => [Phone::mobile()]])->errors()->first('cep')
        );

        $this->app->setLocale('en');
        $this->assertSame(
            'The iban must be a valid Turkish IBAN.',
            Validator::make(['iban' => 'x'], ['iban' => 'iban_tr'])->errors()->first('iban')
        );
        $this->assertSame(
            'The plaka must be a valid Turkish vehicle plate.',
            Validator::make(['plaka' => 'x'], ['plaka' => [new Plate]])->errors()->first('plaka')
        );
    }

    public function test_app_translation_overrides_the_package_message(): void
    {
        $this->app->setLocale('tr');
        $this->app['translator']->addLines(['validation.tckn' => 'Kimlik no hatalı.'], 'tr');

        $this->assertSame('Kimlik no hatalı.', Validator::make(['tc' => '1'], ['tc' => 'tckn'])->errors()->first('tc'));
    }

    public function test_nullable_and_required_work_as_usual(): void
    {
        $this->assertTrue(Validator::make(['tc' => null], ['tc' => 'nullable|tckn'])->passes());
        $this->assertTrue(Validator::make([], ['tc' => 'tckn'])->passes());
        $this->assertFalse(Validator::make(['tc' => ''], ['tc' => 'required|tckn'])->passes());
    }
}
