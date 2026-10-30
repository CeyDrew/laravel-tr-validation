<?php

declare(strict_types=1);

namespace CeyDrew\TrValidation;

/**
 * Framework-independent checks and normalizers for Turkish identifiers.
 * Every method accepts mixed input and never throws.
 */
final class Tr
{
    /** Letters used on Turkish vehicle plates (no Ç, Ğ, İ, Ö, Ş, Ü, Q, W, X). */
    private const PLATE_LETTERS = 'ABCDEFGHIJKLMNOPRSTUVYZ';

    /**
     * T.C. Kimlik No: 11 digits, cannot start with 0, the last two digits are checksums.
     */
    public static function isTckn(mixed $value): bool
    {
        $value = self::digitsOnly($value, allowSeparators: false);

        if ($value === null || ! preg_match('/^[1-9]\d{10}$/', $value)) {
            return false;
        }

        $d = array_map('intval', str_split($value));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];

        $tenth = (($odd * 7 - $even) % 10 + 10) % 10;
        $eleventh = (array_sum(array_slice($d, 0, 10))) % 10;

        return $d[9] === $tenth && $d[10] === $eleventh;
    }

    /**
     * Vergi Kimlik No (tax number of a company): 10 digits, the last digit is a checksum.
     */
    public static function isVkn(mixed $value): bool
    {
        $value = self::digitsOnly($value, allowSeparators: false);

        if ($value === null || ! preg_match('/^\d{10}$/', $value)) {
            return false;
        }

        $d = array_map('intval', str_split($value));
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $tmp = ($d[$i] + 9 - $i) % 10;
            $v = ($tmp * (2 ** (9 - $i))) % 9;
            if ($tmp !== 0 && $v === 0) {
                $v = 9;
            }
            $sum += $v;
        }

        return (10 - $sum % 10) % 10 === $d[9];
    }

    /**
     * Turkish IBAN: "TR" + 2 check digits + 5-digit bank code + reserved "0" + 16-character account number,
     * validated with the ISO 7064 mod-97 checksum. Spaces are ignored and lowercase is accepted.
     */
    public static function isIban(mixed $value): bool
    {
        $iban = self::compactIban($value);

        if ($iban === null || ! preg_match('/^TR\d{7}0[0-9A-Z]{16}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder.$chunk) % 97);
        }

        return $remainder === 1;
    }

    /**
     * Turkish phone number (mobile 5xx, landline 2xx/3xx/4xx, or 850). Accepts +90, 90 and 0 prefixes
     * and common separators: "0543 413 54 61", "+90 (543) 413-54-61", "5434135461".
     */
    public static function isPhone(mixed $value, bool $mobileOnly = false): bool
    {
        $national = self::nationalPhone($value);

        if ($national === null) {
            return false;
        }

        return $mobileOnly
            ? (bool) preg_match('/^5\d{9}$/', $national)
            : (bool) preg_match('/^(?:[2-5]\d{2}|850)\d{7}$/', $national);
    }

    /**
     * Turkish vehicle plate: province code 01-81, then 1 letter + 4-5 digits, 2 letters + 3-4 digits
     * or 3 letters + 2-3 digits. Spaces are optional: "34 ABC 123", "06A1234".
     */
    public static function isPlate(mixed $value): bool
    {
        $plate = self::normalizePlate($value);

        if ($plate === null) {
            return false;
        }

        $l = '['.self::PLATE_LETTERS.']';

        return (bool) preg_match(
            "/^(0[1-9]|[1-7]\d|8[01]) (?:{$l} \d{4,5}|{$l}{2} \d{3,4}|{$l}{3} \d{2,3})$/",
            $plate
        );
    }

    /** "0543 413 54 61" → "+905434135461" (E.164), or null when it is not a valid Turkish number. */
    public static function formatPhoneE164(mixed $value): ?string
    {
        return self::isPhone($value) ? '+90'.self::nationalPhone($value) : null;
    }

    /** "+905434135461" → "0543 413 54 61", or null when it is not a valid Turkish number. */
    public static function formatPhone(mixed $value): ?string
    {
        if (! self::isPhone($value)) {
            return null;
        }

        $n = self::nationalPhone($value);

        return sprintf('0%s %s %s %s', substr($n, 0, 3), substr($n, 3, 3), substr($n, 6, 2), substr($n, 8, 2));
    }

    /** "tr330006100519786457841326" → "TR33 0006 1005 1978 6457 8413 26", or null when invalid. */
    public static function formatIban(mixed $value): ?string
    {
        return self::isIban($value) ? trim(chunk_split((string) self::compactIban($value), 4, ' ')) : null;
    }

    /** "34abc123" → "34 ABC 123", or null when it is not a valid plate. */
    public static function formatPlate(mixed $value): ?string
    {
        return self::isPlate($value) ? self::normalizePlate($value) : null;
    }

    private static function digitsOnly(mixed $value, bool $allowSeparators): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $allowSeparators ? preg_replace('/[\s\-().]/', '', $value) : $value;
    }

    private static function compactIban(mixed $value): ?string
    {
        return is_string($value) ? strtoupper(preg_replace('/\s+/', '', $value)) : null;
    }

    private static function nationalPhone(mixed $value): ?string
    {
        $digits = self::digitsOnly($value, allowSeparators: true);

        if ($digits === null || ! preg_match('/^\+?\d+$/', $digits)) {
            return null;
        }

        $digits = ltrim($digits, '+');

        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    /** Uppercases and puts single spaces between the province code, letters and digits. */
    private static function normalizePlate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $compact = strtoupper(preg_replace('/[\s\-]+/', '', $value));

        if (! preg_match('/^(\d{2})([A-Z]{1,3})(\d{2,5})$/', $compact, $m)) {
            return null;
        }

        return "{$m[1]} {$m[2]} {$m[3]}";
    }
}
