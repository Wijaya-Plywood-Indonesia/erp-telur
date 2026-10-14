<?php

namespace App\Http\Controllers;

use App\Models\PengajuanMutasiTelur;
use App\Services\MutasiTelurService;

class MutasiTelurPrintController extends Controller
{
    /** Satuan per jenis telur pada bukti mutasi. */
    private const SATUAN = [
        'petian' => 'Peti',
        'kiloan' => 'Kg',
        'bentes' => 'Kg',
    ];

    public function print(PengajuanMutasiTelur $pengajuan)
    {
        abort_if(
            in_array($pengajuan->status, [PengajuanMutasiTelur::DITOLAK, PengajuanMutasiTelur::DIBATALKAN]),
            404,
            'Mutasi sudah ditolak / dibatalkan.'
        );

        $pengajuan->load(['creator', 'validator']);

        $items = collect($pengajuan->items)->map(fn ($i) => [
            'label'  => MutasiTelurService::JENIS[$i['jenis']] ?? $i['jenis'],
            'qty'    => (float) $i['qty'],
            'satuan' => self::SATUAN[$i['jenis']] ?? 'Kg',
        ])->all();

        $tujuan = MutasiTelurService::TUJUAN[$pengajuan->tujuan] ?? $pengajuan->tujuan;

        return view('filament.pages.cetak-thermal', compact('pengajuan', 'items', 'tujuan'));
    }
}