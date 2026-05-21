<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;

class TemplatePracticasExport implements FromArray, WithEvents, ShouldAutoSize
{
    public function array(): array
    {
        return [
            ['', 'No.', 'APELLIDOS Y NOMBRES', '', 'C.I.', 'FECHA DE INICIO', 'FECHA DE FINALIZACION', 'MATERIA', 'HOSPITAL', 'IMPORTE A PAGAR'],
            ['', 1, 'PARICAGUA VALDIVIA', 'ANEL KAREN', '5187708', '09/02/2026', '28/02/2026', 'SEMIOLOGÍA I', 'HOSPITAL BENIGNO SANCHEZ', '1600'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                // Merge the header for Apellidos y Nombres to match exactly the user's template
                $event->sheet->getDelegate()->mergeCells('C1:D1');
                
                // Style the header row
                $event->sheet->getDelegate()->getStyle('A1:J1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ]
                ]);
            },
        ];
    }
}
