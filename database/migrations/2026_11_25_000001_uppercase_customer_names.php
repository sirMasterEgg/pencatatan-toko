<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tugas 24: nama dan alamat pelanggan selalu huruf besar. Ubah data lama sekali saja.
     */
    public function up(): void
    {
        DB::table('customers')->orderBy('id')->chunkById(100, function ($customers) {
            foreach ($customers as $customer) {
                $name = mb_strtoupper(trim($customer->name));
                $address = $customer->address === null || trim($customer->address) === ''
                    ? null
                    : mb_strtoupper(trim($customer->address));

                if ($name !== $customer->name || $address !== $customer->address) {
                    DB::table('customers')->where('id', $customer->id)->update(['name' => $name, 'address' => $address]);
                }
            }
        });
    }

    /**
     * Nama asli tidak disimpan, jadi tidak bisa dikembalikan.
     */
    public function down(): void
    {
        //
    }
};
