<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $blueprint) {
            // 1. Drop foreign key constraint terlebih dahulu agar database mengizinkan kolom dihapus
            $blueprint->dropForeign('stock_opnames_toko_id_foreign');

            // 2. Drop kolom toko_id
            $blueprint->dropColumn('toko_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $blueprint) {
            // Kembalikan kolom toko_id jika melakukan rollback migrasi
            $blueprint->foreignId('toko_id')
                ->after('no_opname') // diletakkan kembali setelah no_opname
                ->nullable() // dibuat nullable sementara saat rollback agar tidak crash jika sudah ada data
                ->constrained('identitas_toko')
                ->restrictOnDelete();
        });
    }
};
