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
        Schema::create('produksi_telur_korektors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_telur')
                ->unique()
                ->constrained('produksi_telurs')
                ->cascadeOnDelete();

            $table->unsignedInteger('korektor_peti')->nullable();
            $table->decimal('korektor_kiloan', 10, 2)->nullable();
            $table->decimal('korektor_sisa', 10, 2)->nullable();
            $table->decimal('korektor_bentes', 10, 2)->nullable();
            $table->text('korektor_catatan')->nullable();

            // Audit terpisah dari produksi_telurs.created_by
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produksi_telur_korektors');
    }
};
