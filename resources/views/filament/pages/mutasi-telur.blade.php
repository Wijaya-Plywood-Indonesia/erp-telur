<x-filament-panels::page>
    @php($riwayat = $this->getRiwayat())

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-white/5 text-left">
                <tr>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">No. Dokumen</th>
                    <th class="px-4 py-2">Rincian (masuk ke tujuan)</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Keterangan</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($riwayat as $row)
                    <tr class="border-t border-gray-200 dark:border-white/10 {{ $row['batal'] ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['tanggal'] }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ $row['nota'] }}
                            @if ($row['batal'])
                                <span class="ml-1 text-xs text-danger-600">(dibatalkan)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @foreach ($row['rincian'] as $r)
                                <div>{{ $r['akun'] }}: <strong>{{ rtrim(rtrim(number_format($r['qty'], 2, ',', '.'), '0'), ',') }}</strong></div>
                            @endforeach
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['status'] === 'draft' ? 'Draft' : 'Diposting' }}</td>
                        <td class="px-4 py-2">{{ $row['keterangan'] }}</td>
                        <td class="px-4 py-2 text-right">
                            @unless ($row['batal'])
                                <x-filament::button
                                    size="xs" color="danger" outlined
                                    wire:click="batalMutasi('{{ $row['nota'] }}')"
                                    wire:confirm="{{ $row['status'] === 'draft' ? 'Hapus draft mutasi ' : 'Batalkan mutasi ' }}{{ $row['nota'] }}?">
                                    {{ $row['status'] === 'draft' ? 'Hapus Draft' : 'Batalkan' }}
                                </x-filament::button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada mutasi telur.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>