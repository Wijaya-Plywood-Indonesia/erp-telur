<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Satu satpam bisa punya banyak perangkat (HP), masing-masing dengan token sendiri.
        Schema::create('satpam_perangkats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('satpam_id')->constrained('satpams')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('aktivasi_pada')->nullable();
            $table->timestamp('terakhir_dipakai')->nullable();
            $table->timestamps();
        });

        // Berapa HP yang boleh memakai satu kode bersamaan. 0 = tanpa batas.
        Schema::table('satpams', function (Blueprint $table) {
            $table->unsignedSmallInteger('maks_perangkat')->default(1)->after('aktif');
        });

        // Pindahkan token yang sudah ada, supaya HP yang sudah aktif tidak keluar.
        DB::statement(
            'INSERT INTO satpam_perangkats (satpam_id, token_hash, aktivasi_pada, terakhir_dipakai, created_at, updated_at)
             SELECT id, token_hash, aktivasi_pada, aktivasi_pada, NOW(), NOW()
             FROM satpams WHERE token_hash IS NOT NULL'
        );

        Schema::table('satpams', function (Blueprint $table) {
            $table->dropColumn(['token_hash', 'aktivasi_pada']);
        });
    }

    public function down(): void
    {
        Schema::table('satpams', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable();
            $table->timestamp('aktivasi_pada')->nullable();
            $table->dropColumn('maks_perangkat');
        });
        Schema::dropIfExists('satpam_perangkats');
    }
};