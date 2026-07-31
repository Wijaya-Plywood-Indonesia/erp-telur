<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ProduksiPakanExport implements FromArray, WithEvents
{
    /**
     * @param string $tanggal        Format: YYYY-MM-DD
     * @param array  $pakanMentah    Ambil langsung dari $this->mentahState di Page (key: nama, satuan, awal, masuk, p, l1, l2, akhir)
     * @param array  $pakanCampuran  Ambil langsung dari $this->campuranState di Page (key sama seperti di atas)
     */
    public function __construct(
        protected string $tanggal,
        protected array $pakanMentah = [],
        protected array $pakanCampuran = []
    ) {}

    /**
     * BUG FIX #1:
     * Laravel Excel butuh minimal satu "source concern" (FromArray/FromCollection/FromView)
     * supaya sheet benar-benar dibuat sebelum event AfterSheet berjalan.
     * Karena semua penulisan cell dilakukan manual di build(), array ini
     * sengaja dikosongkan — cukup untuk memastikan sheet ada & bisa ditulisi.
     */
    public function array(): array
    {
        return [['']];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->build($event->sheet->getDelegate());
            },
        ];
    }

    protected function build($sheet): void
    {
        $kolomAkhir = 'H';

        // ── 1. Judul Laporan ──────────────────────────────────────────
        $sheet->setCellValue('A1', 'LAPORAN PRODUKSI PAKAN');
        $sheet->mergeCells("A1:{$kolomAkhir}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)
            ->setColor((new Color())->setRGB('111827'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->setCellValue('A2', 'Tanggal Laporan: ' . date('d F Y', strtotime($this->tanggal)));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)
            ->getColor()->setRGB('4B5563');

        // ── Helper Styling Header ──────────────────────────────────────
        $applyHeaderStyle = function ($startRow) use ($sheet, $kolomAkhir) {
            $sheet->getStyle("A{$startRow}:{$kolomAkhir}{$startRow}")
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E293B');
            $sheet->getStyle("A{$startRow}:{$kolomAkhir}{$startRow}")
                ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

            $rowSub = $startRow + 1;
            $sheet->getStyle("A{$rowSub}:{$kolomAkhir}{$rowSub}")
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('334155');
            $sheet->getStyle("A{$rowSub}:{$kolomAkhir}{$rowSub}")
                ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A{$startRow}:{$kolomAkhir}{$rowSub}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        };

        // ── 2. Tabel Produksi Pakan Mentah ────────────────────────────
        $rowMentahHeader = 4;
        $sheet->setCellValue("A{$rowMentahHeader}", 'PRODUKSI PAKAN MENTAH');
        $sheet->mergeCells("A{$rowMentahHeader}:{$kolomAkhir}{$rowMentahHeader}");

        $rowMentahSub = 5;
        $sheet->setCellValue("A{$rowMentahSub}", 'NAMA BAHAN');
        $sheet->setCellValue("B{$rowMentahSub}", 'SATUAN');
        $sheet->setCellValue("C{$rowMentahSub}", 'STOK AWAL');
        $sheet->setCellValue("D{$rowMentahSub}", 'MASUK (+)');
        $sheet->setCellValue("E{$rowMentahSub}", 'KELUAR PLT (-)');
        $sheet->setCellValue("F{$rowMentahSub}", 'KELUAR L1 (-)');
        $sheet->setCellValue("G{$rowMentahSub}", 'KELUAR L2 (-)');
        $sheet->setCellValue("H{$rowMentahSub}", 'SISA AKHIR');

        $applyHeaderStyle($rowMentahHeader);

        $startMentahData = 6;
        $currentRow = $startMentahData;

        // BUG FIX #2: key disesuaikan dengan $mentahState di Page
        // (nama, satuan, awal, masuk, p, l1, l2, akhir) — bukan nama_bahan/stok_awal/keluar_pullet dst.
        foreach ($this->pakanMentah as $item) {
            $sheet->setCellValue("A{$currentRow}", $item['nama'] ?? '-');
            $sheet->setCellValue("B{$currentRow}", $item['satuan'] ?? 'Kg');
            $sheet->setCellValue("C{$currentRow}", (float) ($item['awal'] ?? 0));
            $sheet->setCellValue("D{$currentRow}", (float) ($item['masuk'] ?? 0));
            $sheet->setCellValue("E{$currentRow}", (float) ($item['p'] ?? 0));
            $sheet->setCellValue("F{$currentRow}", (float) ($item['l1'] ?? 0));
            $sheet->setCellValue("G{$currentRow}", (float) ($item['l2'] ?? 0));

            // Formula Sisa Akhir = Stok Awal + Masuk - (Keluar PLT + Keluar L1 + Keluar L2)
            $sheet->setCellValue("H{$currentRow}", "=C{$currentRow}+D{$currentRow}-SUM(E{$currentRow}:G{$currentRow})");

            $currentRow++;
        }

        // Kalau tidak ada data sama sekali, kasih 1 baris kosong biar formula total tidak error range invalid
        if ($currentRow === $startMentahData) {
            $sheet->setCellValue("A{$currentRow}", '(Tidak ada data)');
            $currentRow++;
        }

        $endMentahData = $currentRow - 1;

        // Baris Total Pakan Mentah
        $rowTotalMentah = $currentRow;
        $sheet->setCellValue("A{$rowTotalMentah}", 'TOTAL PAKAN MENTAH');
        $sheet->mergeCells("A{$rowTotalMentah}:B{$rowTotalMentah}");
        $sheet->setCellValue("C{$rowTotalMentah}", "=SUM(C{$startMentahData}:C{$endMentahData})");
        $sheet->setCellValue("D{$rowTotalMentah}", "=SUM(D{$startMentahData}:D{$endMentahData})");
        $sheet->setCellValue("E{$rowTotalMentah}", "=SUM(E{$startMentahData}:E{$endMentahData})");
        $sheet->setCellValue("F{$rowTotalMentah}", "=SUM(F{$startMentahData}:F{$endMentahData})");
        $sheet->setCellValue("G{$rowTotalMentah}", "=SUM(G{$startMentahData}:G{$endMentahData})");
        $sheet->setCellValue("H{$rowTotalMentah}", "=SUM(H{$startMentahData}:H{$endMentahData})");

        $sheet->getStyle("A{$rowTotalMentah}:{$kolomAkhir}{$rowTotalMentah}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$rowTotalMentah}:{$kolomAkhir}{$rowTotalMentah}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
        $sheet->getStyle("A{$rowTotalMentah}:{$kolomAkhir}{$rowTotalMentah}")
            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$rowTotalMentah}:{$kolomAkhir}{$rowTotalMentah}")
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        $sheet->getStyle("A{$rowMentahHeader}:{$kolomAkhir}{$rowTotalMentah}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B{$startMentahData}:B{$rowTotalMentah}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ── 3. Tabel Produksi Pakan Campuran ──────────────────────────
        $rowCampuranHeader = $rowTotalMentah + 3;
        $sheet->setCellValue("A{$rowCampuranHeader}", 'PRODUKSI PAKAN CAMPURAN');
        $sheet->mergeCells("A{$rowCampuranHeader}:{$kolomAkhir}{$rowCampuranHeader}");

        $rowCampuranSub = $rowCampuranHeader + 1;
        $sheet->setCellValue("A{$rowCampuranSub}", 'NAMA BAHAN');
        $sheet->setCellValue("B{$rowCampuranSub}", 'SATUAN');
        $sheet->setCellValue("C{$rowCampuranSub}", 'STOK AWAL');
        $sheet->setCellValue("D{$rowCampuranSub}", 'MASUK (+)');
        $sheet->setCellValue("E{$rowCampuranSub}", 'KELUAR PLT (-)');
        $sheet->setCellValue("F{$rowCampuranSub}", 'KELUAR L1 (-)');
        $sheet->setCellValue("G{$rowCampuranSub}", 'KELUAR L2 (-)');
        $sheet->setCellValue("H{$rowCampuranSub}", 'SISA AKHIR');

        $applyHeaderStyle($rowCampuranHeader);

        $startCampuranData = $rowCampuranSub + 1;
        $currentRow = $startCampuranData;

        foreach ($this->pakanCampuran as $item) {
            $sheet->setCellValue("A{$currentRow}", $item['nama'] ?? '-');
            $sheet->setCellValue("B{$currentRow}", $item['satuan'] ?? 'Kg');
            $sheet->setCellValue("C{$currentRow}", (float) ($item['awal'] ?? 0));
            $sheet->setCellValue("D{$currentRow}", (float) ($item['masuk'] ?? 0));
            $sheet->setCellValue("E{$currentRow}", (float) ($item['p'] ?? 0));
            $sheet->setCellValue("F{$currentRow}", (float) ($item['l1'] ?? 0));
            $sheet->setCellValue("G{$currentRow}", (float) ($item['l2'] ?? 0));

            $sheet->setCellValue("H{$currentRow}", "=C{$currentRow}+D{$currentRow}-SUM(E{$currentRow}:G{$currentRow})");

            $currentRow++;
        }

        if ($currentRow === $startCampuranData) {
            $sheet->setCellValue("A{$currentRow}", '(Tidak ada data)');
            $currentRow++;
        }

        $endCampuranData = $currentRow - 1;

        // Baris Total Pakan Campuran
        $rowTotalCampuran = $currentRow;
        $sheet->setCellValue("A{$rowTotalCampuran}", 'TOTAL PAKAN CAMPURAN');
        $sheet->mergeCells("A{$rowTotalCampuran}:B{$rowTotalCampuran}");
        $sheet->setCellValue("C{$rowTotalCampuran}", "=SUM(C{$startCampuranData}:C{$endCampuranData})");
        $sheet->setCellValue("D{$rowTotalCampuran}", "=SUM(D{$startCampuranData}:D{$endCampuranData})");
        $sheet->setCellValue("E{$rowTotalCampuran}", "=SUM(E{$startCampuranData}:E{$endCampuranData})");
        $sheet->setCellValue("F{$rowTotalCampuran}", "=SUM(F{$startCampuranData}:F{$endCampuranData})");
        $sheet->setCellValue("G{$rowTotalCampuran}", "=SUM(G{$startCampuranData}:G{$endCampuranData})");
        $sheet->setCellValue("H{$rowTotalCampuran}", "=SUM(H{$startCampuranData}:H{$endCampuranData})");

        $sheet->getStyle("A{$rowTotalCampuran}:{$kolomAkhir}{$rowTotalCampuran}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$rowTotalCampuran}:{$kolomAkhir}{$rowTotalCampuran}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
        $sheet->getStyle("A{$rowTotalCampuran}:{$kolomAkhir}{$rowTotalCampuran}")
            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$rowTotalCampuran}:{$kolomAkhir}{$rowTotalCampuran}")
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        $sheet->getStyle("A{$rowCampuranHeader}:{$kolomAkhir}{$rowTotalCampuran}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("B{$startCampuranData}:B{$rowTotalCampuran}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ── 4. Formatting Angka & Lebar Kolom ─────────────────────────
        $sheet->getStyle("C{$startMentahData}:H{$rowTotalMentah}")
            ->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("C{$startCampuranData}:H{$rowTotalCampuran}")
            ->getNumberFormat()->setFormatCode('#,##0.00');

        $sheet->getStyle("H{$startMentahData}:H{$endMentahData}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ECFDF5');
        $sheet->getStyle("H{$startCampuranData}:H{$endCampuranData}")
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ECFDF5');

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(10);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(16);
        $sheet->getColumnDimension('H')->setWidth(18);

        // Sembunyikan sisa cell placeholder dari array() supaya tidak kelihatan
        $sheet->setCellValue('A1', $sheet->getCell('A1')->getValue() ?: 'LAPORAN PRODUKSI PAKAN');
    }
}
