<x-filament-panels::page>
    @php($riwayat = $this->getRiwayat())

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-white/5 text-left">
                <tr>
                    <th class="px-4 py-2">Tanggal</th>
                    <th class="px-4 py-2">No. Dokumen</th>
                    <th class="px-4 py-2">Tujuan</th>
                    <th class="px-4 py-2">Rincian</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Dibuat / Divalidasi</th>
                    <th class="px-4 py-2">Keterangan</th>
                    <th class="px-4 py-2 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($riwayat as $row)
                    <tr class="border-t border-gray-200 dark:border-white/10 {{ in_array($row['status'], ['dibatalkan', 'ditolak']) ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['tanggal'] }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['nota'] }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['tujuan'] }}</td>
                        <td class="px-4 py-2">
                            @foreach ($row['rincian'] as $r)
                                <div>{{ $r['label'] }}: <strong>{{ rtrim(rtrim(number_format($r['qty'], 2, ',', '.'), '0'), ',') }}</strong></div>
                            @endforeach
                        </td>
                        <td class="px-4 py-2">
                            <span class="whitespace-nowrap font-medium">{{ $row['status_label'] }}</span>
                            @if ($row['status'] === 'ditolak' && $row['alasan_tolak'])
                                <div class="text-xs text-danger-600">{{ $row['alasan_tolak'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <div>{{ $row['pembuat'] ?? '-' }}</div>
                            @if ($row['validator'])
                                <div class="text-xs text-gray-500">{{ $row['validator'] }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $row['keterangan'] }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-2">
                                @if ($row['can_validasi'])
                                    <x-filament::button size="xs" color="success"
                                        wire:click="mountAction('validasi', { id: {{ $row['id'] }} })">
                                        Validasi
                                    </x-filament::button>
                                @endif

                                @if ($row['can_edit'])
                                    <x-filament::button size="xs" color="gray" outlined
                                        wire:click="mountAction('edit', { id: {{ $row['id'] }} })">
                                        Edit
                                    </x-filament::button>
                                @endif

                                @if ($row['can_validasi'] || $row['can_batal'])
                                    <x-filament::dropdown placement="bottom-end" teleport>
                                        <x-slot name="trigger">
                                            <x-filament::icon-button
                                                icon="heroicon-m-ellipsis-vertical"
                                                label="Aksi lainnya" />
                                        </x-slot>

                                        <x-filament::dropdown.list>
                                            @if ($row['can_validasi'])
                                                <x-filament::dropdown.list.item color="danger"
                                                    wire:click="mountAction('tolak', { id: {{ $row['id'] }} })">
                                                    Tolak
                                                </x-filament::dropdown.list.item>
                                            @endif

                                            @if ($row['can_batal'])
                                                <x-filament::dropdown.list.item color="danger"
                                                    wire:click="mountAction('batal', { id: {{ $row['id'] }} })">
                                                    Batalkan
                                                </x-filament::dropdown.list.item>
                                            @endif
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Belum ada mutasi telur.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>