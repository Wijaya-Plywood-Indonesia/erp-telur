<?php

namespace App\Services;

use App\Models\AkunGroup;
use App\Models\JurnalUmum;
use App\Models\SubAnakAkun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan laba/rugi untuk SATU rentang tanggal.
 *
 * Aturannya disalin dari halaman "Laba Rugi Telur" (App\Filament\Pages\LabaRugiTelur)
 * supaya angkanya sama persis:
 *   - struktur akun dari grup "Laba Rugi" -> bagian ber-tipe
 *     (pendapatan, retur_potongan, hpp, beban_produksi, beban_usaha, pendapatan_lain, beban_lain)
 *   - nilai akun = Σ (banyak × harga) dari Jurnal Umum (hanya jurnal yang sudah DIPOSTING),
 *     bertanda menurut saldo normal akun
 *   - laba_bersih = pendapatan − retur − (HPP + beban produksi) − beban usaha
 *                   + pendapatan lain − beban lain
 */
class LabaRugiRingkasanService
{
    /** tipe => daftar kode akun (boleh berulang, persis seperti halaman Laba Rugi) */
    private ?array $petaTipe = null;

    /** kode akun => true bila saldo normal kredit */
    private ?array $kreditNormal = null;

    public function hitung(Carbon $dari, Carbon $sampai): array
    {
        $peta = $this->petaTipe();

        $semuaKode = [];
        foreach ($peta as $daftar) {
            foreach ($daftar as $kode) {
                $semuaKode[$kode] = true;
            }
        }

        $saldo = [];
        if ($semuaKode) {
            $rows = JurnalUmum::query()
                ->whereBetween('tgl', [$dari->format('Y-m-d'), $sampai->format('Y-m-d')])
                ->whereIn('no_akun', array_keys($semuaKode))
                ->selectRaw('no_akun, LOWER(map) as m, SUM(COALESCE(banyak, 1) * COALESCE(harga, 0)) as nilai')
                ->groupBy('no_akun', DB::raw('LOWER(map)'))
                ->get();

            foreach ($rows as $r) {
                $kode    = (string) $r->no_akun;
                $nilai   = (float) $r->nilai;
                $isDebit = $r->m === 'd';

                $saldo[$kode] = ($saldo[$kode] ?? 0.0)
                    + (($this->kreditNormal[$kode] ?? false)
                        ? ($isDebit ? -$nilai : $nilai)
                        : ($isDebit ? $nilai : -$nilai));
            }
        }

        $t = [];
        foreach (['pendapatan', 'retur_potongan', 'hpp', 'beban_produksi', 'beban_usaha', 'pendapatan_lain', 'beban_lain'] as $tipe) {
            $t[$tipe] = 0.0;
            foreach ($peta[$tipe] ?? [] as $kode) {
                $t[$tipe] += $saldo[$kode] ?? 0.0;
            }
        }

        $pendapatan = $t['pendapatan'] - $t['retur_potongan'];
        $hpp        = $t['hpp'];
        $beban      = $t['beban_produksi'] + $t['beban_usaha'] + $t['beban_lain'];
        $lain       = $t['pendapatan_lain'];

        return [
            'pendapatan'      => round($pendapatan),
            'pendapatan_lain' => round($lain),
            'hpp'             => round($hpp),
            'beban'           => round($beban),
            'laba_bersih'     => round($pendapatan + $lain - $hpp - $beban),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════

    private function petaTipe(): array
    {
        if ($this->petaTipe !== null) {
            return $this->petaTipe;
        }

        $this->kreditNormal = [];
        foreach (SubAnakAkun::pluck('saldo_normal', 'kode_sub_anak_akun') as $kode => $normal) {
            $this->kreditNormal[(string) $kode] = in_array(strtolower((string) $normal), ['kredit', 'credit', 'k'], true);
        }

        $peta = [];

        $root = AkunGroup::whereNull('parent_id')
            ->whereRaw('LOWER(nama) LIKE ?', ['%laba rugi%'])
            ->first();

        if ($root) {
            $groups = AkunGroup::where('parent_id', $root->id)
                ->visible()
                ->ordered()
                ->with([
                    'subAnakAkuns.anakAkun:id,kode_anak_akun,nama_anak_akun',
                    'childrenRecursive.subAnakAkuns.anakAkun:id,kode_anak_akun,nama_anak_akun',
                    'childrenRecursive.anakAkuns.subAnakAkuns',
                    'anakAkuns.subAnakAkuns',
                ])
                ->get();

            foreach ($groups as $group) {
                $tipe = $group->tipe ?? 'lainnya';
                $this->kumpulkan($group, $tipe, $peta);
            }
        }

        return $this->petaTipe = $peta;
    }

    /** Kumpulkan kode akun di bawah sebuah grup (rekursif), sama seperti buildGroupNode(). */
    private function kumpulkan(AkunGroup $group, string $tipe, array &$peta): void
    {
        // sub anak akun langsung di grup (hanya yang punya anak akun, seperti di halaman Laba Rugi)
        foreach ($group->subAnakAkuns as $sub) {
            if (!$sub->anakAkun) {
                continue;
            }
            $peta[$tipe][] = (string) $sub->kode_sub_anak_akun;
        }

        // grup anak
        foreach ($group->childrenRecursive as $child) {
            $this->kumpulkan($child, $tipe, $peta);
        }

        // anak akun (pivot lama)
        if ($group->relationLoaded('anakAkuns')) {
            foreach ($group->anakAkuns as $anak) {
                foreach ($anak->subAnakAkuns as $sub) {
                    $peta[$tipe][] = (string) $sub->kode_sub_anak_akun;
                }
                $peta[$tipe][] = (string) $anak->kode_anak_akun;
            }
        }
    }
}