<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambalan: melengkapi kolom/tabel satpam yang belum ada di production.
 * Aman dijalankan berulang (semua dicek dulu dengan hasTable/hasColumn).
 */
return new class extends Migration {
    public function up(): void
    {
        // ── satpams ─────────────────────────────────────────────
        if (! Schema::hasTable('satpams')) {
            Schema::create('satpams', function (Blueprint $table) {
                $table->id();
                $table->string('pabrik', 20);
                $table->string('nama', 100);
                $table->string('kode_akses', 20);
                $table->string('token_hash', 64)->nullable();
                $table->timestamp('aktivasi_pada')->nullable();
                $table->boolean('aktif')->default(true);
                $table->timestamps();
                $table->unique(['pabrik', 'kode_akses']);
            });
        } else {
            Schema::table('satpams', function (Blueprint $table) {
                if (! Schema::hasColumn('satpams', 'kode_akses')) {
                    $table->string('kode_akses', 20)->nullable();
                }
                if (! Schema::hasColumn('satpams', 'token_hash')) {
                    $table->string('token_hash', 64)->nullable();
                }
                if (! Schema::hasColumn('satpams', 'aktivasi_pada')) {
                    $table->timestamp('aktivasi_pada')->nullable();
                }
                if (! Schema::hasColumn('satpams', 'aktif')) {
                    $table->boolean('aktif')->default(true);
                }
            });
        }

        // ── satpam_setorans ─────────────────────────────────────
        if (! Schema::hasTable('satpam_setorans')) {
            Schema::create('satpam_setorans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('satpam_id')->constrained('satpams')->cascadeOnDelete();
                $table->string('status', 12)->default('DIBUKA');
                $table->unsignedBigInteger('sampai_penjualan_id');
                $table->decimal('total', 15, 2)->default(0);
                $table->unsignedInteger('jumlah_nota')->default(0);
                $table->foreignId('dibuka_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('dibuka_pada')->nullable();
                $table->timestamp('ditutup_pada')->nullable();
                $table->timestamps();
            });
        }

        // ── penjualans ──────────────────────────────────────────
        Schema::table('penjualans', function (Blueprint $table) {
            if (! Schema::hasColumn('penjualans', 'satpam_id')) {
                $table->foreignId('satpam_id')->nullable()->constrained('satpams')->nullOnDelete();
            }
            if (! Schema::hasColumn('penjualans', 'satpam_setoran_id')) {
                $table->foreignId('satpam_setoran_id')->nullable()->constrained('satpam_setorans')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Sengaja kosong: ini migrasi tambalan.
    }
};
