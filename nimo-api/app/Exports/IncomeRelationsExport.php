<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class IncomeRelationsExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithEvents
{
    protected $data;
    protected $alias;
    protected $month;
    protected $year;

    public function __construct(Collection $data, string $alias, int $month, int $year)
    {
        $this->data = $data;
        $this->alias = $alias;
        $this->month = $month;
        $this->year = $year;
    }

    public function collection()
    {
        return $this->data;
    }

    public function monthName()
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        return $months[$this->month] ?? '';
    }

    public function headings(): array
    {
        return [
            ['Cuentas Contacto | Mes de Año'],
            ['Concepto', 'Monto', 'Fecha', 'Pago', 'Categoria', 'Tarjeta'],
        ];
    }

    public function map($relation): array
    {
        return [
            $relation->fromTransaction->concept,
            $relation->fromTransaction->amount,
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
            'A' => 36,
            'B' => 15,
            'C' => 15,
            'D' => 15,
            'E' => 20,
            'F' => 25,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $dataRowStart = 3;
                $lastRow = $this->data->count() + $dataRowStart - 1;
                $totalRow = $lastRow + 1;
                $full = $lastRow + 10;

                // Insertar imagen (favicon)
                $drawing = new Drawing();
                $drawing->setName('Logo');
                $drawing->setDescription('Logo');
                $drawing->setPath(public_path('storage/favicon.png'));
                $drawing->setHeight(48); // Ajusta el alto a 35px aprox.
                $drawing->setCoordinates('C1');
                $drawing->setOffsetX(40);
                $drawing->setOffsetY(6);
                $drawing->setWorksheet($sheet);
                $sheet->getRowDimension(1)->setRowHeight(42);
                $sheet->getRowDimension(2)->setRowHeight(24); // Ajuste del alto de la fila

                for ($i = $dataRowStart; $i <= $full; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(20); // Ajuste del alto de las filas de datos
                }


                // Estilo encabezados (fila 2)
                $sheet->getStyle("A2:C2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0f172a']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
                    ],
                ]);
                $sheet->getStyle("D2:F2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'ff334155']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
                    ],
                ]);

                // Formato moneda
                $sheet->getStyle("B{$dataRowStart}:B{$full}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD);

                // Formato fecha
                foreach (['C', 'D'] as $col) {
                    $sheet->getStyle("{$col}{$dataRowStart}:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DMMINUS);
                }

                // Bordes de la tabla
                $sheet->getStyle("A2:F{$lastRow}")
                    ->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));

                // Alternar color de filas
                for ($row = $dataRowStart; $row <= $lastRow; $row++) {
                    if ($row % 2 == 0) {
                        $sheet->getStyle("A{$row}:F{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF0F0F0');
                    }
                }

                // Subtotal
                $sheet->setCellValue("A{$totalRow}", "Subtotal:");
                $sheet->setCellValue("B{$totalRow}", "=SUM(B{$dataRowStart}:B{$lastRow})");

                $sheet->getStyle("A{$totalRow}:B{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => 'FF000000']],
                    ],
                ]);

                // Título
                $sheet->mergeCells('A1:B1');
                $sheet->setCellValue('A1', 'Cuentas ' . $this->alias . '  | ' . $this->monthName() . ' ' . $this->year);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF000000']],
                    'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                ]);
            },
        ];
    }
}
