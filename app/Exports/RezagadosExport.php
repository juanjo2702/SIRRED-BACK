<?php

namespace App\Exports;

use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RezagadosExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $corteId;

    public function __construct($corteId = null)
    {
        $this->corteId = $corteId;
    }

    public function collection()
    {
        $query = Facturacion::with(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->where('estado_subida', 'REZAGADO')
            ->where('tipo_contrato', 'FACTURACION');

        if ($this->corteId) {
            $query->where('corte_id', $this->corteId);
        }

        return $query->orderBy('id')->get();
    }

    public function headings(): array
    {
        return [
            'CI',
            'APELLIDOS',
            'NOMBRE',
            'SEDE',
            'CARRERA',
            'MONTO',
            'CARGA HORARIA',
            'CORTE'
        ];
    }

    public function map($facturacion): array
    {
        return [
            $facturacion->docente?->ci ?? '',
            $facturacion->docente?->apellidos ?? '',
            $facturacion->docente?->nombre ?? '',
            $facturacion->sedeCarrera?->sede?->nombre ?? '',
            $facturacion->sedeCarrera?->carrera?->nombre ?? '',
            $facturacion->monto ?? 0,
            $facturacion->carga_horaria ?? 0,
            $facturacion->corte?->nombre ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF9800']
                ]
            ],
        ];
    }
}
