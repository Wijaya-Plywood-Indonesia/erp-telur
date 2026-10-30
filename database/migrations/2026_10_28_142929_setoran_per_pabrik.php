<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Setoran sekarang milik satu pabrik (dipakai bersama semua satpam di pabrik itu).
        Schema::table('satpam_setorans', function (Blueprint $table) {
            $table->string('pabrik', 20)->nullable()->after('id')->index();
        });

        // Isi pabrik untuk baris lama dari satpam pemiliknya.
        DB::statement('UPDATE satpam_setorans s JOIN satpams p ON p.id = s.satpam_id SET s.pabrik = p.pabrik');

        // satpam_id lama diganti "satpam yang menekan tombol tutup buku di HP".
        Schema::table('satpam_setorans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('satpam_id');
        });
        Schema::table('satpam_setorans', function (Blueprint $table) {
            $table->foreignId('ditutup_satpam_id')->nullable()->constrained('satpams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('satpam_setorans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ditutup_satpam_id');
        });
        Schema::table('satpam_setorans', function (Blueprint $table) {
            $table->foreignId('satpam_id')->nullable()->constrained('satpams')->cascadeOnDelete();
            $table->dropColumn('pabrik');
        });
    }
};