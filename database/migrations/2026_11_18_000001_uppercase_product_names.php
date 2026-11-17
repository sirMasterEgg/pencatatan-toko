<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tugas 14: nama barang selalu huruf besar. Ubah data lama sekali saja.
     */
    public function up(): void
    {
        DB::table('products')->orderBy('id')->chunkById(100, function ($products) {
            foreach ($products as $product) {
                $name = mb_strtoupper(trim($product->name));

                if ($name !== $product->name) {
                    DB::table('products')->where('id', $product->id)->update(['name' => $name]);
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
