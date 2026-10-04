<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The password rule, and the words that describe it.
 *
 * Both live here so they cannot drift: the staff and admin profile pages
 * once told people "At least 8 characters" while the server also demanded
 * letters and numbers, so a password of eight letters was refused for a
 * reason the page never gave.
 *
 * `rule()` is what `Password::defaults()` returns (AppServiceProvider), and
 * `requirements()` is what `partials/password_rules.blade.php` prints and
 * ticks off as the person types.
 */
class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    public static function rule(): Password
    {
        return Password::min(self::MIN_LENGTH)->letters()->numbers()->symbols();
    }

    /**
     * One entry per check in `rule()`, in the order they are shown.
     *
     * Each `pattern` is the same expression Laravel's Password rule tests
     * (`\pL`, `\pN`, and `\p{Z}|\p{S}|\p{P}` for symbols), written for a
     * JavaScript `RegExp` with the `u` flag. Keep them identical: a
     * checklist that ticks a password the server then refuses is worse than
     * no checklist. Note that Laravel counts a space as a symbol, so the
     * checklist does too.
     *
     * @return list<array{label: string, min: int|null, pattern: string|null}>
     */
    public static function requirements(): array
    {
        return [
            ['label' => 'At least ' . self::MIN_LENGTH . ' characters', 'min' => self::MIN_LENGTH, 'pattern' => null],
            ['label' => 'A letter', 'min' => null, 'pattern' => '\p{L}'],
            ['label' => 'A number', 'min' => null, 'pattern' => '\p{N}'],
            ['label' => 'A special character, such as ! @ # ?', 'min' => null, 'pattern' => '[\p{Z}\p{S}\p{P}]'],
        ];
    }
}
