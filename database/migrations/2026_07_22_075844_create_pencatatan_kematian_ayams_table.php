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
        Schema::create('pencatatan_kematian_ayams', function (Blueprint $table) {
            $table->foreignId('id_ayam')
                ->constrained('ayams')
                ->cascadeOnDelete();

            // Tanggal pencatatan harian
            $table->date('tanggal')->index();

            // Rincian ekor berkurang
            $table->integer('jumlah_mati')->default(0);
            $table->integer('jumlah_afkir')->default(0);

            // Keterangan & diagnosa
            $table->string('penyebab')->nullable();
            $table->text('catatan')->nullable();

            // Status Validasi & Kunci Data (Locking)
            $table->boolean('is_validated')->default(false);
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            // Audit user pembuat
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Aturan unik: 1 batch ayam hanya boleh punya 1 catatan per tanggal
            $table->unique(['id_ayam', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pencatatan_kematian_ayams');
    }
};
