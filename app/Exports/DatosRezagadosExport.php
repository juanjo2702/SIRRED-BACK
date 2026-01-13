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
    protected $rowNumber = 0;

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
                    ->where('tipo_contrato', 'FACTURACION')
                    ->where('estado_subida', 'APROBADO'); // Solo exportar rezagados aprobados
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
            'No',
            'NIT',
            'Razón Social Proveedor',
            'Código Autorización',
            'Número',
            'Número',
            'Fecha FecDUI/DIM Compra',
            'ImporteTotal Compra',
            'Importe',
            'IEHD',
            'IPJ',
            'TASAS',
            'Otros No Sujeto',
            'Exentos',
            'Tasas',
            'Subtotal',
            'Desctos/Bonif',
            'GIFT CARD',
            'Importe Base Crédito',
            'Crédito Fiscal',
            'Tipo Compra',
            'Código de Control',
        ];
    }

    public function map($datoFactura): array
    {
        $this->rowNumber++;

        // Get fecha formatted
        $fecha = $datoFactura->fecha_factura
            ? \Carbon\Carbon::parse($datoFactura->fecha_factura)->format('d/m/Y')
            : '';

        // Get docente info
        $docente = $datoFactura->facturacion->docente;
        $razonSocial = $docente
            ? trim($docente->apellidos . ' ' . $docente->nombre)
            : '';

        // Get tipo_compra from docente (default to 1 if not set)
        $tipoCompra = $docente->tipo_compra ?? 1;

        // Get monto total (importe total compra)
        $importeTotalCompra = $datoFactura->monto_total ?? 0;

        // Calculate values based on tipo_compra
        // Tipo 1: Normal - Subtotal e Importe Base Crédito = ImporteTotal, Exentos = 0
        // Tipo 2: Exento - Exentos = ImporteTotal, Subtotal e Importe Base Crédito = 0

        if ($tipoCompra == 1) {
            $exentos = 0.00;
            $subtotal = $importeTotalCompra;
            $importeBaseCredito = $importeTotalCompra;
        } else {
            // tipo_compra == 2
            $exentos = $importeTotalCompra;
            $subtotal = 0.00;
            $importeBaseCredito = 0.00;
        }

        // Crédito Fiscal = 13% de Importe Base Crédito
        $creditoFiscal = $importeBaseCredito * 0.13;

        // Format numbers with 2 decimals and comma as decimal separator
        $formatNumber = function ($value) {
            return number_format($value, 2, ',', '');
        };

        return [
            $this->rowNumber,
            $datoFactura->nit_emisor ?? '',
            $razonSocial,
            $datoFactura->codigo_autorizacion ?? '',
            $datoFactura->numero_factura ?? '',
            1, // Número columna F - siempre 1
            $fecha,
            $formatNumber($importeTotalCompra),
            $formatNumber(0), // Importe - siempre 0,00
            $formatNumber(0), // IEHD - siempre 0,00
            $formatNumber(0), // IPJ - siempre 0,00
            $formatNumber(0), // TASAS - siempre 0,00
            $formatNumber(0), // Otros No Sujeto - siempre 0,00
            $formatNumber($exentos),
            $formatNumber(0), // Tasas - siempre 0,00
            $formatNumber($subtotal),
            $formatNumber(0), // Desctos/Bonif - siempre 0,00
            $formatNumber(0), // GIFT CARD - siempre 0,00
            $formatNumber($importeBaseCredito),
            $formatNumber($creditoFiscal),
            $tipoCompra,
            '0', // Código de Control - siempre 0
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF9800']
                ]
            ],
        ];
    }
}
