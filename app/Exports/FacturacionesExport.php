<?php

namespace App\Exports;

use App\Models\Facturacion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FacturacionesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $corteId;
    protected $tipoContrato;
    protected $estadoSubida;
    protected $sedeNombre;
    protected $carreraNombre;

    public function __construct($corteId, $tipoContrato = null, $estadoSubida = null, $sedeNombre = null, $carreraNombre = null)
    {
        $this->corteId = $corteId;
        $this->tipoContrato = $tipoContrato;
        $this->estadoSubida = $estadoSubida;
        $this->sedeNombre = $sedeNombre;
        $this->carreraNombre = $carreraNombre;
    }

    public function collection()
    {
        $query = Facturacion::with(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->where('corte_id', $this->corteId);

        // Filter by tipo_contrato if provided
        if ($this->tipoContrato) {
            $query->where('tipo_contrato', $this->tipoContrato);
        }

        if ($this->estadoSubida !== null && $this->estadoSubida !== 'null') {
            $query->where('estado_subida', $this->estadoSubida);
        } elseif ($this->estadoSubida === 'null') {
            $query->whereNull('estado_subida');
        }

        $facturaciones = $query->get();

        // Apply frontend filters
        if ($this->sedeNombre) {
            $facturaciones = $facturaciones->filter(function ($f) {
                return $f->sedeCarrera->sede->nombre === $this->sedeNombre;
            });
        }

        if ($this->carreraNombre) {
            $facturaciones = $facturaciones->filter(function ($f) {
                return $f->sedeCarrera->carrera->nombre === $this->carreraNombre;
            });
        }

        return $facturaciones;
    }

    public function headings(): array
    {
        return [
            'APELLIDOS',
            'NOMBRE',
            'CI',
            'SEDE',
            'CARRERA',
            'TIPO CONTRATO',
            'MONTO (Bs.)',
            'CARGA HORARIA',
            'FECHA SUBIDA',
            'ESTADO'
        ];
    }

    public function map($facturacion): array
    {
        $fechaSubida = $facturacion->fecha_subida
            ? date('d/m/Y H:i', strtotime($facturacion->fecha_subida))
            : '-';

        $estado = match($facturacion->estado_subida) {
            null => 'Pendiente',
            'SUBIDA' => 'Subida',
            'APROBADO' => 'Aprobado',
            'DENEGADO' => 'Denegado',
            default => 'Desconocido'
        };

        return [
            $facturacion->docente->apellidos ?? '',
            $facturacion->docente->nombre ?? '',
            $facturacion->docente->ci ?? '',
            $facturacion->sedeCarrera->sede->nombre ?? '',
            $facturacion->sedeCarrera->carrera->nombre ?? '',
            $facturacion->tipo_contrato,
            number_format($facturacion->monto, 2, '.', ''),
            $facturacion->carga_horaria,
            $fechaSubida,
            $estado
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
