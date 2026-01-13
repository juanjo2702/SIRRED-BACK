<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Corte;
use App\Exports\FacturacionesExport;
use App\Exports\DatosFacturaExport;
use App\Exports\RezagadosExport;
use App\Exports\DatosRezagadosExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Controlador para exportaciones de facturas a Excel
 * Separado del FacturacionController para mantener responsabilidad única
 */
class FacturaExportController extends Controller
{
    /**
     * Exporta lista de facturaciones a Excel
     */
    public function exportFacturaciones(Request $request)
    {
        $corteId = $request->corte_id;
        $tipoContrato = $request->tipo_contrato;
        $estadoSubida = $request->estado_subida;
        $sedeNombre = $request->sede_nombre;
        $carreraNombre = $request->carrera_nombre;

        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Corte';

        $date = date('Y-m-d_His');
        $filename = "Facturas_{$corteName}_{$date}.xlsx";

        return Excel::download(
            new FacturacionesExport($corteId, $tipoContrato, $estadoSubida, $sedeNombre, $carreraNombre),
            $filename
        );
    }

    /**
     * Exporta los datos extraídos de las facturas a Excel
     */
    public function exportDatosExtraidos(Request $request)
    {
        $corteId = $request->corte_id;
        $sedeNombre = $request->sede_nombre;
        $carreraNombre = $request->carrera_nombre;

        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Corte';

        // Construir nombre descriptivo del archivo
        $nameParts = ['Aprobados', $corteName];

        if ($sedeNombre) {
            $nameParts[] = str_replace(' ', '_', $sedeNombre);
        }

        if ($carreraNombre) {
            $nameParts[] = str_replace(' ', '_', $carreraNombre);
        }

        $date = date('Y-m-d');
        $nameParts[] = $date;

        $filename = implode('_', $nameParts) . '.xlsx';

        return Excel::download(
            new DatosFacturaExport($corteId, $sedeNombre, $carreraNombre),
            $filename
        );
    }

    /**
     * Exporta la lista de rezagados a Excel
     */
    public function exportRezagados(Request $request)
    {
        $corteId = $request->corte_id;

        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Todos';

        $date = date('Y-m-d_His');
        $filename = "Rezagados_{$corteName}_{$date}.xlsx";

        return Excel::download(
            new RezagadosExport($corteId),
            $filename
        );
    }

    /**
     * Exporta los datos extraídos de facturas de rezagados que subieron tarde
     */
    public function exportDatosRezagados(Request $request)
    {
        $corteId = $request->corte_id;
        $sedeNombre = $request->sede_nombre;
        $carreraNombre = $request->carrera_nombre;

        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Corte';

        $date = date('Y-m-d_His');
        $filename = "DatosRezagados_{$corteName}_{$date}.xlsx";

        return Excel::download(
            new DatosRezagadosExport($corteId, $sedeNombre, $carreraNombre),
            $filename
        );
    }
}
