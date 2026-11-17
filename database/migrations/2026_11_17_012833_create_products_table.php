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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 20);
            $table->string('category')->nullable();
            $table->string('code')->nullable()->unique();
            $table->string('status', 20)->default('needs_review')->index();
            $table->foreignId('merged_into_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedBigInteger('created_from_transaction_id')->nullable()->index();
            $table->timestamps();
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
