<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class IncomeRelationsExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithEvents
{
    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Contacto',
            'Concepto',
            'Monto',
            'Fecha de transacción',
            'Fecha de pago',
            'Categoria',
            'Tarjeta',
        ];
    }

    public function map($relation): array
    {
        return [
            $relation->contact->alias,
            $relation->fromTransaction->concept,
            $relation->amount,
            $relation->toTransaction && $relation->toTransaction->transaction_date
                ? Date::stringToExcel($relation->toTransaction->transaction_date)
                : null,
            $relation->fromTransaction && $relation->fromTransaction->transaction_date
                ? Date::stringToExcel($relation->fromTransaction->transaction_date)
                : null,
            optional($relation->toTransaction->category)->name,
            (optional($relation->toTransaction->card->bank)->name ?? '') . ' ' . (optional($relation->toTransaction->card)->numbers ?? ''),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Contacto
            'B' => 30, // Concepto
            'C' => 15, // Monto
            'D' => 18, // Fecha de transacción
            'E' => 18, // Fecha de pago
            'F' => 20, // Categoria
            'G' => 25, // Tarjeta
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastRow = $this->data->count() + 1; // +1 por encabezado
                $totalRow = $lastRow + 1;

                // Estilos para encabezado
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF3895CF']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
                    ],
                ];

                // Aplica estilo encabezado
                $sheet->getStyle("A1:G1")->applyFromArray($headerStyle);

                // Formato moneda para columna C (Monto)
                $sheet->getStyle("C2:C{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);

                // Formato fecha corta para columnas D y E (dd/mm/yyyy)
                $sheet->getStyle("D2:D{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DDMMYYYY);

                $sheet->getStyle("E2:E{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DDMMYYYY);

                // Bordes para todas las celdas con datos
                $sheet->getStyle("A1:G{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF000000'));

                // Alternar color de filas (banded rows)
                for ($row = 2; $row <= $lastRow; $row++) {
                    if ($row % 2 == 0) {
                        $sheet->getStyle("A{$row}:G{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF0F0F0'); // gris muy claro
                    }
                }

                // Fila total
                $sheet->setCellValue("B{$totalRow}", "Total:");
                $sheet->setCellValue("C{$totalRow}", "=SUM(C2:C{$lastRow})");

                // Estilo para fila total
                $sheet->getStyle("B{$totalRow}:C{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => 'FF000000']],
                    ],
                ]);
            },
        ];
    }
}
