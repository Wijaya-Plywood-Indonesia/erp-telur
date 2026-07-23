<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\JurnalPembantuHeader;
use App\Models\JurnalPembantuItem;
use App\Models\JurnalUmum;
use App\Models\PencatatanKematianAyam;
use App\Models\SubAnakAkun;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MutasiAyamService
{
    /**
     * Memproses dan membuat jurnal beban kematian ayam menggunakan harga_beli (HPP) dari Master Barang
     */
    /* STREAMING_CHUNK:Processing daily mortality journal only */
    public function prosesJurnalKematian(string $tanggal, int $userId): void
    {
        DB::transaction(function () use ($tanggal, $userId) {
            $this->buatJurnalAyamMati($tanggal, $userId);
        });
    }

    /**
     * Backward compatibility method wrapper
     */
    public function prosesJurnalKematianDanAfkir(
        string $tanggal,
        int $userId,
        string $noAkunKas = '1121-00',
        float $totalPenerimaanKasAfkir = 0
    ): void {
        $this->prosesJurnalKematian($tanggal, $userId);
    }

    /**
     * JURNAL: AYAM MATI
     * Debet  : 5400-02 (BEBAN KEMATIAN AYAM)
     * Kredit : 1420-XX (Persediaan Ayam KD X - Sebesar harga_beli Barang)
     */
    /* STREAMING_CHUNK:Building mortality journal entries querying Barang via kode_barang & id_sub_anak_akun */
    public function buatJurnalAyamMati(string $tanggal, int $userId): void
    {
        $tglFormatted = Carbon::parse($tanggal)->format('d/m');
        $nota = "MATI-AYAM-{$tanggal}";

        // Bersihkan draft jurnal mati lama jika ada
        $this->hapusJurnalLama($nota);

        $records = PencatatanKematianAyam::with(['ayam.subAnakAkun', 'ayam.kandang'])
            ->whereDate('tanggal', $tanggal)
            ->where('jumlah_mati', '>', 0)
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        $totalRupiahBeban = 0;
        $kreditItems = [];

        foreach ($records as $row) {
            $ayam = $row->ayam;
            if (!$ayam) continue;

            $ekorMati = (int) $row->jumlah_mati;

            $subAkun = $ayam->subAnakAkun;
            $kodeAkunKredit = $subAkun?->kode_sub_anak_akun ?? '1420-00';
            $cleanBatchName = trim(preg_replace('/\s*\(\d+[^)]*\)/i', '', $ayam->nama_batch));

            // Query Master Barang menggunakan nama kolom yang tepat
            $barang = Barang::query()
                ->when($ayam->id_sub_anak_akun, fn($q) => $q->where('id_sub_anak_akun', $ayam->id_sub_anak_akun))
                ->orWhere('kode_barang', $cleanBatchName)
                ->orWhere('nama_barang', $cleanBatchName)
                ->orWhere('kode_barang', 'LIKE', "%{$cleanBatchName}%")
                ->orWhere('nama_barang', 'LIKE', "%{$cleanBatchName}%")
                ->first();

            // HPP diambil dari harga_beli pada Master Barang
            $hppPerEkor = (float) ($barang?->harga_beli ?? $ayam->hpp ?? 15000);
            $totalNilaiRupiah = $ekorMati * $hppPerEkor;

            $totalRupiahBeban += $totalNilaiRupiah;

            $mingguRealtime = intdiv($ayam->umur_hari, 7);
            $namaAkunDenganUmur = "{$cleanBatchName} ({$mingguRealtime} mggu)";

            $kreditItems[] = [
                'no_akun'     => $kodeAkunKredit,
                'nama_akun'   => $namaAkunDenganUmur,
                'nama_barang' => $namaAkunDenganUmur,
                'banyak'      => $ekorMati,
                'harga'       => $hppPerEkor,
                'total_nilai' => $totalNilaiRupiah,
            ];
        }

        if ($totalRupiahBeban <= 0) return;

        $nextJurnal = $this->generateNextJurnalNumber();

        // ── A. DEBET: BEBAN KEMATIAN AYAM (5400-02) ──
        $bebanSubAkun  = SubAnakAkun::where('kode_sub_anak_akun', '5400-02')->first();
        $namaBebanAkun = $bebanSubAkun?->nama_sub_anak_akun ?? 'BEBAN KEMATIAN AYAM';

        $this->createJurnalEntry($nextJurnal, $tanggal, $nota, "{$namaBebanAkun} {$tglFormatted}", '5400-02', $namaBebanAkun, 'd', $userId, [
            'nama_barang' => $namaBebanAkun,
            'keterangan'  => "{$namaBebanAkun} {$tglFormatted}",
            'banyak'      => 1,
            'harga'       => $totalRupiahBeban,
        ]);

        // ── B. KREDIT: Persediaan Masing-masing Ayam KD X (1420-XX) ──
        $urut = 1;
        foreach ($kreditItems as $kredit) {
            $this->createJurnalEntry($nextJurnal, $tanggal, $nota, "{$kredit['no_akun']} Ayam Mati {$tglFormatted}", $kredit['no_akun'], $kredit['nama_akun'], 'k', $userId, [
                'nama_barang' => $kredit['nama_barang'],
                'keterangan'  => "Ayam Mati {$tglFormatted}",
                'banyak'      => $kredit['banyak'],
                'harga'       => $kredit['harga'],
                'urut'        => $urut++,
            ]);
        }

        Log::info("[MutasiAyamService] Jurnal Ayam Mati diterbitkan. Total: Rp {$totalRupiahBeban}");
    }

    /* STREAMING_CHUNK:Deleting previous unposted journal entries */
    public function hapusJurnalLama(string $nota): void
    {
        $isPosted = JurnalPembantuHeader::where('modul_asal', 'kematian_ayam')
            ->where('no_dokumen', $nota)
            ->where('status', JurnalPembantuHeader::STATUS_DIPOSTING)
            ->exists();

        if ($isPosted) {
            throw new Exception("Jurnal tanggal ini ({$nota}) sudah diposting ke Buku Besar!");
        }

        $headers = JurnalPembantuHeader::where('modul_asal', 'kematian_ayam')
            ->where('no_dokumen', $nota)
            ->get();

        foreach ($headers as $header) {
            $header->items()->delete();
            $header->delete();
        }
    }

    private function createJurnalEntry(
        int $nextJurnal,
        string $tgl,
        string $nota,
        string $ket,
        string $noAkun,
        string $namaAkun,
        string $map,
        int $userId,
        array $itemData
    ): void {
        $nextNoJP = (int) (JurnalPembantuHeader::lockForUpdate()->max('no_jurnal_pembantu') ?? 0) + 1;

        $header = JurnalPembantuHeader::create([
            'no_jurnal_pembantu' => $nextNoJP,
            'tgl_transaksi'      => $tgl,
            'jenis_transaksi'    => 'pk',
            'modul_asal'         => 'kematian_ayam',
            'jurnal'             => $nextJurnal,
            'no_akun'            => $noAkun,
            'nama_akun'          => $namaAkun,
            'map'                => $map,
            'keterangan'         => $ket,
            'no_dokumen'         => $nota,
            'status'             => JurnalPembantuHeader::STATUS_DRAFT,
            'dibuat_oleh'        => $userId,
        ]);

        JurnalPembantuItem::create([
            'jurnal_pembantu_header_id' => $header->id,
            'urut'                      => $itemData['urut'] ?? 1,
            'nama_barang'               => $itemData['nama_barang'],
            'no_dokumen'                => $nota,
            'keterangan'                => $itemData['keterangan'],
            'banyak'                    => $itemData['banyak'],
            'harga'                     => $itemData['harga'],
            'status'                    => true,
            'created_by'                => $userId,
            'updated_by'                => $userId,
        ]);
    }

    private function generateNextJurnalNumber(): int
    {
        $maxJP = (int) (JurnalPembantuHeader::lockForUpdate()->max('jurnal') ?? 0);
        $maxJU = (int) (JurnalUmum::lockForUpdate()->max('jurnal') ?? 0);

        return max($maxJP, $maxJU) + 1;
    }
}
