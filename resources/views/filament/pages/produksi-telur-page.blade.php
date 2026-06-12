<x-filament-panels::page>
    {{-- Hapus CSS Variables lama dan gantikan dengan Tailwind murni --}}
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

    <div class="space-y-4 w-full min-w-0 overflow-hidden">
        @if(empty($kandangs) || (is_array($kandangs) && count($kandangs) === 0))
        <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">
            <x-heroicon-o-building-office-2 class="w-12 h-12 text-gray-300 dark:text-gray-700 mb-4" />
            <h3 class="text-lg font-black text-gray-700 dark:text-gray-200">Data Kandang Belum Tersedia</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Belum ada data kandang yang dapat ditampilkan saat ini.</p>
        </div>
        @else

        @if($is_validated)
        <div class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-800 dark:text-emerald-400 text-xs font-semibold inline-flex items-center gap-1.5 rounded-none w-full">
            <x-heroicon-o-shield-check class="w-3.5 h-3.5" />
            Divalidasi oleh <span class="font-black mx-1">{{ $namaValidator ?: 'Unknown' }}</span>
            pada {{ $waktuValidasi }}
            @if($namaPenyimpan)
            &nbsp;·&nbsp; Diinput oleh <span class="font-black mx-1">{{ $namaPenyimpan }}</span>
            @endif
        </div>
        @elseif($isDraftSaved && $isCreator)
        <div class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/50 text-blue-800 dark:text-blue-400 text-xs font-semibold inline-flex items-center gap-1.5 rounded-none w-full">
            <x-heroicon-o-clock class="w-3.5 h-3.5" />
            Disimpan oleh <span class="font-black mx-1">{{ $namaUserLogin }}</span>
            — Menunggu validasi dari petugas lain
        </div>
        @elseif($isDraftSaved && !$isCreator)
        <div class="px-3 py-1.5 bg-violet-50 dark:bg-violet-950/30 border border-violet-200 dark:border-violet-800/50 text-violet-800 dark:text-violet-400 text-xs font-semibold inline-flex items-center gap-1.5 rounded-none w-full">
            <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
            Diinput oleh <span class="font-black mx-1">{{ $namaPenyimpan }}</span>
            — Silakan periksa dan validasi
        </div>
        @elseif(!$isEditable)
        <div class="px-3 py-1.5 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-400 text-xs font-semibold inline-flex items-center gap-1.5 rounded-none w-full">
            <x-heroicon-o-lock-closed class="w-3.5 h-3.5" />
            Terkunci — Anda tidak memiliki akses edit
        </div>
        @endif

        <div class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 p-4 flex flex-col md:flex-row gap-4 justify-between items-center rounded-none shadow-sm">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <div class="space-y-0.5">
                    <label class="block text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider">Tanggal</label>
                    <input
                        type="date"
                        wire:model.live="tanggal"
                        class="border border-gray-300 dark:border-gray-700 px-2.5 py-1.5 text-xs font-bold bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-white focus:outline-none focus:border-gray-500 rounded-none shadow-inner"
                        {{ !$isEditable ? 'disabled' : '' }}>
                </div>
            </div>

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

            <div class="flex gap-2 w-full md:w-auto justify-end">
                @if($showSaveButton)
                <button
                    type="button"
                    x-data
                    x-on:click="if (confirm('Apakah Anda yakin ingin menyimpan data ini?\nPastikan semua data sudah benar.')) { $wire.save() }"
                    class="px-5 py-1.5 text-xs font-bold text-white transition shadow-sm border rounded-none hover:brightness-110"
                    style="background-color:#107c41; border-color:#107c41;">
                    Simpan
                </button>
                @endif
                @if($showValidateButton)
                <button
                    type="button"
                    wire:click="validateProduksi"
                    wire:confirm="Data yang sudah divalidasi tidak dapat diubah kecuali oleh Super Admin. Lanjutkan?"
                    class="px-5 py-1.5 text-xs font-bold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition rounded-none shadow-sm">
                    Validasi
                </button>
                @endif
            </div>
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

                        // ── IMPLEMENTASI PALET 200 UNTUK TEAL & AMBER DI HEADER ──
                        $headerClass = $isEven
                        ? 'bg-teal-200 text-teal-950 border-teal-300/80 dark:bg-teal-950/40 dark:text-teal-200 dark:border-zinc-800'
                        : 'bg-amber-200 text-amber-950 border-amber-300/80 dark:bg-amber-950/30 dark:text-amber-200 dark:border-zinc-800';
                        @endphp
                        <th class="px-3 py-2 border border-zinc-300 dark:border-zinc-800 shadow-sm {{ $headerClass }}" colSpan="3">
                            <div class="flex items-center justify-between gap-4 w-full">
                                <span class="font-black uppercase text-[12px] tracking-wider whitespace-nowrap">{{ $kandang['nama_kandang'] }}</span>
                                <div class="w-32">
                                    <select wire:model.live="kandangPakan.{{ $idKandang }}" class="w-full text-center text-xs font-bold border border-zinc-400/60 dark:border-zinc-700 dark:text-white bg-white/90 dark:bg-zinc-800 py-1 px-1 rounded-none shadow-sm focus:outline-none focus:ring-1 focus:ring-teal-500 focus:dark:ring-teal-400" {{ !$isEditable ? 'disabled' : '' }}>
                                        <option value="">Pilih Pakan</option>
                                        @foreach($allPakan as $pakan)
                                        <option value="{{ $pakan['id'] }}">{{ $pakan['nama_barang'] }}</option>
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

                        // ── PENGURANGAN KONTRAS PADA SUB-HEADER (PALET 100/50) ──
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
                            {{-- ── KUNCI UTAMA: INPUT PADA DARK MODE MENGGUNAKAN ZINC-700 NETRAL ── --}}
                            <input type="number"
                                wire:model.live="gridData.{{ $idKandang }}.{{ $rowIdx }}.butir"
                                placeholder="0"
                                data-row="{{ $rowIdx }}"
                                data-col="{{ $idKandang }}_butir"
                                class="w-full text-center text-[13px] font-bold border-0 bg-white dark:bg-zinc-700 py-1 px-0.5 outline-none focus:ring-2 focus:ring-teal-500 focus:dark:ring-teal-400 rounded-none text-zinc-800 dark:text-zinc-100 transition-colors"
                                {{ !$isEditable ? 'disabled' : '' }}>
                        </td>
                        <td class="p-0.5 border border-zinc-300 dark:border-zinc-800 text-center {{ $cellBgClass }}">
                            <input type="number"
                                step="0.01"
                                wire:model.live="gridData.{{ $idKandang }}.{{ $rowIdx }}.kilo"
                                placeholder="0.0"
                                data-row="{{ $rowIdx }}"
                                data-col="{{ $idKandang }}_kilo"
                                class="w-full text-center text-[13px] font-bold border-0 bg-white dark:bg-zinc-700 py-1 px-0.5 outline-none focus:ring-2 focus:ring-teal-500 focus:dark:ring-teal-400 rounded-none text-zinc-800 dark:text-zinc-100 transition-colors"
                                {{ !$isEditable ? 'disabled' : '' }}>
                        </td>
                        <td class="p-0.5 border border-zinc-300 dark:border-zinc-800 text-center {{ $cellBgClass }}">
                            <input type="number"
                                step="0.1"
                                wire:model.live="gridData.{{ $idKandang }}.{{ $rowIdx }}.tray"
                                placeholder="0"
                                data-row="{{ $rowIdx }}"
                                data-col="{{ $idKandang }}_tray"
                                class="w-full text-center text-[13px] font-bold border-0 bg-white dark:bg-zinc-700 py-1 px-0.5 outline-none focus:ring-2 focus:ring-teal-500 focus:dark:ring-teal-400 rounded-none text-zinc-800 dark:text-zinc-100 transition-colors"
                                {{ !$isEditable ? 'disabled' : '' }}>
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

                        // ── PENYELARASAN PALET TOTAL FOOTER SEPERTI HEADER ──
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
        @endif
    </div>

    {{-- ── PERBAIKAN: OPERASI NAVIGASI KEYBOARD UTARA, SELATAN, TIMUR, BARAT SECARA PRESEDENSI ── --}}
    <script>
        document.addEventListener('keydown', function(e) {
            const target = e.target;

            // Pastikan interaksi keyboard terfokus pada sel input grid yang memiliki koordinat
            if (target.tagName !== 'INPUT' || !target.hasAttribute('data-row') || !target.hasAttribute('data-col')) {
                return;
            }

            const currentRow = parseInt(target.getAttribute('data-row'));
            const currentCol = target.getAttribute('data-col');

            // Ambil semua sel input yang berada pada baris yang sama untuk mendeteksi pergeseran Kiri/Kanan
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
                // Geser ke sel sebelah kiri dalam baris yang sama
                if (currentIndex > 0) {
                    e.preventDefault();
                    nextInput = rowInputs[currentIndex - 1];
                }
            } else if (e.key === 'ArrowRight') {
                // Geser ke sel sebelah kanan dalam baris yang sama
                if (currentIndex < rowInputs.length - 1) {
                    e.preventDefault();
                    nextInput = rowInputs[currentIndex + 1];
                }
            }

            // Berikan efek fokus dan block teks secara instan saat navigasi dilakukan
            if (nextInput) {
                nextInput.focus();
                nextInput.select();
            }
        });
    </script>
</x-filament-panels::page>