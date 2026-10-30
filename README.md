# Laravel TR Validation

[![tests](https://github.com/CeyDrew/laravel-tr-validation/actions/workflows/tests.yml/badge.svg)](https://github.com/CeyDrew/laravel-tr-validation/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Laravel validation rules for Turkish data — **T.C. Kimlik No**, **Vergi Kimlik No**, **TR IBAN**, **phone numbers** and **vehicle plates** — with real checksum validation, Turkish and English messages, and a framework-independent helper class.

[Türkçe aşağıda](#türkçe)

```php
$request->validate([
    'tc_kimlik' => ['required', 'tckn'],
    'vergi_no'  => ['nullable', 'vkn'],
    'iban'      => ['required', 'iban_tr'],
    'telefon'   => ['required', 'tr_mobile'],
    'plaka'     => ['required', 'tr_plate'],
]);
```

## Installation

```bash
composer require ceydrew/laravel-tr-validation
```

Requires PHP 8.2+ and Laravel 11, 12 or 13. The service provider is registered automatically.

## Rules

| Rule | Rule object | Accepts | Checks |
|---|---|---|---|
| `tckn` | `new Tckn` | `10000000146` | 11 digits, not starting with 0, both check digits |
| `vkn` | `new Vkn` | `1234567890` | 10 digits and the VKN check digit |
| `iban_tr` | `new Iban` | `TR33 0006 1005 1978 6457 8413 26` | Turkish format (26 chars, reserved `0`), ISO 7064 mod-97 checksum; spaces and lowercase allowed |
| `tr_phone` | `new Phone` | `0543 413 54 61`, `+90 (212) 555-12-34`, `5434135461` | Mobile (5xx), landline (2xx/3xx/4xx) or 850; `+90`, `90` and `0` prefixes |
| `tr_mobile` | `Phone::mobile()` | `0543 413 54 61` | Mobile numbers only |
| `tr_plate` | `new Plate` | `34 ABC 123`, `06a1234` | Province code 01–81 and one of the official letter/digit patterns; letters Ç Ğ İ Ö Ş Ü Q W X are rejected |

Rule objects live in `CeyDrew\TrValidation\Rules`:

```php
use CeyDrew\TrValidation\Rules\{Iban, Phone, Tckn};

$request->validate([
    'tc_kimlik' => ['required', new Tckn],
    'iban'      => ['required', new Iban],
    'telefon'   => ['required', Phone::mobile()],
]);
```

Like every Laravel rule, they skip empty values unless you add `required`, and work with `nullable`.

## Helpers and formatting

The `Tr` class has no Laravel dependency, so you can use it anywhere — in services, imports or console commands:

```php
use CeyDrew\TrValidation\Tr;

Tr::isTckn('10000000146');                    // true
Tr::isIban('tr33 0006 1005 1978 6457 8413 26'); // true
Tr::isPhone('0212 555 12 34', mobileOnly: true); // false

Tr::formatPhone('+905434135461');      // "0543 413 54 61"
Tr::formatPhoneE164('0543 413 54 61'); // "+905434135461"  — store this in the database
Tr::formatIban('tr330006100519786457841326'); // "TR33 0006 1005 1978 6457 8413 26"
Tr::formatPlate('34abc123');           // "34 ABC 123"
```

Formatters return `null` for invalid input, so `Tr::formatPhoneE164($input) ?? abort(422)` is a valid pattern.

## Messages

Messages are included in Turkish and English and follow the app locale:

> *tc_kimlik geçerli bir T.C. kimlik numarası olmalıdır.*
> *The tc_kimlik must be a valid Turkish identity number (T.C. Kimlik No).*

To change them, either add the rule key to your own `lang/{locale}/validation.php` (e.g. `'tckn' => 'Kimlik numarası hatalı.'`), or publish the package files:

```bash
php artisan vendor:publish --tag=tr-validation-translations
```

## How the checks work

<details>
<summary>T.C. Kimlik No</summary>

With digits `d1 … d11`:
- `d10 = ((d1 + d3 + d5 + d7 + d9) × 7 − (d2 + d4 + d6 + d8)) mod 10`
- `d11 = (d1 + d2 + … + d10) mod 10`
- `d1` cannot be 0.
</details>

<details>
<summary>Vergi Kimlik No</summary>

For each of the first nine digits `dᵢ` (i = 1…9): `t = (dᵢ + 10 − i) mod 10`, `v = (t × 2^(10 − i)) mod 9`, and `v = 9` when `t ≠ 0` and `v = 0`. The tenth digit is `(10 − (Σv mod 10)) mod 10`.
</details>

<details>
<summary>TR IBAN</summary>

`TR` + 2 check digits + 5-digit bank code + reserved `0` + 16-character account number. The first four characters are moved to the end, letters become numbers (A = 10 … Z = 35) and the result mod 97 must be 1.
</details>

These checks prove a number is *well-formed*, not that it exists or belongs to the user — use official services (e.g. NVİ for identity, your bank for IBAN ownership) when you need that.

## Testing

```bash
composer install
composer test
```

The suite covers each rule with valid and invalid examples, 1,000 generated TCKN/VKN numbers, string rules and rule objects through Laravel's validator, locale-aware messages and app-level overrides. CI runs on PHP 8.2–8.4 with Laravel 11, 12 and 13.

## License

MIT © 2026 Abdurrahman Bilici

---

## Türkçe

Türkiye'ye özel veriler için Laravel doğrulama kuralları: **T.C. kimlik no**, **vergi kimlik no**, **TR IBAN**, **telefon** ve **araç plakası**. Gerçek kontrol hanesi algoritmalarıyla çalışır, Türkçe ve İngilizce hata mesajlarıyla gelir.

```bash
composer require ceydrew/laravel-tr-validation
```

```php
$request->validate([
    'tc_kimlik' => ['required', 'tckn'],
    'iban'      => ['required', 'iban_tr'],
    'telefon'   => ['required', 'tr_mobile'],
]);
```

- Kurallar: `tckn`, `vkn`, `iban_tr`, `tr_phone`, `tr_mobile`, `tr_plate` (ya da `new Tckn`, `Phone::mobile()` gibi kural nesneleri).
- `Tr` sınıfı Laravel'e bağlı değildir: `Tr::isTckn()`, `Tr::formatPhone()`, `Tr::formatPhoneE164()`, `Tr::formatIban()`, `Tr::formatPlate()`.
- Hata mesajları uygulamanın diline göre Türkçe ya da İngilizce gelir; kendi `lang/tr/validation.php` dosyanda aynı anahtarı yazarak değiştirebilirsin.
- Bu kontroller numaranın **biçim olarak** doğru olduğunu gösterir; gerçekten var olduğunu ya da kullanıcıya ait olduğunu göstermez.
