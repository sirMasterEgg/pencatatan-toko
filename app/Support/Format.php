<?php

namespace App\Support;

class Format
{
    public static function rupiah(int|float|string|null $amount): string
    {
        return 'Rp'.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    public static function thousands(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        return number_format((float) $value, 0, ',', '.');
    }

    public static function quantity(int|float|string|null $quantity): string
    {
        $text = number_format((float) ($quantity ?? 0), 3, ',', '.');

        return rtrim(rtrim($text, '0'), ',');
    }
}
