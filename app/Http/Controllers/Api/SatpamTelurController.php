<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SatpamTelurController extends Controller
{
    /**
     * GET /api/satpam/harga
     * Harga terkini 3 jenis telur milik pabrik pemilik kunci API.
     */
    public function harga(Request $request)
    {
        $cfg = $this->pabrik($request);

        $map = array_filter($cfg['barang'] ?? []);              // kode => barang_id
        $barangs = Barang::with('satuan')
            ->whereIn('id', array_values($map))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $data = [];
        foreach ($map as $kode => $id) {
            $b = $barangs->get((int) $id);
            if (! $b) {
                continue;
            }
            $data[] = [
                'kode'   => $kode,
                'nama'   => config("satpam.label.$kode", $b->nama_barang),
                'satuan' => $b->satuan?->nama_satuan ?? '',
                'harga'  => (float) $b->harga_jual,
            ];
        }

        return response()->json([
            'toko'        => $this->userPos($cfg)?->tokoUtama?->toko?->nama_toko,
            'data'        => $data,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/satpam/nota
     * Body: jenis (petian|kiloan|bentes), qty, harga_ditampilkan, nama_customer?
     *
     * Harga & total dihitung di server. Kalau harga di layar HP beda dengan harga
     * sekarang, balas 409 + harga_baru supaya satpam cek ulang sebelum cetak.
     * Nota dibuat berstatus BELUM DIBAYAR; jurnal & stok terbentuk saat admin
     * memvalidasi LUNAS di Filament (alur yang sama seperti POS).
     */
    public function store(Request $request)
    {
        $cfg = $this->pabrik($request);

        $v = $request->validate([
            'jenis'             => ['required', Rule::in(array_keys(config('satpam.label')))],
            'qty'               => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'harga_ditampilkan' => ['required', 'numeric'],
            'nama_customer'     => ['nullable', 'string', 'max:100'],
        ]);

        $barangId = $cfg['barang'][$v['jenis']] ?? null;
        if (! $barangId) {
            return response()->json(['message' => 'Jenis telur belum dikonfigurasi di server'], 422);
        }

        $user = $this->userPos($cfg);
        if (! $user) {
            return response()->json(['message' => 'User pos untuk pabrik ini belum diisi di server'], 422);
        }

        $qty = round((float) $v['qty'], 2);

        $nota = DB::transaction(function () use ($v, $barangId, $user, $qty) {
            $barang = Barang::with('satuan')->lockForUpdate()->find($barangId);

            if (! $barang || ! $barang->is_active) {
                $this->fail('Barang tidak ditemukan / tidak aktif', 422);
            }

            $harga = (float) $barang->harga_jual;

            if (abs($harga - (float) $v['harga_ditampilkan']) > 0.009) {
                $this->fail('Harga berubah, cek ulang lalu cetak lagi', 409, ['harga_baru' => $harga]);
            }

            if (config('satpam.cek_stok') && (float) $barang->stok_buku_besar < $qty) {
                $this->fail("Stok {$barang->nama_barang} tidak mencukupi", 422);
            }

            $total = round($qty * $harga, 2);

            $penjualan = $this->buatNotaDenganRetry([
                'tanggal'            => now(),
                'nama_customer'      => $v['nama_customer'] ?? config('satpam.nama_customer_default'),
                'is_member'          => false,
                'metode_pembayaran'  => 'TUNAI',
                'total'              => $total,
                'bayar'              => $total,
                'bayar_tunai'        => $total,
                'bayar_transfer'     => 0,
                'kembalian'          => 0,
                'keterangan'         => 'Dibuat dari aplikasi satpam',
                'user_id'            => $user->id,
            ]);

            DetailPenjualan::create([
                'penjualan_id' => $penjualan->id,
                'barang_id'    => $barang->id,
                'nama_barang'  => $barang->nama_barang,
                'satuan'       => $barang->satuan?->nama_satuan,
                'qty'          => $qty,
                'harga_awal'   => $harga,
                'harga_jual'   => $harga,
                'potongan'     => 0,
                'subtotal'     => $total,
            ]);

            return [
                'no_nota'     => $penjualan->no_nota,
                'tanggal'     => $penjualan->tanggal->format('d/m/Y H:i'),
                'toko'        => $user->tokoUtama?->toko?->nama_toko,
                'kasir'       => $user->name,
                'nama_barang' => config("satpam.label.{$v['jenis']}", $barang->nama_barang),
                'satuan'      => $barang->satuan?->nama_satuan ?? '',
                'qty'         => $qty,
                'harga'       => $harga,
                'total'       => $total,
            ];
        });

        return response()->json($nota, 201);
    }

    /** Konfigurasi pabrik yang dipilih middleware berdasarkan X-Api-Key. */
    private function pabrik(Request $request): array
    {
        $kode = $request->attributes->get('satpam_pabrik');

        return (array) config("satpam.pabrik.$kode", []);
    }

    private function userPos(array $cfg): ?User
    {
        $id = $cfg['user_id'] ?? null;

        return $id ? User::with('tokoUtama.toko')->find($id) : null;
    }

    /** Nomor nota: format sama dengan POS Filament (INV-YYYYMMDD...), jadi satu deret. */
    private function nomorNota(): string
    {
        $prefix = 'INV-' . now()->format('Ymd');

        $last = Penjualan::where('no_nota', 'LIKE', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $last) {
            return $prefix . now()->format('His');
        }

        $suffix = substr($last->no_nota, strlen($prefix));
        if (! ctype_digit($suffix)) {
            return $prefix . now()->format('His');
        }

        return $prefix . str_pad((int) $suffix + 1, strlen($suffix), '0', STR_PAD_LEFT);
    }

    private function buatNotaDenganRetry(array $attrs): Penjualan
    {
        // no_nota unique; kalau bentrok dengan POS yang jalan bersamaan, hitung ulang.
        for ($i = 0; $i < 3; $i++) {
            try {
                return Penjualan::create($attrs + ['no_nota' => $this->nomorNota()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($i === 2) {
                    throw $e;
                }
            }
        }
    }

    private function fail(string $message, int $status, array $extra = []): never
    {
        throw new HttpResponseException(
            response()->json(['message' => $message] + $extra, $status)
        );
    }
}