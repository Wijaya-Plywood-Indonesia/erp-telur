<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_mutasi_telurs', function (Blueprint $table) {
            $table->id();
            $table->string('no_dokumen')->unique();
            $table->date('tanggal')->index();
            $table->string('tujuan', 20);                 // wijaya | wahana
            $table->json('items');                        // [{jenis, qty}, ...]
            $table->text('keterangan')->nullable();

            // menunggu_validasi | tervalidasi | ditolak | dibatalkan
            $table->string('status', 30)->default('menunggu_validasi')->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('alasan_tolak')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_mutasi_telurs');
    }
};