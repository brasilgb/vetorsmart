<?php

namespace App\Support;

/**
 * Brazilian currency amounts kept as integer cents. Parsing and formatting work on strings and integers only, so no
 * amount ever passes through floating point.
 */
final class Money
{
    /**
     * Cents of an amount typed in reais: "1.234,56", "1234,56", "1234.56", "1234" or "0". Null when it is not an amount.
     */
    public static function parse(?string $value): ?int
    {
        $value = str_replace(['R$', ' ', "\u{00A0}"], '', trim((string) $value));

        if ($value === '') {
            return null;
        }

        // A comma is the decimal separator (dots are thousands); without a comma, a dot followed by one or two digits is.
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            [$integer, $fraction] = array_pad(explode(',', $value, 2), 2, '');
        } elseif (preg_match('/^\d+\.\d{1,2}$/', $value) === 1) {
            [$integer, $fraction] = explode('.', $value, 2);
        } else {
            $integer = str_replace('.', '', $value);
            $fraction = '';
        }

        if (preg_match('/^\d{1,10}$/', $integer) !== 1 || preg_match('/^\d{0,2}$/', $fraction) !== 1) {
            return null;
        }

        return (int) $integer * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * "R$ 1.234,56".
     */
    public static function format(int $cents): string
    {
        return 'R$ '.self::plain($cents);
    }

    /**
     * "1.234,56", for form fields.
     */
    public static function plain(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.number_format(intdiv($cents, 100), 0, ',', '.').','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
