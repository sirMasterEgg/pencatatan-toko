<?php

return [
    'units' => ['pcs', 'dus', 'pak', 'lusin', 'kg', 'gram', 'liter', 'botol', 'sak', 'karton', 'meter', 'rol'],

    // Satuan yang jumlahnya boleh desimal. Satuan lain wajib bilangan bulat.
    'decimal_units' => ['kg', 'gram', 'liter', 'meter'],

    'owner' => [
        'name' => env('OWNER_NAME', 'Pemilik Toko'),
        'email' => env('OWNER_EMAIL', 'pemilik@toko.test'),
        'password' => env('OWNER_PASSWORD'),
    ],
];
