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
            $table->integer('korektor_peti')->nullable();
            $table->decimal('korektor_kiloan', 10, 2)->nullable();
            $table->decimal('korektor_sisa', 10, 2)->nullable();
            $table->decimal('korektor_bentes', 10, 2)->nullable();
            $table->text('korektor_catatan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_telurs', function (Blueprint $table) {
            $table->dropColumn([
                'korektor_peti',
                'korektor_kiloan',
                'korektor_sisa',
                'korektor_bentes',
                'korektor_catatan',
            ]);
        });
    }
};
