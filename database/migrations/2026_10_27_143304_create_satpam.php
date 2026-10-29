<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Satu baris = satu satpam = satu perangkat.
        Schema::create('satpams', function (Blueprint $table) {
            $table->id();
            $table->string('pabrik', 20);                 // wijaya | wahana
            $table->string('nama', 100);
            $table->string('kode_akses', 20);             // passcode, diketik satpam sekali saat pertama install
            $table->string('token_hash', 64)->nullable(); // sha256 token perangkat (token asli hanya ada di HP)
            $table->timestamp('aktivasi_pada')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['pabrik', 'kode_akses']);
        });

        // Satu baris = satu kali tutup buku (setoran).
        Schema::create('satpam_setorans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('satpam_id')->constrained('satpams')->cascadeOnDelete();
            $table->string('status', 12)->default('DIBUKA');   // DIBUKA | SELESAI | DIBATALKAN
            $table->unsignedBigInteger('sampai_penjualan_id'); // nota sampai ID ini yang ikut ditutup
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedInteger('jumlah_nota')->default(0);
            $table->foreignId('dibuka_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dibuka_pada')->nullable();
            $table->timestamp('ditutup_pada')->nullable();
            $table->timestamps();
        });

        Schema::table('penjualans', function (Blueprint $table) {
            $table->foreignId('satpam_id')->nullable()->constrained('satpams')->nullOnDelete();
            $table->foreignId('satpam_setoran_id')->nullable()->constrained('satpam_setorans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('satpam_setoran_id');
            $table->dropConstrainedForeignId('satpam_id');
        });
        Schema::dropIfExists('satpam_setorans');
        Schema::dropIfExists('satpams');
    }
};