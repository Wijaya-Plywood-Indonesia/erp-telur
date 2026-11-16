<?php

namespace App\Exports;

use App\Models\JurnalUmum;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurnalUmumExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnFormatting,
    WithEvents,
    ShouldAutoSize
{
    public function __construct(
        protected ?string $dari = null,
        protected ?string $sampai = null,
    ){}

    public function query()
    {
        $query = JurnalUmum::query()
            ->orderBy('tgl', 'asc')
            ->orderBy('jurnal', 'asc')
            ->orderBy('map', 'asc')      // Debit dulu, baru Kredit
            ->orderBy('no_akun', 'asc')
            ->orderBy('id', 'asc');

        if (!empty($this->dari)) {
            $query->whereDate('tgl', '>=', $this->dari);
        }
        if (!empty($this->sampai)) {
            $query->whereDate('tgl', '<=', $this->sampai);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No. Jurnal',
            'No. Akun',
            'Nama Akun',
            'Nama',
            'Keterangan',
            'Banyak',
            'Harga',
            'Debit',
            'Kredit',
        ];
    }

    public function map($row): array
    {
        // Logika sama dengan hitungTotal() di halaman Jurnal Umum
        $banyak = $row->banyak;
        $harga  = (float) $row->harga;
        $total  = ($banyak === null || $banyak === '' || (float) $banyak <= 0)
            ? $harga
            : (float) $banyak * $harga;

        $isDebit = strtolower((string) $row->map) === 'd';

        return [
            $row->tgl ? Date::dateTimeToExcel($row->tgl) : null,
            $row->jurnal,
            (string) $row->no_akun,
            $row->nama_akun,
            $row->nama,
            $row->keterangan,
            ($banyak !== null && (float) $banyak > 0) ? (float) $banyak : null,
            $harga,
            $isDebit ? $total : null,
            !$isDebit ? $total : null,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'C' => NumberFormat::FORMAT_TEXT,
            'G' => '#,##0.##',
            'H' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => 'solid',
                    'startColor' => ['rgb' => '1F4E78'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = $sheet->getHighestRow();
                $total = $last + 1;

                $sheet->setCellValue("H{$total}", 'TOTAL');
                $sheet->setCellValue("I{$total}", "=SUM(I2:I{$last})");
                $sheet->setCellValue("J{$total}", "=SUM(J2:J{$last})");

                $sheet->getStyle("A{$total}:J{$total}")->getFont()->setBold(true);
                $sheet->getStyle("I{$total}:J{$total}")
                    ->getNumberFormat()->setFormatCode('#,##0');
            },
        ];
    }
}