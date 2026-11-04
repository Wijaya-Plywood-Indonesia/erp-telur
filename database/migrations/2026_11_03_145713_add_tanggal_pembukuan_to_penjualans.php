<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal pembukuan nota. Kosong = pakai tanggal nota (nota POS web & data lama tidak berubah).
 * Nota dari app satpam diisi saat setorannya ditutup.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('penjualans', 'tanggal_pembukuan')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->dateTime('tanggal_pembukuan')->nullable()->after('tanggal');
                $table->index('tanggal_pembukuan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('penjualans', 'tanggal_pembukuan')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->dropIndex(['tanggal_pembukuan']);
                $table->dropColumn('tanggal_pembukuan');
            });
        }
    }
};