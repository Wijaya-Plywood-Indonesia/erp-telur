<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\Satpam;
use App\Models\SatpamPerangkat;
use App\Models\SatpamSetoran;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SatpamTelurController extends Controller
{
    /**
     * GET /api/satpam/harga   (dipertahankan untuk aplikasi versi lama)
     * Harga terkini jenis telur milik pabrik pemilik kunci API.
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
     * POST /api/satpam/aktivasi
     * Body: kode_akses. Dipanggil sekali saat pertama install.
     * Mengembalikan token yang disimpan di HP dan dikirim lewat header X-Satpam-Token.
     * Satu kode bisa dipakai di beberapa HP sampai batas maks_perangkat (0 = tanpa batas).
     */
    public function aktivasi(Request $request)
    {
        $kode = $request->attributes->get('satpam_pabrik');

        $v = $request->validate([
            'kode_akses' => ['required', 'string', 'max:20'],
        ]);

        $satpam = Satpam::where('pabrik', $kode)
            ->where('aktif', true)
            ->where('kode_akses', trim($v['kode_akses']))
            ->first();

        if (! $satpam) {
            $this->fail('Kode tidak dikenal', 422);
        }

        if (! $satpam->bolehTambahPerangkat()) {
            $this->fail('Batas jumlah HP untuk kode ini sudah tercapai. Hubungi admin.', 422);
        }

        $token = Str::random(60);

        $satpam->perangkats()->create([
            'token_hash'       => hash('sha256', $token),
            'aktivasi_pada'    => now(),
            'terakhir_dipakai' => now(),
        ]);

        return response()->json([
            'token' => $token,
            'nama'  => $satpam->nama,
        ]);
    }

    /**
     * GET /api/satpam/beranda
     * Satu panggilan untuk layar utama: harga + stok tersedia + status setoran.
     */
    public function beranda(Request $request)
    {
        $cfg = $this->pabrik($request);
        $satpam = $this->satpamWajib($request);

        $map = array_filter($cfg['barang'] ?? []);              // jenis tanpa barang (mis. bentes Wijaya) tidak tampil
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
                'stok'   => $this->stokTersedia($b),
            ];
        }

        $dibuka = $satpam->setoranDibuka();
        $totalDibuka = null;
        $notaDibuka = null;
        if ($dibuka) {
            $q = $satpam->penjualanTerbukaPabrikIni()->where('id', '<=', $dibuka->sampai_penjualan_id);
            $totalDibuka = round((float) (clone $q)->sum('total'), 2);
            $notaDibuka  = (int) (clone $q)->count();
        }

        return response()->json([
            'satpam'      => ['nama' => $satpam->nama],
            'data'        => $data,
            'setoran'     => [
                'total'              => $satpam->totalPabrik(),
                'jumlah_nota'        => $satpam->jumlahNotaPabrik(),
                'dibuka'             => $dibuka !== null,
                'total_dibuka'       => $totalDibuka,
                'jumlah_nota_dibuka' => $notaDibuka,
            ],
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

        // Token wajib kalau SATPAM_WAJIB_TOKEN=true. Kalau tidak, aplikasi lama tetap bisa
        // jual (nota tanpa satpam). Token yang dikirim tapi tidak valid selalu ditolak.
        $satpam = config('satpam.wajib_token')
            ? $this->satpamWajib($request)
            : $this->satpamOpsional($request);

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

        $nota = DB::transaction(function () use ($v, $barangId, $user, $qty, $cfg, $satpam) {
            $barang = Barang::with('satuan')->lockForUpdate()->find($barangId);

            if (! $barang || ! $barang->is_active) {
                $this->fail('Barang tidak ditemukan / tidak aktif', 422);
            }

            $harga = (float) $barang->harga_jual;

            if (abs($harga - (float) $v['harga_ditampilkan']) > 0.009) {
                $this->fail('Harga berubah, cek ulang lalu cetak lagi', 409, ['harga_baru' => $harga]);
            }

            // Stok tersedia = stok buku besar dikurangi nota yang belum divalidasi
            // (nota yang belum divalidasi belum memotong stok buku besar).
            if (config('satpam.cek_stok') && $this->stokTersedia($barang) < $qty) {
                $this->fail("Stok {$barang->nama_barang} tidak mencukupi", 422);
            }

            $total = round($qty * $harga, 2);

            $penjualan = $this->buatNotaDenganRetry([
                'tanggal'            => now(),
                'nama_customer'      => $v['nama_customer']
                    ?? ($cfg['nama_customer'] ?? config('satpam.nama_customer_default')),
                'is_member'          => false,
                'metode_pembayaran'  => 'TUNAI',
                'total'              => $total,
                'bayar'              => $total,
                'bayar_tunai'        => $total,
                'bayar_transfer'     => 0,
                'kembalian'          => 0,
                'keterangan'         => $satpam
                    ? 'Dibuat dari aplikasi satpam: ' . $satpam->nama
                    : 'Dibuat dari aplikasi satpam',
                'user_id'            => $user->id,
                'satpam_id'          => $satpam?->id,
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
                'kasir'       => $satpam?->nama ?? $user->name,
                'nama_barang' => config("satpam.label.{$v['jenis']}", $barang->nama_barang),
                'satuan'      => $barang->satuan?->nama_satuan ?? '',
                'qty'         => $qty,
                'harga'       => $harga,
                'total'       => $total,
            ];
        });

        return response()->json($nota, 201);
    }

    /**
     * POST /api/satpam/tutup-buku
     * Body: total_konfirmasi (angka yang dilihat petugas ruko di layar).
     * Hanya berhasil kalau kantor sudah membuka kuncinya di ERP.
     */
    public function tutupBuku(Request $request)
    {
        $satpam = $this->satpamWajib($request);

        $v = $request->validate([
            'total_konfirmasi' => ['required', 'numeric'],
        ]);

        $hasil = DB::transaction(function () use ($satpam, $v) {
            $setoran = SatpamSetoran::where('pabrik', $satpam->pabrik)
                ->where('status', 'DIBUKA')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $setoran) {
                $this->fail('Tutup buku belum dibuka oleh kantor, atau sudah ditutup', 403);
            }

            $q = $satpam->penjualanTerbukaPabrikIni()->where('id', '<=', $setoran->sampai_penjualan_id);
            $total = round((float) (clone $q)->sum('total'), 2);

            if (abs($total - (float) $v['total_konfirmasi']) > 0.009) {
                $this->fail('Total berubah, periksa lagi', 409, ['total_baru' => $total]);
            }

            $ids = (clone $q)->pluck('id');
            Penjualan::whereIn('id', $ids)->update(['satpam_setoran_id' => $setoran->id]);

            $setoran->update([
                'status'            => 'SELESAI',
                'total'             => $total,
                'jumlah_nota'       => $ids->count(),
                'ditutup_pada'      => now(),
                'ditutup_satpam_id' => $satpam->id,
            ]);

            return ['total' => $total, 'jumlah_nota' => $ids->count()];
        });

        return response()->json(['message' => 'Tutup buku berhasil'] + $hasil);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

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

    /** Satpam dari header X-Satpam-Token. Null kalau header tidak ada. 401 kalau token tidak valid. */
    private function satpamOpsional(Request $request): ?Satpam
    {
        $token = $request->header('X-Satpam-Token');
        if (! $token) {
            return null;
        }

        $perangkat = SatpamPerangkat::with('satpam')
            ->where('token_hash', hash('sha256', $token))
            ->whereHas('satpam', function ($q) use ($request) {
                $q->where('pabrik', $request->attributes->get('satpam_pabrik'))
                    ->where('aktif', true);
            })
            ->first();
        $satpam = $perangkat?->satpam;

        if (! $satpam) {
            $this->fail('Perangkat belum diaktifkan', 401, ['kode' => 'belum_aktif']);
        }

        // Catat kapan HP ini terakhir dipakai (paling sering sekali per menit).
        if (! $perangkat->terakhir_dipakai || $perangkat->terakhir_dipakai->lt(now()->subMinute())) {
            $perangkat->forceFill(['terakhir_dipakai' => now()])->save();
        }

        return $satpam;
    }

    private function satpamWajib(Request $request): Satpam
    {
        $satpam = $this->satpamOpsional($request);

        if (! $satpam) {
            $this->fail('Perangkat belum diaktifkan', 401, ['kode' => 'belum_aktif']);
        }

        return $satpam;
    }

    /**
     * Stok yang boleh dijual: stok buku besar dikurangi qty nota yang belum divalidasi
     * (dan tidak dibatalkan). Nota satpam baru memotong stok buku besar setelah divalidasi LUNAS.
     */
    private function stokTersedia(Barang $barang): float
    {
        $pending = (float) DetailPenjualan::where('barang_id', $barang->id)
            ->whereHas('penjualan', function ($q) {
                $q->whereNull('validated_by')
                    ->where(function ($w) {
                        $w->whereNull('status_transaksi')
                            ->orWhere('status_transaksi', '!=', 'DIBATALKAN');
                    });
            })
            ->sum('qty');

        return round((float) $barang->stok_buku_besar - $pending, 2);
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