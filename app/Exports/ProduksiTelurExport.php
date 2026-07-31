<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProduksiTelurExport implements WithEvents
{
    /**
     * Kita sengaja terima semua data sebagai parameter (bukan query ulang ke DB
     * di dalam class ini), supaya isi Excel dijamin 100% sama dengan yang
     * sedang tampil di layar user saat tombol Download ditekan.
     */
    public function __construct(
        protected string $tanggal,
        protected array $kandangs,      // [['id'=>.., 'nama_kandang'=>..], ...]
        protected array $gridData,      // [$idKandang][$rowIndex] = ['butir','kilo','tray']
        protected array $kandangPakan,  // [$idKandang] = id_produksi_pakan_campuran
        protected array $allPakan,      // [['id'=>.., 'nama_barang'=>..], ...]
        protected array $kandangTotals, // [$idKandang] = ['butir','kilo','tray']
        protected array $grandTotal,    // ['butir','kilo','tray']
        protected array $korektor,      // ['peti','kiloan','sisa','bentes','total']
    ) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->build($event->sheet->getDelegate());
            },
        ];
    }

    protected function pakanName(?int $idPakan): string
    {
        if (! $idPakan) {
            return '-';
        }

        foreach ($this->allPakan as $p) {
            if ((int) $p['id'] === $idPakan) {
                return $p['nama_barang'];
            }
        }

        return '-';
    }

    protected function mapFilamentColor(string $color): string
    {
        return match ($color) {
            'success' => '1F7A3D', // hijau
            'warning' => 'B45309', // oranye
            'danger'  => 'C0392B', // merah
            default   => '374151', // abu-abu netral
        };
    }

    protected function build($sheet): void
    {
        $jumlahKandang   = count($this->kandangs);
        $kolomTerakhirIx = 1 + ($jumlahKandang * 3); // kolom A = NO, sisanya 3 kolom/kandang
        $kolomTerakhir   = Coordinate::stringFromColumnIndex($kolomTerakhirIx);

        // ── Judul ──────────────────────────────────────────
        $sheet->setCellValue('A1', 'LAPORAN PRODUKSI TELUR HARIAN');
        $sheet->mergeCells("A1:{$kolomTerakhir}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('1F7A3D');

        // ── Tanggal ────────────────────────────────────────
        $sheet->setCellValue('A2', 'Tanggal Laporan:');
        $sheet->setCellValue('B2', $this->tanggal);
        $sheet->getStyle('A2')->getFont()->setBold(true);

        // ── Header grup kandang (baris 4-6) ───────────────
        $rowGroup = 4;
        $rowPakan = 5;
        $rowSub   = 6;

        $sheet->setCellValue("A{$rowGroup}", 'NO');
        $sheet->mergeCells("A{$rowGroup}:A{$rowSub}");

        $col = 2; // mulai kolom B
        foreach ($this->kandangs as $kandang) {
            $colButir = Coordinate::stringFromColumnIndex($col);
            $colKilo  = Coordinate::stringFromColumnIndex($col + 1);
            $colTray  = Coordinate::stringFromColumnIndex($col + 2);

            $sheet->setCellValue("{$colButir}{$rowGroup}", strtoupper($kandang['nama_kandang']));
            $sheet->mergeCells("{$colButir}{$rowGroup}:{$colTray}{$rowGroup}");

            $idPakan = $this->kandangPakan[$kandang['id']] ?? null;
            $sheet->setCellValue("{$colButir}{$rowPakan}", 'Pakan: ' . $this->pakanName($idPakan));
            $sheet->mergeCells("{$colButir}{$rowPakan}:{$colTray}{$rowPakan}");

            $sheet->setCellValue("{$colButir}{$rowSub}", 'BUTIR');
            $sheet->setCellValue("{$colKilo}{$rowSub}", 'KILO (KG)');
            $sheet->setCellValue("{$colTray}{$rowSub}", 'TRAY');

            $col += 3;
        }

        $sheet->getStyle("A{$rowGroup}:{$kolomTerakhir}{$rowGroup}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B1B1B');
        $sheet->getStyle("A{$rowGroup}:{$kolomTerakhir}{$rowGroup}")
            ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$rowGroup}:{$kolomTerakhir}{$rowSub}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$rowPakan}:{$kolomTerakhir}{$rowPakan}")
            ->getFont()->setItalic(true)->getColor()->setRGB('2F5496');
        $sheet->getStyle("A{$rowSub}:{$kolomTerakhir}{$rowSub}")->getFont()->setBold(true);

        // ── Baris data (10 baris tetap sesuai template) ───
        $startDataRow = 7;
        for ($i = 0; $i < 10; $i++) {
            $row = $startDataRow + $i;
            $sheet->setCellValue("A{$row}", $i + 1);

            $col = 2;
            foreach ($this->kandangs as $kandang) {
                $data = $this->gridData[$kandang['id']][$i] ?? ['butir' => 0, 'kilo' => 0, 'tray' => 0];

                $colButir = Coordinate::stringFromColumnIndex($col);
                $colKilo  = Coordinate::stringFromColumnIndex($col + 1);
                $colTray  = Coordinate::stringFromColumnIndex($col + 2);

                $sheet->setCellValue("{$colButir}{$row}", (int) ($data['butir'] ?? 0));
                $sheet->setCellValue("{$colKilo}{$row}", (float) ($data['kilo'] ?? 0));
                $sheet->setCellValue("{$colTray}{$row}", (float) ($data['tray'] ?? 0));

                $col += 3;
            }
        }

        $lastDataRow = $startDataRow + 9;

        // Format angka: kolom desimal (kilo & tray) pakai 1 angka di belakang koma
        $sheet->getStyle("B{$startDataRow}:{$kolomTerakhir}{$lastDataRow}")
            ->getNumberFormat()->setFormatCode('#,##0.0');

        // Kolom BUTIR per kandang dibuat bulat (tanpa desimal)
        $col = 2;
        foreach ($this->kandangs as $kandang) {
            $colButir = Coordinate::stringFromColumnIndex($col);
            $sheet->getStyle("{$colButir}{$startDataRow}:{$colButir}{$lastDataRow}")
                ->getNumberFormat()->setFormatCode('#,##0');
            $col += 3;
        }

        $sheet->getStyle("B{$startDataRow}:{$kolomTerakhir}{$lastDataRow}")
            ->getFont()->getColor()->setRGB('2F5496');

        // ── Baris TOTAL ────────────────────────────────────
        $rowTotal = $lastDataRow + 1; // baris 17
        $sheet->setCellValue("A{$rowTotal}", 'TOTAL');

        $col = 2;
        foreach ($this->kandangs as $kandang) {
            $total = $this->kandangTotals[$kandang['id']] ?? ['butir' => 0, 'kilo' => 0, 'tray' => 0];

            $colButir = Coordinate::stringFromColumnIndex($col);
            $colKilo  = Coordinate::stringFromColumnIndex($col + 1);
            $colTray  = Coordinate::stringFromColumnIndex($col + 2);

            $sheet->setCellValue("{$colButir}{$rowTotal}", $total['butir']);
            $sheet->setCellValue("{$colKilo}{$rowTotal}", $total['kilo']);
            $sheet->setCellValue("{$colTray}{$rowTotal}", $total['tray']);

            $col += 3;
        }

        $sheet->getStyle("A{$rowTotal}:{$kolomTerakhir}{$rowTotal}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowTotal}:{$kolomTerakhir}{$rowTotal}")
            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);

        // ── Blok Analisa Korektor Gudang ───────────────────
        $rowBanner = $rowTotal + 2; // baris 19
        $sheet->setCellValue("A{$rowBanner}", 'HASIL ANALISA KOREKTOR GUDANG');
        $sheet->mergeCells("A{$rowBanner}:D{$rowBanner}");
        $sheet->getStyle("A{$rowBanner}:D{$rowBanner}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B1B1B');
        $sheet->getStyle("A{$rowBanner}:D{$rowBanner}")
            ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        $rowKorekHeader = $rowBanner + 1;
        $sheet->setCellValue("A{$rowKorekHeader}", 'KOMPONEN / ITEM');
        $sheet->setCellValue("B{$rowKorekHeader}", 'NILAI INPUT');
        $sheet->setCellValue("C{$rowKorekHeader}", 'SATUAN');
        $sheet->setCellValue("D{$rowKorekHeader}", 'HASIL KONVERSI (KG)');
        $sheet->getStyle("A{$rowKorekHeader}:D{$rowKorekHeader}")->getFont()->setBold(true);

        $peti   = (float) ($this->korektor['peti'] ?? 0);
        $kiloan = (float) ($this->korektor['kiloan'] ?? 0);
        $sisa   = (float) ($this->korektor['sisa'] ?? 0);
        $bentes = (float) ($this->korektor['bentes'] ?? 0);

        $items = [
            ['1. Peti (@10 Kg/Pt)', $peti, 'Pt', $peti * 10],
            ['2. Kiloan', $kiloan, 'Kg', $kiloan],
            ['3. Sisa', $sisa, 'Kg', $sisa],
            ['4. Bentes (Retak)', $bentes, 'Kg', $bentes],
        ];

        $row = $rowKorekHeader + 1;
        foreach ($items as [$label, $nilai, $satuan, $konversi]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $nilai);
            $sheet->setCellValue("C{$row}", $satuan);
            $sheet->setCellValue("D{$row}", $konversi);
            $row++;
        }

        // ── 3 baris ringkasan, mengikuti tampilan kartu di UI ──
        $totalKg       = (float) ($this->korektor['total'] ?? 0);
        $dariKandangKg = (float) ($this->korektor['dariKandang'] ?? 0);
        $selisihKg     = (float) ($this->korektor['selisih'] ?? 0);
        $statusColor   = $this->mapFilamentColor($this->korektor['statusColor'] ?? 'success');

        // Baris "Total Korektor" — hijau, sama seperti sebelumnya
        $rowTotalKorektor = $row;
        $sheet->setCellValue("A{$rowTotalKorektor}", 'Total Korektor');
        $sheet->setCellValue("B{$rowTotalKorektor}", $totalKg);
        $sheet->setCellValue("C{$rowTotalKorektor}", 'Kg');
        $sheet->setCellValue("D{$rowTotalKorektor}", $totalKg);
        $sheet->getStyle("A{$rowTotalKorektor}:D{$rowTotalKorektor}")
            ->getFont()->setBold(true)->getColor()->setRGB('1F7A3D');
        $sheet->getStyle("A{$rowTotalKorektor}:D{$rowTotalKorektor}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2F0D9');

        // Baris "Dari Kandang" — biru, sesuai UI
        $rowDariKandang = $rowTotalKorektor + 1;
        $sheet->setCellValue("A{$rowDariKandang}", 'Dari Kandang');
        $sheet->setCellValue("B{$rowDariKandang}", $dariKandangKg);
        $sheet->setCellValue("C{$rowDariKandang}", 'Kg');
        $sheet->setCellValue("D{$rowDariKandang}", $dariKandangKg);
        $sheet->getStyle("A{$rowDariKandang}:D{$rowDariKandang}")
            ->getFont()->setBold(true)->getColor()->setRGB('2F5496');
        $sheet->getStyle("A{$rowDariKandang}:D{$rowDariKandang}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');

        // Baris "Selisih" — warna latar mengikuti status (hijau/oranye/merah),
        // TANPA teks label status seperti "Selisih Wajar"
        $rowSelisih = $rowDariKandang + 1;
        $sheet->setCellValue("A{$rowSelisih}", 'Selisih');
        $sheet->setCellValue("B{$rowSelisih}", $selisihKg);
        $sheet->setCellValue("C{$rowSelisih}", 'Kg');
        $sheet->setCellValue("D{$rowSelisih}", $selisihKg);
        $sheet->getStyle("A{$rowSelisih}:D{$rowSelisih}")
            ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$rowSelisih}:D{$rowSelisih}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);

        // Format angka
        $sheet->getStyle("B{$rowKorekHeader}:D{$rowDariKandang}")
            ->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle("B{$rowSelisih}:D{$rowSelisih}")
            ->getNumberFormat()->setFormatCode('+#,##0.0;-#,##0.0;0');

        $rowKorektorEnd = $rowSelisih; // dipakai untuk border di bawah

        // ── Border tabel utama & tabel korektor ───────────
        $sheet->getStyle("A{$rowGroup}:{$kolomTerakhir}{$rowTotal}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$rowBanner}:D{$rowKorektorEnd}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        for ($i = 1; $i <= $kolomTerakhirIx; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(14);
        }
    }
}
