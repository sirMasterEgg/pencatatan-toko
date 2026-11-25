<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tugas 26: nominal yang sudah dibayar. Lunas = total, Belum bayar = 0, Sebagian = isian pemilik.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->default(0)->after('total');
        });

        // Data lama: Lunas dianggap dibayar penuh. Sebagian dan Belum bayar dianggap 0,
        // sehingga piutang lama tetap sama seperti sebelum tugas ini.
        DB::table('transactions')->where('payment_status', 'paid')->update(['paid_amount' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
