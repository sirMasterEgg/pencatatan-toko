<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $owner = config('shop.owner');

        if (empty($owner['password'])) {
            throw new RuntimeException('Isi OWNER_PASSWORD di file .env sebelum menjalankan seeder.');
        }

        User::updateOrCreate(
            ['email' => $owner['email']],
            ['name' => $owner['name'], 'password' => $owner['password']],
        );
    }
}
