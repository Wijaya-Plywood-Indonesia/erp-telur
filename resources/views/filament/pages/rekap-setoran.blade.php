<x-filament-panels::page>
    @php
        $d = $this->data();
        $fmt = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $tgl = fn ($x) => $x ? \Carbon\Carbon::parse($x)->translatedFormat('d M Y H:i') : '-';
        $rentang = function ($a, $b) {
            if (! $a) return '';
            $a = \Carbon\Carbon::parse($a); $b = \Carbon\Carbon::parse($b);
            return $a->isSameDay($b)
                ? $a->translatedFormat('d M Y')
                : $a->translatedFormat('d M') . ' – ' . $b->translatedFormat('d M Y');
        };
        $garis = 'color-mix(in srgb, currentColor 12%, transparent)';
        $kotak = "border:1px solid $garis;border-radius:.75rem;padding:1rem;";
        $redup = 'opacity:.6;font-size:.8rem;';
        $sel = "padding:.4rem .5rem;border-bottom:1px solid $garis;";
    @endphp

    {{-- FILTER TANGGAL --}}
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end">
        <div>
            <div style="{{ $redup }}">Ditutup dari tanggal</div>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="dari" />
            </x-filament::input.wrapper>
        </div>
        <div>
            <div style="{{ $redup }}">Sampai tanggal</div>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="sampai" />
            </x-filament::input.wrapper>
        </div>
    </div>

    {{-- WIJAYA (kiri) & WAHANA (kanan) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:1.5rem;align-items:start">
        @foreach (\App\Filament\Pages\RekapSetoran::PABRIK as $kode => $label)
            @php $r = $d['pabrik'][$kode]; @endphp
            <div style="display:flex;flex-direction:column;gap:.75rem;min-width:0">
                <h2 style="font-size:1.25rem;font-weight:700">{{ $label }}</h2>

                @forelse ($r['items'] as $c)
                    <div style="{{ $kotak }}">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem">
                            <div>
                                <div style="font-weight:600">{{ $tgl($c['ditutup_pada']) }}</div>
                                <div style="{{ $redup }}">{{ $rentang($c['awal'], $c['akhir']) }} · {{ $c['nota'] }} nota</div>
                            </div>
                            <div style="font-size:1.35rem;font-weight:700;white-space:nowrap">{{ $fmt($c['total']) }}</div>
                        </div>

                        {{-- Rekap per satpam --}}
                        <table style="width:100%;border-collapse:collapse;font-size:.9rem;margin-top:.5rem">
                            @foreach ($c['rekap'] as $s)
                                <tr>
                                    <td style="{{ $sel }}">{{ $s['nama'] }}</td>
                                    <td style="{{ $sel }};text-align:right;opacity:.6">{{ $s['nota'] }} nota</td>
                                    <td style="{{ $sel }};text-align:right;font-weight:600">{{ $fmt($s['total']) }}</td>
                                </tr>
                            @endforeach
                        </table>

                        {{-- Dropdown penjualan --}}
                        <x-filament::button size="sm" color="gray" wire:click="toggle('{{ $c['kunci'] }}')" style="margin-top:.6rem">
                            {{ ($terbuka[$c['kunci']] ?? false) ? 'Sembunyikan' : 'Lihat' }} penjualan ({{ $c['nota'] }})
                        </x-filament::button>
                        @if ($terbuka[$c['kunci']] ?? false)
                            @include('filament.pages.partials.rekap-nota', ['notas' => $this->notaUntuk($c['kunci'], $d['namaSatpam']), 'fmt' => $fmt, 'tgl' => $tgl, 'sel' => $sel])
                        @endif
                    </div>
                @empty
                    <div style="{{ $redup }}">Belum ada setoran.</div>
                @endforelse

                @if ($r['total_halaman'] > 1)
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem">
                        <x-filament::button color="gray" size="sm" :disabled="$r['halaman'] <= 1"
                            wire:click="halamanSebelumnya('{{ $kode }}')">‹ Lebih baru</x-filament::button>
                        <span style="{{ $redup }}">Halaman {{ $r['halaman'] }} dari {{ $r['total_halaman'] }} · {{ $r['jumlah'] }} setoran</span>
                        <x-filament::button color="gray" size="sm" :disabled="$r['halaman'] >= $r['total_halaman']"
                            wire:click="halamanBerikutnya('{{ $kode }}')">Lebih lama ›</x-filament::button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>