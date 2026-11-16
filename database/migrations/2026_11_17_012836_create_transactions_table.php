<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->dateTime('transacted_at')->index();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('input_source', 20)->default('form');
            $table->string('status', 20)->default('confirmed')->index();
            $table->string('payment_status', 20);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('note')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
