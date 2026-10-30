<?php

declare(strict_types=1);

namespace CeyDrew\TrValidation\Tests\Unit;

use CeyDrew\TrValidation\Tr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrTest extends TestCase
{
    public static function validTckn(): array
    {
        return [['10000000146'], [10000000146], [' 10000000146 ']];
    }

    public static function invalidTckn(): array
    {
        return [
            'wrong 11th digit' => ['10000000147'],
            'wrong 10th digit' => ['10000000156'],
            'starts with 0' => ['01234567890'],
            'too short' => ['1000000014'],
            'too long' => ['100000001460'],
            'letters' => ['1000000014a'],
            'separators' => ['100 000 001 46'],
            'empty' => [''],
            'null' => [null],
            'array' => [['10000000146']],
            'float' => [10000000146.0],
        ];
    }

    #[DataProvider('validTckn')]
    public function test_valid_tckn(mixed $value): void
    {
        $this->assertTrue(Tr::isTckn($value));
    }

    #[DataProvider('invalidTckn')]
    public function test_invalid_tckn(mixed $value): void
    {
        $this->assertFalse(Tr::isTckn($value));
    }

    public function test_generated_tckn_numbers_are_valid_and_a_changed_check_digit_breaks_them(): void
    {
        mt_srand(42);
        for ($i = 0; $i < 500; $i++) {
            $tckn = self::makeTckn(mt_rand(100000000, 999999999));
            $this->assertTrue(Tr::isTckn($tckn), $tckn);

            $broken = $tckn;
            $broken[10] = (string) (((int) $tckn[10] + 1) % 10);
            $this->assertFalse(Tr::isTckn($broken), $broken);
        }
    }

    public function test_vkn(): void
    {
        $this->assertTrue(Tr::isVkn(self::makeVkn('123456789')));
        $this->assertFalse(Tr::isVkn('123456789'));
        $this->assertFalse(Tr::isVkn('12345678901'));
        $this->assertFalse(Tr::isVkn('12345 67890'));
        $this->assertFalse(Tr::isVkn('abcdefghij'));
        $this->assertFalse(Tr::isVkn(null));

        mt_srand(7);
        for ($i = 0; $i < 500; $i++) {
            $vkn = self::makeVkn(str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT));
            $this->assertTrue(Tr::isVkn($vkn), $vkn);
            $broken = substr($vkn, 0, 9).(((int) $vkn[9] + 1) % 10);
            $this->assertFalse(Tr::isVkn($broken), $broken);
        }
    }

    public function test_iban(): void
    {
        $this->assertTrue(Tr::isIban('TR330006100519786457841326'));
        $this->assertTrue(Tr::isIban('TR33 0006 1005 1978 6457 8413 26'));
        $this->assertTrue(Tr::isIban('tr330006100519786457841326'));

        $this->assertFalse(Tr::isIban('TR330006100519786457841327'), 'bad checksum');
        $this->assertFalse(Tr::isIban('TR34 0006 1005 1978 6457 8413 26'), 'bad check digits');
        $this->assertFalse(Tr::isIban('TR3300061005197864578413'), 'too short');
        $this->assertFalse(Tr::isIban('DE89370400440532013000'), 'not Turkish');
        $this->assertFalse(Tr::isIban('TR330006110519786457841326'), 'reserved digit must be 0');
        $this->assertFalse(Tr::isIban(''));
        $this->assertFalse(Tr::isIban(null));
    }

    public function test_phone(): void
    {
        $valid = ['05434135461', '5434135461', '+905434135461', '905434135461', '0543 413 54 61', '+90 (543) 413-54-61', '0212 555 12 34', '0850 222 33 44'];
        foreach ($valid as $ok) {
            $this->assertTrue(Tr::isPhone($ok), $ok);
        }

        $invalid = ['0543413546', '054341354611', '+15434135461', '0143 413 54 61', '0643 413 54 61', '0543-413-54-6a', '444 1 444', '', null];
        foreach ($invalid as $bad) {
            $this->assertFalse(Tr::isPhone($bad), (string) $bad);
        }

        $this->assertTrue(Tr::isPhone('0543 413 54 61', mobileOnly: true));
        $this->assertFalse(Tr::isPhone('0212 555 12 34', mobileOnly: true));
    }

    public function test_plate(): void
    {
        foreach (['34 ABC 123', '34ABC123', '06 A 1234', '06a1234', '35 AB 4567', '81 Z 12345', '01 ABC 12', '34-AB-123'] as $ok) {
            $this->assertTrue(Tr::isPlate($ok), $ok);
        }

        $invalid = ['00 A 1234', '82 A 1234', '34 ABCD 12', '34 A 123', '34 ABC 1234', '34 Q 1234', "34 \u{00C7}A 123", '34 AB 12', 'ABC 123', '', null];
        foreach ($invalid as $bad) {
            $this->assertFalse(Tr::isPlate($bad), (string) $bad);
        }
    }

    public function test_formatters(): void
    {
        $this->assertSame('+905434135461', Tr::formatPhoneE164('0543 413 54 61'));
        $this->assertSame('0543 413 54 61', Tr::formatPhone('+905434135461'));
        $this->assertSame('TR33 0006 1005 1978 6457 8413 26', Tr::formatIban('tr330006100519786457841326'));
        $this->assertSame('34 ABC 123', Tr::formatPlate('34abc123'));

        $this->assertNull(Tr::formatPhone('123'));
        $this->assertNull(Tr::formatPhoneE164('123'));
        $this->assertNull(Tr::formatIban('TR00'));
        $this->assertNull(Tr::formatPlate('99 A 1'));
    }

    /** Builds a valid T.C. Kimlik No from its first nine digits. */
    private static function makeTckn(int $first9): string
    {
        $d = array_map('intval', str_split((string) $first9));
        $d[9] = ((($d[0] + $d[2] + $d[4] + $d[6] + $d[8]) * 7 - ($d[1] + $d[3] + $d[5] + $d[7])) % 10 + 10) % 10;
        $d[10] = array_sum($d) % 10;

        return implode('', $d);
    }

    /** Builds a valid VKN from its first nine digits. */
    private static function makeVkn(string $first9): string
    {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $tmp = ((int) $first9[$i] + 9 - $i) % 10;
            $v = ($tmp * (2 ** (9 - $i))) % 9;
            $sum += ($tmp !== 0 && $v === 0) ? 9 : $v;
        }

        return $first9.((10 - $sum % 10) % 10);
    }
}
