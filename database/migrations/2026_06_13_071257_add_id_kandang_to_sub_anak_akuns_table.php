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
        Schema::table('sub_anak_akuns', function (Blueprint $table) {
            $table->foreignId('id_kandang')
                ->nullable()
                ->after('kode_sub_anak_akun')
                ->constrained('kandangs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sub_anak_akuns', function (Blueprint $table) {
            $table->dropForeign(['id_kandang']);
            $table->dropColumn('id_kandang');
        });
    }
};
