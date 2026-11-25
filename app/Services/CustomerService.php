<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\Phone;

class CustomerService
{
    /**
     * Nama dan alamat pelanggan disimpan huruf besar. Teks kosong menjadi null.
     */
    public static function normalizeText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value);
    }

    public function findOrCreate(string $name, ?string $phone = null, ?string $address = null): Customer
    {
        $normalized = Phone::normalize($phone);

        if ($normalized !== null) {
            $existing = Customer::where('phone', $normalized)->first();

            if ($existing) {
                return $existing;
            }
        }

        return Customer::create([
            'name' => self::normalizeText($name),
            'phone' => $normalized,
            'address' => self::normalizeText($address),
        ]);
    }
}
