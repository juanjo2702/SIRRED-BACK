<?php

namespace App\Exports;

use App\Models\DatoFactura;
use App\Models\Corte;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DatosRezagadosExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $corteId;
    protected $sedeNombre;
    protected $carreraNombre;

    public function __construct($corteId, $sedeNombre = null, $carreraNombre = null)
    {
        $this->corteId = $corteId;
        $this->sedeNombre = $sedeNombre;
        $this->carreraNombre = $carreraNombre;
    }

    public function collection()
    {
        // Get the corte to check the facturation period
        $corte = Corte::find($this->corteId);

        $query = DatoFactura::with(['facturacion.docente', 'facturacion.sedeCarrera.sede', 'facturacion.sedeCarrera.carrera', 'facturacion.corte'])
            ->whereHas('facturacion', function ($q) {
                $q->where('corte_id', $this->corteId)
                  ->where('tipo_contrato', 'FACTURACION');
            });

        $datos = $query->get();

        // Filter ONLY those who uploaded AFTER the facturation period (rezagados that uploaded late)
        if ($corte && $corte->fecha_fin_facturacion) {
            $fechaFinFacturacion = \Carbon\Carbon::parse($corte->fecha_fin_facturacion)->endOfDay();
            $datos = $datos->filter(function ($d) use ($fechaFinFacturacion) {
                // Only include if uploaded AFTER the period
                if (!$d->facturacion->fecha_subida) return false;
                $fechaSubida = \Carbon\Carbon::parse($d->facturacion->fecha_subida);
                return $fechaSubida->gt($fechaFinFacturacion);
            });
        } else {
            // If no period configured, return empty
            return collect([]);
        }

        // Apply frontend filters
        if ($this->sedeNombre) {
            $datos = $datos->filter(function ($d) {
                return $d->facturacion->sedeCarrera->sede->nombre === $this->sedeNombre;
            });
        }

        if ($this->carreraNombre) {
            $datos = $datos->filter(function ($d) {
                return $d->facturacion->sedeCarrera->carrera->nombre === $this->carreraNombre;
            });
        }

        return $datos->values();
    }

    public function headings(): array
    {
        return [
            'FECHA',
            'NroFACTURA',
            'NIT',
            'CUF',
            'NOMBRE',
            'Monto',
        ];
    }

    public function map($datoFactura): array
    {
        $fecha = $datoFactura->fecha_factura
            ? \Carbon\Carbon::parse($datoFactura->fecha_factura)->format('d/m/Y')
            : '';

        $docente = $datoFactura->facturacion->docente;
        $nombreCompleto = $docente
            ? trim($docente->nombre . ' ' . $docente->apellidos)
            : '';

        return [
            $fecha,
            $datoFactura->numero_factura ?? '',
            $datoFactura->nit_emisor ?? '',
            $datoFactura->codigo_autorizacion ?? '',
            $nombreCompleto,
            $datoFactura->monto_total ? number_format($datoFactura->monto_total, 2, '.', '') : '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF9800']
                ]
            ],
        ];
    }
}
