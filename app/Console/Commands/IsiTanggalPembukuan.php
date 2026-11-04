<?php

namespace App\Console\Commands;

use App\Models\Penjualan;
use App\Models\SatpamSetoran;
use App\Services\TanggalPembukuanService;
use Illuminate\Console\Command;

/**
 * Mengisi tanggal pembukuan untuk setoran yang SUDAH ditutup sebelum fitur tanggal pembukuan dipasang,
 * supaya Preview Penjualan dan laporan membacanya per tanggal tutup buku.
 *
 * Default: hanya penjualans.tanggal_pembukuan yang diisi. Jurnal TIDAK disentuh.
 * Tambahkan --jurnal kalau tanggal jurnalnya juga mau disamakan (jangan dipakai untuk periode
 * yang jurnalnya sudah dikoreksi admin).
 */
class IsiTanggalPembukuan extends Command
{
    protected $signature = 'satpam:isi-tanggal-pembukuan
        {--sejak= : Setoran yang ditutup pada/sesudah tanggal ini, format YYYY-MM-DD (wajib)}
        {--sampai= : Setoran yang ditutup pada/sebelum tanggal ini (opsional)}
        {--jurnal : Samakan juga tanggal jurnal (jurnal pembantu & jurnal umum)}
        {--dry-run : Hanya tampilkan, tidak mengubah apa pun}';

    protected $description = 'Isi tanggal pembukuan nota untuk setoran satpam yang sudah ditutup sebelumnya.';

    public function handle(TanggalPembukuanService $service): int
    {
        $sejak = $this->option('sejak');
        if (! $sejak) {
            $this->error('Wajib mengisi --sejak=YYYY-MM-DD, contoh: --sejak=2026-11-01');

            return self::FAILURE;
        }

        $setorans = SatpamSetoran::query()
            ->where('status', 'SELESAI')
            ->whereDate('ditutup_pada', '>=', $sejak)
            ->when($this->option('sampai'), fn ($q, $d) => $q->whereDate('ditutup_pada', '<=', $d))
            ->orderBy('ditutup_pada')
            ->get();

        $rencana = [];
        $totalNota = 0;

        foreach ($setorans as $s) {
            $ids = Penjualan::where('satpam_setoran_id', $s->id)
                ->whereNull('tanggal_pembukuan')
                ->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            $jumlah = (float) Penjualan::whereIn('id', $ids)->sum('total');
            $totalNota += $ids->count();
            $rencana[] = [$s, $ids];

            $this->line(sprintf(
                '#%d %-7s ditutup %s : %d nota, Rp %s',
                $s->id,
                $s->pabrik,
                $s->ditutup_pada?->format('d/m/Y H:i'),
                $ids->count(),
                number_format($jumlah, 0, ',', '.')
            ));
        }

        if ($rencana === []) {
            $this->info('Tidak ada nota yang perlu diisi.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info($totalNota . ' nota pada ' . count($rencana) . ' setoran akan diisi tanggal pembukuannya'
            . ($this->option('jurnal') ? ' (jurnal ikut dikoreksi).' : ' (jurnal tidak disentuh).'));

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Lanjutkan?')) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        foreach ($rencana as [$s, $ids]) {
            $service->terapkan($ids, $s->ditutup_pada, (bool) $this->option('jurnal'));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}