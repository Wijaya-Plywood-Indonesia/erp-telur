<?php

namespace App\Services;

use App\Models\Ayam;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalUmum;
use App\Models\Kandang;
use App\Models\LogKematianAyam;
use App\Models\PencatatanKematianAyam;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostingMutasiAyamService
{
    /**
     * Memposting Jurnal Pembantu Kematian/Afkir ke Jurnal Umum
     * sekaligus menyinkronkan stok fisik ayam di Kandang.
     */
    /* STREAMING_CHUNK:Executing general ledger posting and chicken stock mutation */
    public function postingKeJurnalUmumDanUpdateStok(string $tanggal, int $userId): void
    {
        DB::transaction(function () use ($tanggal, $userId) {
            $notaMati  = "MATIAYAM-{$tanggal}";
            $notaAfkir = "AFKIRAYAM-{$tanggal}";

            // 1. Ambil seluruh Jurnal Pembantu Header untuk mutasi ayam pada tanggal aktif
            $jpHeaders = JurnalPembantuHeader::with('items')
                ->where('modul_asal', 'kematian_ayam')
                ->whereIn('no_dokumen', [$notaMati, $notaAfkir])
                ->get();

            if ($jpHeaders::isEmpty()) {
                throw new Exception("Tidak ditemukan Jurnal Pembantu Kematian/Afkir untuk tanggal {$tanggal}.");
            }

            // 2. Pindahkan data Jurnal Pembantu ke Jurnal Umum (General Ledger)
            foreach ($jpHeaders as $header) {
                if ($header->status === JurnalPembantuHeader::STATUS_DIPOSTING) {
                    continue; // Skip jika sudah diposting sebelumnya
                }

                JurnalUmum::create([
                    'tgl_transaksi' => $header->tgl_transaksi,
                    'jenis_transaksi' => $header->jenis_transaksi ?? 'pk',
                    'jurnal'        => $header->jurnal,
                    'no_akun'       => $header->no_akun,
                    'nama_akun'     => $header->nama_akun,
                    'map'           => $header->map,
                    'keterangan'    => $header->keterangan,
                    'no_dokumen'    => $header->no_dokumen,
                    'dibuat_oleh'   => $userId,
                ]);

                // Ubah status Jurnal Pembantu Header menjadi DIPOSTING
                $header->update(['status' => JurnalPembantuHeader::STATUS_DIPOSTING]);
            }

            // 3. Validasi & Kunci Record Pencatatan Kematian Ayam
            $recordsMutasi = PencatatanKematianAyam::with('ayam.kandang')
                ->whereDate('tanggal', $tanggal)
                ->get();

            foreach ($recordsMutasi as $mutasi) {
                $mutasi->update([
                    'is_validated' => true,
                    'validated_by' => $userId,
                    'validated_at' => now(),
                ]);

                // 4. Catat Audit Log Kematian (jika tabel log_kematian_ayams ada)
                $this->catatLogKematianFisik($mutasi, $userId);

                // 5. Cek jika populasi ayam pada batch/kandang tersebut telah habis (0 Ekor)
                $ayam = $mutasi->ayam;
                if ($ayam && $ayam->jumlah_saat_ini <= 0) {
                    $this->handleKandangKosong($ayam);
                }
            }

            Log::info("[PostingMutasiAyamService] Berhasil memposting Jurnal Mutasi & menyinkronkan stok tanggal {$tanggal}.");
        });
    }

    /* STREAMING_CHUNK:Recording physical death log entries for audit trail */
    private function catatLogKematianFisik(PencatatanKematianAyam $mutasi, int $userId): void
    {
        if (!class_exists(LogKematianAyam::class)) {
            return;
        }

        $ayam = $mutasi->ayam;
        if (!$ayam) return;

        // Hindari duplikasi log pada tanggal dan ayam yang sama
        LogKematianAyam::updateOrCreate(
            [
                'id_ayam' => $ayam->id,
                'tanggal' => $mutasi->tanggal,
            ],
            [
                'id_kandang'   => $ayam->id_kandang,
                'jumlah_mati'  => $mutasi->jumlah_mati,
                'jumlah_afkir' => $mutasi->jumlah_afkir,
                'populasi_awal' => $ayam->jumlah_awal,
                'populasi_sisa' => $ayam->jumlah_saat_ini,
                'created_by'   => $userId,
            ]
        );
    }

    /* STREAMING_CHUNK:Handling empty cage status update when population reaches zero */
    private function handleKandangKosong(Ayam $ayam): void
    {
        if (!$ayam->id_kandang) return;

        $kandang = Kandang::find($ayam->id_kandang);
        if (!$kandang) return;

        // Cek apakah masih ada batch lain yang hidup di kandang ini
        $sisaAyamLain = Ayam::where('id_kandang', $kandang->id)
            ->where('id', '!=', $ayam->id)
            ->get()
            ->sum(fn($a) => $a->jumlah_saat_ini);

        if ($sisaAyamLain <= 0) {
            // Tandai kandang tidak aktif/kosong
            $kandang->update([
                'is_aktif' => false,
                'keterangan' => "Kandang kosong (Ayam habis terinfeksi/afkir per " . now()->format('d/m/Y') . ")",
            ]);
        }
    }
}
