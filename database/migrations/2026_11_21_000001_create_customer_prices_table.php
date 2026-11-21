<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tugas 17: harga khusus per pelanggan per barang, disimpan sebagai riwayat.
     * sell_price null berarti harga khusus dihentikan mulai effective_from.
     */
    public function up(): void
    {
        Schema::create('customer_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('sell_price')->nullable();
            $table->dateTime('effective_from');
            $table->unsignedBigInteger('source_transaction_id')->nullable()->index();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'product_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_prices');
    }
};
