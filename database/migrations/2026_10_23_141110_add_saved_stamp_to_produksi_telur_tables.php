<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tabel = ['produksi_telurs', 'produksi_telur_korektors'];

    public function up(): void
    {
        foreach ($this->tabel as $nama) {
            if (! Schema::hasColumn($nama, 'saved_by')) {
                Schema::table($nama, fn(Blueprint $t) => $t->unsignedBigInteger('saved_by')->nullable());
            }
            if (! Schema::hasColumn($nama, 'saved_at')) {
                Schema::table($nama, fn(Blueprint $t) => $t->timestamp('saved_at')->nullable());
            }
            if (! Schema::hasColumn($nama, 'is_diperbaiki')) {
                Schema::table($nama, fn(Blueprint $t) => $t->boolean('is_diperbaiki')->default(false));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tabel as $nama) {
            foreach (['saved_by', 'saved_at', 'is_diperbaiki'] as $kolom) {
                if (Schema::hasColumn($nama, $kolom)) {
                    Schema::table($nama, fn(Blueprint $t) => $t->dropColumn($kolom));
                }
            }
        }
    }
};
