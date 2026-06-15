<?php

namespace App\Console\Commands;

use App\Models\SubAnakAkun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateNamaAkunAyam extends Command
{
    protected $signature   = 'ayam:update-nama-akun';
    protected $description = 'Update nama sub anak akun ayam berdasarkan usia real-time';

    public function handle(): void
    {
        // Ambil semua sub anak akun yang terhubung ke kandang
        $akunAyams = SubAnakAkun::with(['kandang.ayamAktif'])
            ->whereNotNull('id_kandang')
            ->get();

        $updated = 0;

        foreach ($akunAyams as $akun) {
            $kandang = $akun->kandang;

            if (!$kandang) continue;

            $ayam = $kandang->ayamAktif;

            // Jika tidak ada ayam aktif, biarkan nama apa adanya
            if (!$ayam) continue;

            // Ambil usia real-time dari accessor
            $umurFormat = $ayam->umur_format; // contoh: "74 minggu"

            // Ubah format: "74 minggu" → "74 mggu"
            $umurPendek = str_replace('minggu', 'mggu', $umurFormat);

            // Format nama baru: "Ayam KD 1 (74 mggu)"
            // Ambil nama dasar kandang tanpa usia lama
            $namaKandang = $kandang->nama_kandang; // contoh: "KD 1"
            $namaBaru    = "Ayam {$namaKandang} ({$umurPendek})";

            // Hanya update jika memang berubah
            if ($akun->nama_sub_anak_akun !== $namaBaru) {
                $akun->update(['nama_sub_anak_akun' => $namaBaru]);
                $updated++;

                Log::info("[UpdateNamaAkunAyam] Updated: {$akun->kode_sub_anak_akun} → {$namaBaru}");
            }
        }

        $this->info("Selesai. {$updated} akun diperbarui.");
        Log::info("[UpdateNamaAkunAyam] Selesai. Total updated: {$updated}");
    }
}
