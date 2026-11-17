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

    /**
     * Jumlah untuk diisikan kembali ke kolom form: tanpa titik ribuan, koma sebagai desimal.
     * Contoh: 1000 menjadi "1000", "0.500" menjadi "0,5".
     */
    public static function quantityInput(int|float|string|null $quantity): string
    {
        $text = number_format((float) ($quantity ?? 0), 3, ',', '');

        return rtrim(rtrim($text, '0'), ',');
    }

    public static function quantity(int|float|string|null $quantity): string
    {
        $text = number_format((float) ($quantity ?? 0), 3, ',', '.');

        return rtrim(rtrim($text, '0'), ',');
    }
}
