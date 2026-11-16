<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\Phone;

class CustomerService
{
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
            'name' => trim($name),
            'phone' => $normalized,
            'address' => $address,
        ]);
    }
}
