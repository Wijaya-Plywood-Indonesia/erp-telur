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
        Schema::table('produksi_telurs', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_validated');
        });

        Schema::table('produksi_telur_korektors', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('korektor_catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_telurs', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });

        Schema::table('produksi_telur_korektors', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
