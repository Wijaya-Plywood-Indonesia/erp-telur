<x-filament-panels::page>
    <!-- STREAMING_CHUNK:Rendering streamlined mortality-only UI table layout -->
    <div class="w-full space-y-4">

        <!-- CONTROL PANEL HEADER -->
        <div class="bg-white dark:bg-zinc-900 p-3 sm:p-4 border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">

            <!-- Date Input -->
            <div class="flex items-center gap-2.5">
                <x-heroicon-o-calendar class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <label class="text-xs font-black uppercase text-zinc-600 dark:text-zinc-400 tracking-wider whitespace-nowrap">TANGGAL:</label>
                <input type="date" wire:model.live="tanggal"
                    class="bg-zinc-50 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 font-bold text-xs sm:text-sm px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none rounded-none">
            </div>

            <!-- Action Buttons / Status Badges -->
            <div class="flex items-center gap-2 justify-end">
                @if($canSaveDraft)
                <button wire:click="save"
                    class="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-white font-bold text-xs uppercase tracking-wider transition rounded-none">
                    Simpan Draft
                </button>
                @endif

                @if($canValidate)
                <button wire:click="validateData"
                    class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider shadow-sm transition rounded-none flex items-center gap-1.5">
                    <x-heroicon-o-check-circle class="w-4 h-4" />
                    <span>{{ $is_validated ? 'Validasi Ulang & Jurnal' : 'Validasi & Jurnal' }}</span>
                </button>
                @endif

                @if($hasSavedData && !$is_validated && !$canValidate && !$canSaveDraft)
                <span class="px-3.5 py-1.5 bg-amber-50 dark:bg-amber-950/60 border border-amber-300 dark:border-amber-800 text-amber-700 dark:text-amber-400 font-black text-xs uppercase tracking-wider flex items-center gap-1.5">
                    <x-heroicon-o-clock class="w-4 h-4 text-amber-500" />
                    <span>Tersimpan (Menunggu Validasi)</span>
                </span>
                @endif

                @if($is_validated && !$this->isSuperAdmin())
                <span class="px-3.5 py-1.5 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 font-black text-xs uppercase tracking-wider flex items-center gap-1.5">
                    <x-heroicon-o-lock-closed class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    <span>Terkunci (Divalidasi)</span>
                </span>
                @endif
            </div>
        </div>

        <!-- MAIN DATA TABLE -->
        <!-- STREAMING_CHUNK:Rendering main table focused purely on mortality -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-md overflow-hidden w-full">

            <!-- Table Header Bar -->
            <div class="px-4 py-3 bg-zinc-100/80 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <h3 class="text-xs sm:text-sm font-black text-zinc-800 dark:text-zinc-200 uppercase tracking-wider flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-4.5 h-4.5 text-emerald-600 dark:text-emerald-400" />
                    <span>Catatan Ayam Mati</span>
                </h3>
                <span class="text-[10px] sm:text-xs text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider">
                    Pencatatan Kematian Harian Per Kandang
                </span>
            </div>

            <div class="overflow-x-auto w-full">
                <table class="w-full text-xs sm:text-sm text-left border-collapse min-w-[480px]">
                    <thead>
                        <tr class="bg-zinc-100 dark:bg-zinc-950 border-b border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200 font-black uppercase tracking-wider text-[11px] sm:text-xs">
                            <th class="p-3 border-r border-zinc-200 dark:border-zinc-800">KD (Kandang)</th>
                            <th class="p-3 border-r border-zinc-200 dark:border-zinc-800 text-right w-40 sm:w-48">Jumlah Ayam</th>
                            <th class="p-3 border-r border-zinc-200 dark:border-zinc-800 text-center w-36 sm:w-44">Mati</th>
                            <th class="p-3 text-right w-40 sm:w-48 text-emerald-600 dark:text-emerald-400">Sisa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800/80 font-bold">
                        @forelse($listAyam as $item)
                        @php
                        $id = $item['id'];
                        $matiVal = (int) ($gridData[$id]['mati'] ?? 0);
                        $sisaVal = max(0, $item['populasi_awal'] - $matiVal);
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition">

                            <!-- Column 1: KD / Nama Kandang + Nama Batch & Usia Realtime -->
                            <td class="p-3 border-r border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-zinc-100 font-black text-xs sm:text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm sm:text-base font-extrabold">{{ $item['nama_kandang'] }}</span>
                                    <span class="text-[10px] sm:text-xs text-zinc-400 dark:text-zinc-500 font-medium">
                                        ({{ $item['nama_batch'] }}@if(!empty($item['umur_format'])) &bull; {{ $item['umur_format'] }}@endif)
                                    </span>
                                </div>
                            </td>

                            <!-- Column 2: Populasi Awal -->
                            <td class="p-3 border-r border-zinc-200 dark:border-zinc-800 text-right text-zinc-700 dark:text-zinc-300 text-xs sm:text-sm font-black">
                                {{ $item['populasi_awal'] > 0 ? number_format($item['populasi_awal'], 0, ',', '.') : '-' }}
                            </td>

                            <!-- Column 3: Input Mati -->
                            <td class="p-1.5 border-r border-zinc-200 dark:border-zinc-800 text-center">
                                <input type="text" inputmode="numeric" wire:model.live="gridData.{{ $id }}.mati" placeholder=""
                                    class="w-full text-center font-black text-zinc-900 dark:text-zinc-100 text-sm sm:text-base border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-950 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:outline-none rounded-none transition {{ !$isEditable ? 'bg-zinc-100 dark:bg-zinc-900/80 cursor-not-allowed opacity-75' : '' }}"
                                    {{ !$isEditable ? 'disabled' : '' }}>
                            </td>

                            <!-- Column 4: Sisa (Dynamic Realtime) -->
                            <td class="p-3 text-right font-black text-xs sm:text-sm text-emerald-600 dark:text-emerald-400">
                                {{ $item['populasi_awal'] > 0 ? number_format($sisaVal, 0, ',', '.') : '-' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="p-6 text-center text-zinc-500 dark:text-zinc-400 italic">Tidak ada batch kandang aktif ditemukan.</td>
                        </tr>
                        @endforelse
                    </tbody>

                    <!-- REKAP TOTAL FOOTER -->
                    <tfoot>
                        <tr class="bg-zinc-100 dark:bg-zinc-950 font-black border-t-2 border-zinc-300 dark:border-zinc-700 text-xs sm:text-sm">
                            <td class="p-3 border-r border-zinc-200 dark:border-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">
                                TOTAL
                            </td>
                            <td class="p-3 border-r border-zinc-200 dark:border-zinc-800 text-right text-zinc-900 dark:text-zinc-100 text-sm sm:text-base font-black">
                                {{ number_format($totalAwal, 0, ',', '.') }}
                            </td>
                            <td class="p-3 border-r border-zinc-200 dark:border-zinc-800 text-center text-zinc-900 dark:text-zinc-100 text-sm sm:text-base font-black">
                                {{ number_format($totalMati, 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right text-emerald-600 dark:text-emerald-400 text-sm sm:text-base font-black">
                                {{ number_format($totalSisa, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        </div>

    </div>
</x-filament-panels::page>