<?php

namespace App\Support;

class Parse
{
    /**
     * Ubah isian jumlah dari form menjadi angka bertitik desimal.
     *
     * "0,5" dan "0.5" menjadi "0.5", "2" menjadi "2", kosong menjadi null.
     * Isian yang bukan angka dikembalikan apa adanya agar ditolak validasi.
     */
    public static function quantity(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return $value === null ? null : (string) $value;
        }

        $text = str_replace([' ', ','], ['', '.'], trim((string) $value));

        if ($text === '') {
            return null;
        }

        return preg_match('/^\d+(\.\d+)?$/', $text) === 1 ? $text : $value;
    }
}
