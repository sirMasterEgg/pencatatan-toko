<?php

return [
    'units' => ['pcs', 'dus', 'pak', 'lusin', 'kg', 'gram', 'liter', 'botol', 'sak', 'karton', 'meter', 'rol'],

    'owner' => [
        'name' => env('OWNER_NAME', 'Pemilik Toko'),
        'email' => env('OWNER_EMAIL', 'pemilik@toko.test'),
        'password' => env('OWNER_PASSWORD'),
    ],
];
