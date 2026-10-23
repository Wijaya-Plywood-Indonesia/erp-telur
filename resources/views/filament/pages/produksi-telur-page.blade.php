<x-filament-panels::page>

    <style>
        input::-webkit-outer-spin-button,

        input::-webkit-inner-spin-button {

            -webkit-appearance: none;

            margin: 0;

        }



        input[type=number] {

            -moz-appearance: textfield;

        }
    </style>



    <div class="space-y-4 w-full min-w-0 overflow-hidden" x-data="{ tab: '{{ $canViewProduksiTab ? 'produksi' : 'korektor' }}', showSaveConfirm: false, saveTarget: null }">

        @if(empty($kandangs) || (is_array($kandangs) && count($kandangs) === 0))

        <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">

            <x-heroicon-o-building-office-2 class="w-12 h-12 text-gray-300 dark:text-gray-700 mb-4" />

            <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">Data Kandang Belum Tersedia</h3>

            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Belum ada data kandang yang dapat ditampilkan saat ini.</p>

        </div>

        @else



        {{-- ── INFORMASI VALIDASI: TETAP DI ATAS KONTEN ── --}}

        @if($is_validated)

        <div class="px-3 py-2 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-800 dark:text-emerald-400 text-xs font-semibold flex items-center gap-2 rounded-none w-full" role="status">

            <x-heroicon-o-shield-check class="w-4 h-4 shrink-0" />

            <span>Produksi telur sudah divalidasi oleh <span class="font-black">{{ $namaValidator ?: 'Unknown' }}</span> pada <span class="font-black">{{ $waktuValidasi }}</span>.</span>

        </div>

        @elseif($isProduksiLocked && !$isSuperAdmin)

        <div class="px-3 py-1.5 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-400 text-xs font-semibold inline-flex items-center gap-1.5 rounded-none w-full">

            <x-heroicon-o-lock-closed class="w-3.5 h-3.5" />

            Produksi telah dikunci setelah disimpan. Hubungi Super Admin bila perlu koreksi.

        </div>

        @endif



        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 p-4 flex flex-col md:flex-row gap-4 justify-between items-center rounded-none shadow-sm">

            <div class="flex items-center gap-3 w-full md:w-auto">

                <div class="space-y-0.5">

                    <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Tanggal</label>

                    <input

                        type="date"

                        wire:model.live="tanggal"

                        class="border border-gray-300 dark:border-gray-700 px-2.5 py-1.5 text-xs font-bold bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-white focus:outline-none focus:border-gray-500 rounded-none shadow-inner">

                </div>

            </div>



            @if($canViewProduksiTab)

            <div class="grid grid-cols-3 gap-2 w-full md:max-w-xl">

                <div class="bg-gray-50 dark:bg-gray-800/50 p-2.5 rounded-none border border-gray-200 dark:border-gray-700 flex flex-col justify-center">

                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Total Butir</span>

                    <div class="text-lg font-black text-gray-800 dark:text-gray-100 mt-0.5">

                        {{ number_format($grandTotal['butir']) }} <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">Btr</span>

                    </div>

                </div>

                <div class="bg-gray-50 dark:bg-gray-800/50 p-2.5 rounded-none border border-gray-200 dark:border-gray-700 flex flex-col justify-center">

                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Total Kilo</span>

                    <div class="text-lg font-black text-gray-800 dark:text-gray-100 mt-0.5">

                        {{ number_format($grandTotal['kilo'], 1) }} <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">Kg</span>

                    </div>

                </div>

                <div class="bg-gray-50 dark:bg-gray-800/50 p-2.5 rounded-none border border-gray-200 dark:border-gray-700 flex flex-col justify-center">

                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Total Tray</span>

                    <div class="text-lg font-black text-gray-800 dark:text-gray-100 mt-0.5">

                        {{ number_format($grandTotal['tray']) }} <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">Tray</span>

                    </div>

                </div>

            </div>

            @endif

        </div>



        {{-- ── TAB NAVIGATION ──────────────────────────────────── --}}

        <div class="flex border-b border-gray-300 dark:border-gray-700">

            @if($canViewProduksiTab)

            <button

                type="button"

                x-on:click="tab = 'produksi'"

                :class="tab === 'produksi'

                    ? 'border-b-2 border-emerald-600 text-emerald-700 dark:text-emerald-400'

                    : 'border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"

                class="px-4 py-2.5 text-xs font-black uppercase tracking-wider transition-colors flex items-center gap-1.5">

                <x-heroicon-o-table-cells class="w-4 h-4" />

                Produksi Telur

                @if($isProduksiLocked)

                <x-heroicon-o-lock-closed class="w-3 h-3 text-gray-400" />

                @endif

            </button>

            @endif



            @if($canViewKorektorTab)

            <button

                type="button"

                x-on:click="tab = 'korektor'"

                :class="tab === 'korektor'

                    ? 'border-b-2 border-emerald-600 text-emerald-700 dark:text-emerald-400'

                    : 'border-b-2 border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"

                class="px-4 py-2.5 text-xs font-black uppercase tracking-wider transition-colors flex items-center gap-1.5">

                <x-heroicon-o-clipboard-document-check class="w-4 h-4" />

                Analisa Korektor

                @if($isKorektorLocked)

                <x-heroicon-o-lock-closed class="w-3 h-3 text-gray-400" />

                @endif

                @if($statusKorektor)

                <span class="ml-1 w-2 h-2 rounded-full

                    @if(($statusKorektor['color'] ?? '') === 'success') bg-emerald-500

                    @elseif(($statusKorektor['color'] ?? '') === 'warning') bg-amber-500

                    @else bg-rose-500 @endif"></span>

                @endif

            </button>

            @endif

        </div>



        {{-- ── TAB 1: PRODUKSI TELUR (GRID KANDANG) ────────────── --}}

        @if($canViewProduksiTab)

        <div x-show="tab === 'produksi'" x-cloak class="space-y-4">



            <div class="flex gap-2 justify-end items-center flex-wrap">

                @if(!$canEdit && !$isSuperAdmin)

                <span class="text-xs text-gray-500 dark:text-gray-400 italic">

                    @if($is_validated)

                    Sudah tervalidasi — tidak dapat diedit.

                    @else

                    Sudah dikunci — hanya bisa divalidasi atau dibuka oleh Super Admin.

                    @endif

                </span>

                @endif



                {{-- Status penyimpanan selalu berada tepat di atas tombol Simpan Produksi. --}}
                @if($isProduksiLocked)
                <div class="w-full mb-1 px-3 py-2 border text-xs font-semibold flex items-center gap-2 rounded-none
    {{ $is_validated
        ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/50 text-emerald-800 dark:text-emerald-300'
        : 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-300' }}"
                    role="status">
                    <x-heroicon-o-check-circle class="w-4 h-4 shrink-0" />
                    <div class="flex flex-col">
                        <span>
                            Disimpan oleh:
                            <span class="font-black">{{ $namaPenyimpan ?: 'Tidak diketahui' }}</span>
                            @if($produksiDiperbaiki)
                            <span class="font-black text-violet-700 dark:text-violet-300">(Diperbaiki)</span>
                            @endif
                            @if($waktuPenyimpanan)
                            pada <span class="font-black">{{ $waktuPenyimpanan }}</span>
                            @endif
                        </span>
                        <span class="font-normal opacity-80">
                            {{ $is_validated ? 'Sudah divalidasi.' : 'Menunggu validasi admin.' }}
                        </span>
                    </div>
                </div>
                @endif

                @if($canEdit)

                <button

                    type="button"

                    x-on:click="saveTarget = 'produksi'; showSaveConfirm = true"


                    class="px-5 py-1.5 text-xs font-bold text-white transition shadow-sm border rounded-none hover:brightness-110"

                    style="background-color:#107c41; border-color:#107c41;">

                    Simpan Produksi

                </button>

                @endif

            </div>



            <div class="overflow-x-auto w-full border border-gray-300 dark:border-gray-800 bg-white dark:bg-zinc-950 rounded-none shadow-md">

                @php

                $lebarMinimal = 48 + (count($kandangs) * 240) + 20;

                @endphp

                <table id="excel-table" class="text-sm border-collapse w-full" style="min-width: {{ $lebarMinimal }}px;">

                    <thead>

                        <tr class="bg-gray-100 dark:bg-zinc-900">

                            <th class="px-2 py-3.5 text-center w-12 font-bold text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-800" rowspan="2">No</th>

                            @foreach($kandangs as $index => $kandang)

                            @php

                            $idKandang = $kandang['id'];

                            $isEven = ($index % 2 === 0);



                            $headerClass = $isEven

                            ? 'bg-teal-200 text-teal-950 border-teal-300/80 dark:bg-teal-950/40 dark:text-teal-200 dark:border-zinc-800'

                            : 'bg-amber-200 text-amber-950 border-amber-300/80 dark:bg-amber-950/30 dark:text-amber-200 dark:border-zinc-800';

                            @endphp

                            <th class="px-3 py-2 border border-zinc-300 dark:border-zinc-800 shadow-sm {{ $headerClass }}" colSpan="3">

                                <div class="flex items-center justify-between gap-4 w-full">

                                    <span class="font-black uppercase text-[12px] tracking-wider whitespace-nowrap">{{ $kandang['nama_kandang'] }}</span>

                                    <div class="w-32">

                                        <select

                                            wire:key="pakan-{{ $tanggal }}-{{ $idKandang }}"

                                            wire:model.live="kandangPakan.{{ $idKandang }}"

                                            class="w-full text-center text-xs font-bold border border-zinc-400/60 dark:border-zinc-700 dark:text-white bg-white/90 dark:bg-zinc-800 py-1 px-1 rounded-none shadow-sm focus:outline-none focus:ring-1 focus:ring-teal-500 focus:dark:ring-teal-400"

                                            {{ !$isEditable ? 'disabled' : '' }}>

                                            <option value="">Pilih Pakan</option>

                                            @foreach($allPakan as $pakan)

                                            <option wire:key="pakan-opt-{{ $tanggal }}-{{ $idKandang }}-{{ $pakan['id'] }}"

                                                value="{{ $pakan['id'] }}">

                                                {{ $pakan['nama_barang'] }}

                                            </option>

                                            @endforeach

                                        </select>

                                    </div>

                                </div>

                            </th>

                            @endforeach

                        </tr>

                        <tr>

                            @foreach($kandangs as $index => $kandang)

                            @php

                            $isEven = ($index % 2 === 0);



                            $subHeaderClass = $isEven

                            ? 'bg-teal-100/60 text-teal-900 border-zinc-300 dark:bg-teal-950/20 dark:text-teal-300 dark:border-zinc-800'

                            : 'bg-amber-100/60 text-amber-900 border-zinc-300 dark:bg-amber-950/15 dark:text-amber-300 dark:border-zinc-800';

                            @endphp

                            <th class="py-1 text-center font-black text-[11px] tracking-widest uppercase border-r border-zinc-300 dark:border-zinc-800 {{ $subHeaderClass }}">Butir</th>

                            <th class="py-1 text-center font-black text-[11px] tracking-widest uppercase border-r border-zinc-300 dark:border-zinc-800 {{ $subHeaderClass }}">Kilo</th>

                            <th class="py-1 text-center font-black text-[11px] tracking-widest uppercase border-r border-zinc-300 dark:border-zinc-800 {{ $subHeaderClass }}">Tray</th>

                            @endforeach

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">

                        @for($rowIdx = 0; $rowIdx < $maxRows; $rowIdx++)

                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10 transition-colors">

                            <td class="text-center font-black text-zinc-900 bg-zinc-100 border border-zinc-300 dark:border-zinc-800 py-1.5 text-xs dark:bg-zinc-900 dark:text-zinc-400">

                                {{ $rowIdx + 1 }}

                            </td>

                            @foreach($kandangs as $index => $kandang)

                            @php

                            $idKandang = $kandang['id'];

                            $isEven = ($index % 2 === 0);

                            $cellBgClass = $isEven

                            ? 'bg-teal-50/10 dark:bg-zinc-800/10'

                            : 'bg-amber-50/10 dark:bg-zinc-800/5';

                            @endphp

                            <td class="p-0.5 border border-zinc-300 dark:border-zinc-800 text-center {{ $cellBgClass }}">

                                <input type="number"

                                    wire:model.live="gridData.{{ $idKandang }}.{{ $rowIdx }}.butir"

                                    {{ !$isEditable ? 'disabled' : '' }}

                                    placeholder="0"

                                    data-row="{{ $rowIdx }}"

                                    data-col="{{ $idKandang }}\_butir"

                                    class="w-full text-center text-[13px] font-bold border-0 bg-white dark:bg-zinc-700 py-1 px-0.5 outline-none focus:ring-2 focus:ring-teal-500 focus:dark:ring-teal-400 rounded-none text-zinc-800 dark:text-zinc-100 transition-colors"

                                    {{ !$isEditable ? 'disabled' : '' }}>

                            </td>

                            <td class="p-0.5 border border-zinc-300 dark:border-zinc-800 text-center {{ $cellBgClass }}">

                                <input type="number"

                                    step="0.01"

                                    wire:model.live="gridData.{{ $idKandang }}.{{ $rowIdx }}.kilo"

                                    {{ !$isEditable ? 'disabled' : '' }}

                                    placeholder="0.0"

                                    data-row="{{ $rowIdx }}"

                                    data-col="{{ $idKandang }}\_kilo"

                                    class="w-full text-center text-[13px] font-bold border-0 bg-white dark:bg-zinc-700 py-1 px-0.5 outline-none focus:ring-2 focus:ring-teal-500 focus:dark:ring-teal-400 rounded-none text-zinc-800 dark:text-zinc-100 transition-colors"

                                    {{ !$isEditable ? 'disabled' : '' }}>

                            </td>

                            <td class="p-0.5 border border-zinc-300 dark:border-zinc-800 text-center {{ $cellBgClass }}">

                                <input type="number"

                                    step="0.1"

                                    wire:model.live="gridData.{{ $idKandang }}.{{ $rowIdx }}.tray"

                                    {{ !$isEditable ? 'disabled' : '' }}

                                    placeholder="0"

                                    data-row="{{ $rowIdx }}"

                                    data-col="{{ $idKandang }}\_tray"

                                    class="w-full text-center text-[13px] font-bold border-0 bg-white dark:bg-zinc-700 py-1 px-0.5 outline-none focus:ring-2 focus:ring-teal-500 focus:dark:ring-teal-400 rounded-none text-zinc-800 dark:text-zinc-100 transition-colors"

                                    disabled>

                            </td>

                            @endforeach

                            </tr>

                            @endfor

                    </tbody>

                    <tfoot>

                        <tr class="font-black border-t-2 border-b-4 border-zinc-400 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-900">

                            <td class="text-center font-bold text-zinc-700 dark:text-zinc-300 border border-zinc-300 dark:border-zinc-800 text-xs py-3">TOTAL</td>

                            @foreach($kandangs as $index => $kandang)

                            @php

                            $idKandang = $kandang['id'];

                            $isEven = ($index % 2 === 0);



                            $totalClass = $isEven

                            ? 'bg-teal-200 text-teal-950 border-zinc-300 dark:bg-teal-950/40 dark:text-teal-200 dark:border-zinc-800'

                            : 'bg-amber-200 text-amber-950 border-zinc-300 dark:bg-amber-950/30 dark:text-amber-200 dark:border-zinc-800';

                            @endphp

                            <td class="py-3 text-center text-[13px] font-black border border-zinc-300 dark:border-zinc-800 {{ $totalClass }}">

                                {{ number_format($kandangTotals[$idKandang]['butir'] ?? 0) }}

                            </td>

                            <td class="py-3 text-center text-[13px] font-black border border-zinc-300 dark:border-zinc-800 {{ $totalClass }}">

                                {{ number_format($kandangTotals[$idKandang]['kilo'] ?? 0, 1) }}

                            </td>

                            <td class="py-3 text-center text-[13px] font-black border border-zinc-300 dark:border-zinc-800 {{ $totalClass }}">

                                {{ number_format($kandangTotals[$idKandang]['tray'] ?? 0, 1) }}

                            </td>

                            @endforeach

                        </tr>

                    </tfoot>

                </table>

            </div>

        </div>

        @endif



        @if($canViewKorektorTab)

        {{-- ── TAB 2: ANALISA KOREKTOR ─────────────────────────── --}}

        <div x-show="tab === 'korektor'" x-cloak class="space-y-4">

            @if($namaKorektorPenyimpan && $isKorektorLocked)
            <div class="px-3 py-1.5 bg-sky-50 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800/50 text-sky-800 dark:text-sky-400 text-xs font-semibold inline-flex flex-wrap items-center gap-1.5 rounded-none w-full" role="status">
                <x-heroicon-o-clock class="w-3.5 h-3.5 shrink-0" />
                <span>
                    Disimpan oleh:
                    <span class="font-black">{{ $namaKorektorPenyimpan }}</span>
                    @if($korektorDiperbaiki)
                    <span class="font-black text-violet-700 dark:text-violet-300">(Diperbaiki)</span>
                    @endif
                    @if($waktuKorektorUpdate)
                    pada <span class="font-black">{{ $waktuKorektorUpdate }}</span>
                    @endif
                </span>
            </div>
            @endif

            <div class="flex flex-col gap-2 w-full">
                @if(! $produksiTelurId || ! $isProduksiLocked)
                <div class="w-full rounded-none border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300" role="alert">
                    <strong>Perhatian:</strong> Simpan Produksi Telur terlebih dahulu sebelum menyimpan Analisa Korektor.
                </div>
                @else
                {{-- Jika data sedang dibuka untuk perbaikan, tampilkan info peringatan di atas tombol --}}
                @if($namaKorektorPenyimpan && ! $isKorektorLocked)
                <div class="w-full rounded-none border border-sky-300 bg-sky-50 px-3 py-2 text-xs text-sky-800 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-300" role="alert">
                    <strong>Perhatian:</strong> Data Analisa Korektor sedang dibuka untuk perbaikan.
                </div>
                @endif

                {{-- Tombol simpan akan tetap muncul selama belum dikunci permanen / memiliki hak akses edit --}}
                @if($canEditKorektor || ! $isKorektorLocked)
                <div class="flex justify-end items-center">
                    <button type="button"
                        x-on:click="saveTarget = 'korektor'; showSaveConfirm = true"
                        class="px-5 py-1.5 text-xs font-bold text-white transition shadow-sm border rounded-none hover:brightness-110"
                        style="background-color:#107c41; border-color:#107c41;">
                        Simpan Korektor
                    </button>
                </div>
                @else
                <span class="text-xs text-gray-500 italic text-right">
                    Korektor sudah dikunci setelah disimpan. Hubungi Super Admin bila perlu koreksi.
                </span>
                @endif
                @endif
            </div>



            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">



                {{-- ── KIRI: Tabel Analisa Korektor (2/3 lebar) ── --}}

                <div class="lg:col-span-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-none shadow-md overflow-hidden">



                    <div class="px-2.5 py-2 sm:px-4 sm:py-3 border-b border-zinc-300 dark:border-zinc-800 bg-zinc-100 dark:bg-zinc-900 flex items-center justify-between gap-2">

                        <div>

                            <h3 class="text-xs sm:text-sm font-black text-zinc-700 dark:text-zinc-300 uppercase tracking-wider flex items-center gap-1.5 sm:gap-2">

                                <x-heroicon-o-clipboard-document-check class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-500 shrink-0" />

                                <span>Hasil Analisa Korektor</span>

                            </h3>

                        </div>

                        <span class="text-[9px] sm:text-[10px] px-1.5 py-0.5 sm:px-2 sm:py-1 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400 rounded-none font-bold uppercase tracking-wider whitespace-nowrap">

                            Auto-Sync <span class="hidden xs:inline">dengan Kandang</span>

                        </span>

                    </div>



                    <div class="overflow-x-auto w-full">

                        <table class="w-full text-xs sm:text-sm border-collapse min-w-[340px]">

                            <thead>

                                <tr class="bg-zinc-50 dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 border-b border-zinc-300 dark:border-zinc-800 text-left">

                                    <th class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 font-black text-[9px] sm:text-[11px] uppercase tracking-wider">Komponen / Item</th>

                                    <th class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 font-black text-[9px] sm:text-[11px] uppercase tracking-wider text-center w-24 sm:w-36">Nilai Input</th>

                                    <th class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 font-black text-[9px] sm:text-[11px] uppercase tracking-wider text-center w-12 sm:w-20">Satuan</th>

                                    <th class="p-1.5 sm:p-2.5 font-black text-[9px] sm:text-[11px] uppercase tracking-wider text-right">Hasil Konversi (Kg)</th>

                                </tr>

                            </thead>

                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">



                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/20">

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 font-bold text-zinc-700 dark:text-zinc-300 text-xs sm:text-sm">

                                        <div class="flex items-center justify-between gap-1 flex-wrap xs:flex-nowrap">

                                            <span>1. Peti</span>

                                            <span class="text-[9px] sm:text-[10px] font-semibold text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1 py-0.5">@10 Kg/Pt</span>

                                        </div>

                                    </td>

                                    <td class="p-1 sm:p-1.5 border-r border-zinc-300 dark:border-zinc-800 text-center">

                                        <input type="number" wire:model.live.debounce.500ms="korektorPeti" @disabled(!$canEditKorektor) placeholder="0"

                                            class="w-full text-center font-bold text-xs sm:text-sm border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-1 sm:py-1.5 rounded-none outline-none focus:ring-2 focus:ring-teal-500"

                                            {{ !$canEditKorektor ? 'disabled' : '' }}>

                                    </td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-zinc-500 font-semibold text-xs sm:text-sm">Pt</td>

                                    <td class="p-1.5 sm:p-2.5 text-right font-black text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm">

                                        {{ number_format(($korektorPeti ?? 0) * 10, 1) }} Kg

                                    </td>

                                </tr>



                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/20">

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 font-bold text-zinc-700 dark:text-zinc-300 text-xs sm:text-sm">2. Kiloan</td>

                                    <td class="p-1 sm:p-1.5 border-r border-zinc-300 dark:border-zinc-800 text-center">

                                        <input type="number" step="0.1" wire:model.live.debounce.500ms="korektorKiloan" placeholder="0"

                                            class="w-full text-center font-bold text-xs sm:text-sm border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-1 sm:py-1.5 rounded-none outline-none focus:ring-2 focus:ring-teal-500"

                                            {{ !$canEditKorektor ? 'disabled' : '' }}>

                                    </td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-zinc-500 font-semibold text-xs sm:text-sm">Kg</td>

                                    <td class="p-1.5 sm:p-2.5 text-right font-black text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm">

                                        {{ number_format($korektorKiloan ?? 0, 1) }} Kg

                                    </td>

                                </tr>



                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/20">

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 font-bold text-zinc-700 dark:text-zinc-300 text-xs sm:text-sm">3. Sisa</td>

                                    <td class="p-1 sm:p-1.5 border-r border-zinc-300 dark:border-zinc-800 text-center">

                                        <input type="number" step="0.1" wire:model.live.debounce.500ms="korektorSisa" placeholder="0"

                                            class="w-full text-center font-bold text-xs sm:text-sm border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-1 sm:py-1.5 rounded-none outline-none focus:ring-2 focus:ring-teal-500"

                                            {{ !$canEditKorektor ? 'disabled' : '' }}>

                                    </td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-zinc-500 font-semibold text-xs sm:text-sm">Kg</td>

                                    <td class="p-1.5 sm:p-2.5 text-right font-black text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm">

                                        {{ number_format($korektorSisa ?? 0, 1) }} Kg

                                    </td>

                                </tr>



                                <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/20">

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 font-bold text-zinc-700 dark:text-zinc-300 text-xs sm:text-sm">4. Bentes (Retak)</td>

                                    <td class="p-1 sm:p-1.5 border-r border-zinc-300 dark:border-zinc-800 text-center">

                                        <input type="number" step="0.1" wire:model.live.debounce.500ms="korektorBentes" placeholder="0"

                                            class="w-full text-center font-bold text-xs sm:text-sm border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 py-1 sm:py-1.5 rounded-none outline-none focus:ring-2 focus:ring-teal-500"

                                            {{ !$canEditKorektor ? 'disabled' : '' }}>

                                    </td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-zinc-500 font-semibold text-xs sm:text-sm">Kg</td>

                                    <td class="p-1.5 sm:p-2.5 text-right font-black text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm">

                                        {{ number_format($korektorBentes ?? 0, 1) }} Kg

                                    </td>

                                </tr>



                                <tr class="bg-emerald-50/60 dark:bg-emerald-950/20 font-black border-t-2 border-zinc-300 dark:border-zinc-800">

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-emerald-700 dark:text-emerald-400 text-xs sm:text-sm">Total Korektor</td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-emerald-700 dark:text-emerald-400 text-xs sm:text-sm">{{ number_format($korektorTotalKg, 1) }}</td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-emerald-700 dark:text-emerald-400 text-xs sm:text-sm">Kg</td>

                                    <td class="p-1.5 sm:p-2.5 text-right text-emerald-700 dark:text-emerald-400 text-xs sm:text-base">{{ number_format($korektorTotalKg, 1) }} Kg</td>

                                </tr>



                                @unless($isPegawaiRuko)

                                <tr class="bg-zinc-50 dark:bg-zinc-900 font-black">

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 text-xs sm:text-sm">

                                        <span class="inline-flex items-center gap-1">

                                            <span>Dari Kandang</span>

                                            <x-heroicon-o-link class="w-3 h-3 text-sky-500 shrink-0" title="Terkoneksi dengan Total Produksi Kandang" />

                                        </span>

                                    </td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-sky-600 dark:text-sky-400 text-xs sm:text-sm">{{ number_format($grandTotal['kilo'], 1) }}</td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-zinc-500 text-xs sm:text-sm">Kg</td>

                                    <td class="p-1.5 sm:p-2.5 text-right text-sky-600 dark:text-sky-400 text-xs sm:text-base">{{ number_format($grandTotal['kilo'], 1) }} Kg</td>

                                </tr>

                                @endunless



                                <tr class="border-t-2 border-zinc-400 dark:border-zinc-700 font-black

                            @if(($statusKorektor['color'] ?? '') === 'success') bg-emerald-100/60 dark:bg-emerald-950/30

                            @elseif(($statusKorektor['color'] ?? '') === 'warning') bg-amber-100/60 dark:bg-amber-950/30

                            @else bg-rose-100/60 dark:bg-rose-950/30 @endif">

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs sm:text-sm">Selisih</td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-xs sm:text-base">{{ $selisihKg > 0 ? '+' : '' }}{{ number_format($selisihKg, 1) }}</td>

                                    <td class="p-1.5 sm:p-2.5 border-r border-zinc-300 dark:border-zinc-800 text-center text-zinc-500 text-xs sm:text-sm">Kg</td>

                                    <td class="p-1.5 sm:p-2.5 text-right text-xs sm:text-lg">{{ $selisihKg > 0 ? '+' : '' }}{{ number_format($selisihKg, 1) }} Kg</td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>



                {{-- ── KANAN: Status Rekonsiliasi (1/3 lebar) ── --}}

                <div class="bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-none shadow-md p-3 sm:p-4 flex flex-col justify-between">

                    <div>

                        <h3 class="text-xs sm:text-sm font-black text-zinc-700 dark:text-zinc-300 uppercase tracking-wider mb-2 sm:mb-3 flex items-center gap-1.5 sm:gap-2">

                            <x-heroicon-o-chart-bar class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-emerald-500 shrink-0" />

                            <span>Status Rekonsiliasi</span>

                        </h3>



                        @php

                        $color = $statusKorektor['color'] ?? 'success';

                        $icon = match($color) {

                        'success' => 'heroicon-o-check-circle',

                        'warning' => 'heroicon-o-exclamation-triangle',

                        default => 'heroicon-o-x-circle',

                        };

                        $bgClass = match($color) {

                        'success' => 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/50',

                        'warning' => 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800/50',

                        default => 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800/50',

                        };

                        $textClass = match($color) {

                        'success' => 'text-emerald-600 dark:text-emerald-400',

                        'warning' => 'text-amber-600 dark:text-amber-400',

                        default => 'text-rose-600 dark:text-rose-400',

                        };

                        @endphp



                        <div class="p-2.5 sm:p-4 border rounded-none text-center mb-3 sm:mb-4 {{ $bgClass }}">

                            <x-dynamic-component :component="$icon" class="w-7 h-7 sm:w-9 sm:h-9 mx-auto mb-1 {{ $textClass }}" />

                            <div class="font-black text-xs sm:text-base {{ $textClass }}">{{ $statusKorektor['label'] ?? '-' }}</div>

                            <div class="text-[10px] sm:text-xs mt-0.5 sm:mt-1 opacity-80 {{ $textClass }}">

                                @if($color === 'success')

                                Tidak ada perbedaan antara Kandang &amp; Korektor

                                @elseif($color === 'warning')

                                Selisih dalam batas toleransi (&lt; 2.0 Kg)

                                @else

                                Perlu pengecekan ulang data penimbangan!

                                @endif

                            </div>

                        </div>

                        <div class="space-y-1 sm:space-y-1.5 text-[11px] sm:text-xs bg-zinc-50 dark:bg-zinc-900 p-2.5 sm:p-3 border border-zinc-200 dark:border-zinc-800">

                            <div class="flex justify-between py-0.5 sm:py-1 border-b border-zinc-200 dark:border-zinc-800">

                                <span class="text-zinc-500 dark:text-zinc-400">Total Fisik Korektor:</span>

                                <span class="font-black text-zinc-800 dark:text-zinc-100">{{ number_format($korektorTotalKg, 1) }} Kg</span>

                            </div>

                            <div class="flex justify-between py-0.5 sm:py-1 border-b border-zinc-200 dark:border-zinc-800">

                                <span class="text-zinc-500 dark:text-zinc-400">Total Laporan Kandang:</span>

                                <span class="font-black text-zinc-800 dark:text-zinc-100">{{ number_format($grandTotal['kilo'], 1) }} Kg</span>

                            </div>

                            <div class="flex justify-between py-0.5 sm:py-1 font-black">

                                <span class="text-zinc-600 dark:text-zinc-300">Margin Selisih:</span>

                                <span class="text-amber-600 dark:text-amber-400">{{ $selisihKg > 0 ? '+' : '' }}{{ number_format($selisihKg, 1) }} Kg</span>

                            </div>

                        </div>

                    </div>



                    <div class="mt-3 sm:mt-4">

                        <label class="block text-[9px] sm:text-[10px] font-black text-zinc-400 uppercase mb-1">Catatan Korektor / Petugas</label>

                        <textarea wire:model.live.debounce.500ms="korektorCatatan" rows="3"

                            placeholder="Tuliskan catatan selisih atau kondisi telur di sini..."

                            class="w-full border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-[11px] sm:text-xs p-1.5 sm:p-2 rounded-none outline-none focus:ring-2 focus:ring-teal-500"

                            {{ !$canEditKorektor ? 'disabled' : '' }}></textarea>

                    </div>

                </div>



            </div>

        </div>

        @endif

        {{-- ↑ penutup @if($canViewKorektorTab) --}}



        @endif

        {{-- ↑ FIX: penutup @if(empty($kandangs) || ...) ... @else di baris paling atas.

             Baris inilah yang sebelumnya hilang → penyebab ParseError & bug tampilan. --}}

        {{-- ── POPUP KONFIRMASI SIMPAN PRODUKSI / KOREKTOR ── --}}
        <div
            x-cloak
            x-show="showSaveConfirm"
            x-transition.opacity
            x-on:keydown.escape.window="showSaveConfirm = false"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="save-confirm-title"
            style="display: none;">
            <div
                x-transition.scale.95
                x-on:click.outside="showSaveConfirm = false"
                class="w-full max-w-xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/70">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                        <x-heroicon-o-clipboard-document-check class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 id="save-confirm-title" class="text-base font-bold text-gray-950 dark:text-white"
                            x-text="saveTarget === 'produksi' ? 'Konfirmasi Simpan Produksi Telur' : 'Konfirmasi Simpan Analisa Korektor'"></h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Periksa ringkasan berikut sebelum menyimpan. Data akan terkunci setelah berhasil disimpan.
                        </p>
                    </div>
                    <button type="button" x-on:click="showSaveConfirm = false" aria-label="Tutup popup"
                        class="rounded-lg p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-800 dark:hover:bg-gray-700 dark:hover:text-white">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-4 p-5">
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2.5 dark:border-gray-700">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Tanggal data</span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $tanggal }}</span>
                    </div>

                    <div x-show="saveTarget === 'produksi'" x-cloak class="space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ringkasan Produksi Telur</p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Total Butir</p>
                                <p class="mt-1 text-xl font-black text-gray-900 dark:text-white">{{ number_format($grandTotal['butir'] ?? 0) }} <span class="text-xs font-semibold">butir</span></p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Total Berat</p>
                                <p class="mt-1 text-xl font-black text-gray-900 dark:text-white">{{ number_format($grandTotal['kilo'] ?? 0, 1) }} <span class="text-xs font-semibold">kg</span></p>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Total Tray</p>
                                <p class="mt-1 text-xl font-black text-gray-900 dark:text-white">{{ number_format($grandTotal['tray'] ?? 0, 1) }} <span class="text-xs font-semibold">tray</span></p>
                            </div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                            <span class="font-bold">Perhatian:</span> setelah penyimpanan berhasil, data Produksi Telur akan terkunci sesuai aturan aplikasi.
                        </div>
                    </div>

                    <div x-show="saveTarget === 'korektor'" x-cloak class="space-y-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Ringkasan Analisa Korektor</p>
                        <div class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                            <div class="flex items-center justify-between gap-4 px-3 py-2.5">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Peti (10 kg/peti)</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($korektorPeti ?? 0, 1) }} peti</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 px-3 py-2.5">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Kiloan</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($korektorKiloan ?? 0, 1) }} kg</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 px-3 py-2.5">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Sisa</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($korektorSisa ?? 0, 1) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 px-3 py-2.5">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Bentes (retak)</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($korektorBentes ?? 0, 1) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 bg-emerald-50 px-3 py-3 dark:bg-emerald-950/30">
                                <span class="text-sm font-bold text-emerald-800 dark:text-emerald-300">Total Korektor</span>
                                <span class="text-base font-black text-emerald-800 dark:text-emerald-300">{{ number_format($korektorTotalKg, 1) }} kg</span>
                            </div>
                            @unless($isPegawaiRuko)
                            <div class="flex items-center justify-between gap-4 px-3 py-2.5">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Produksi dari kandang</span>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($grandTotal['kilo'] ?? 0, 1) }} kg</span>
                            </div>
                            <div class="flex items-center justify-between gap-4 px-3 py-2.5">
                                <span class="text-sm text-gray-500 dark:text-gray-400">Selisih</span>
                                <span class="text-sm font-black {{ ($statusKorektor['color'] ?? 'success') === 'danger' ? 'text-rose-600' : 'text-amber-600' }}">{{ $selisihKg > 0 ? '+' : '' }}{{ number_format($selisihKg, 1) }} kg</span>
                            </div>
                            @endunless
                        </div>
                        @if(!empty($korektorCatatan))
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Catatan</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $korektorCatatan }}</p>
                        </div>
                        @endif
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                            <span class="font-bold">Perhatian:</span> setelah penyimpanan berhasil, data Analisa Korektor akan terkunci sesuai aturan aplikasi.
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:justify-end dark:border-gray-700 dark:bg-gray-800/50">
                    <x-filament::button color="gray" outlined x-on:click="showSaveConfirm = false">
                        Batal, Periksa Lagi
                    </x-filament::button>
                    <x-filament::button
                        color="success"
                        icon="heroicon-o-check-circle"
                        x-on:click="
                        const target = saveTarget;
                        showSaveConfirm = false;
                        if (target === 'produksi') {
                            $wire.call('save');
                        } else if (target === 'korektor') {
                            $wire.call('saveKorektorOnly');
                        }
                    ">
                        Ya, Simpan Data
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>




    {{-- ── Navigasi keyboard di grid produksi (Enter/Arrow) ── --}}

    <script>
        document.addEventListener('keydown', function(e) {

            const target = e.target;



            if (target.tagName !== 'INPUT' || !target.hasAttribute('data-row') || !target.hasAttribute('data-col')) {

                return;

            }



            const currentRow = parseInt(target.getAttribute('data-row'));

            const currentCol = target.getAttribute('data-col');



            const rowInputs = Array.from(document.querySelectorAll(`input[data-row="${currentRow}"]`));

            const currentIndex = rowInputs.indexOf(target);



            let nextInput = null;



            if (e.key === 'Enter') {

                e.preventDefault();

                const targetRow = e.shiftKey ? currentRow - 1 : currentRow + 1;

                nextInput = document.querySelector(`input[data-row="${targetRow}"][data-col="${currentCol}"]`);

            } else if (e.key === 'ArrowUp') {

                e.preventDefault();

                nextInput = document.querySelector(`input[data-row="${currentRow - 1}"][data-col="${currentCol}"]`);

            } else if (e.key === 'ArrowDown') {

                e.preventDefault();

                nextInput = document.querySelector(`input[data-row="${currentRow + 1}"][data-col="${currentCol}"]`);

            } else if (e.key === 'ArrowLeft') {

                if (currentIndex > 0) {

                    e.preventDefault();

                    nextInput = rowInputs[currentIndex - 1];

                }

            } else if (e.key === 'ArrowRight') {

                if (currentIndex < rowInputs.length - 1) {

                    e.preventDefault();

                    nextInput = rowInputs[currentIndex + 1];

                }

            }



            if (nextInput) {

                nextInput.focus();

                nextInput.select();

            }

        });
    </script>

</x-filament-panels::page>