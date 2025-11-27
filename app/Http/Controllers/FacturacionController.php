<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Facturacion;
use App\Models\Corte;
use App\Imports\DocentesImport;
use App\Exports\FacturacionesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class FacturacionController extends Controller
{
    public function uploadExcel(Request $request)
    {
        $request->validate([
            'sede_carrera_id' => 'required|exists:sede_carreras,id',
            'tipo_contrato' => 'required|in:FACTURACION,RETENCION,AFILIACION',
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $corteActivo = Corte::where('estado', 1)->first();
        if (!$corteActivo) {
            return response()->json(['message' => 'No hay corte activo'], 400);
        }

        Excel::import(
            new DocentesImport(
                $request->sede_carrera_id,
                $corteActivo->id,
                $request->tipo_contrato
            ),
            $request->file('file')
        );

        return response()->json(['message' => 'Datos importados correctamente']);
    }

    public function getFacturaciones(Request $request)
    {
        $query = Facturacion::with(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte']);

        if ($request->corte_id) {
            $query->where('corte_id', $request->corte_id);
        }

        if ($request->tipo_contrato) {
            $query->where('tipo_contrato', $request->tipo_contrato);
        }

        if ($request->has('estado_subida')) {
            if ($request->estado_subida === 'null') {
                $query->whereNull('estado_subida');
            } else {
                $query->where('estado_subida', $request->estado_subida);
            }
        }

        return $query->get();
    }

    public function uploadFactura(Request $request, Facturacion $facturacion)
    {
        // Eager load relationships
        $facturacion->load(['docente', 'sedeCarrera.carrera', 'corte']);

        $request->validate([
            'factura' => 'required|file|mimes:pdf|max:2048' // 2MB max
        ]);

        if ($facturacion->tipo_contrato !== 'FACTURACION') {
            return response()->json(['message' => 'Solo tipo FACTURACION puede subir facturas'], 400);
        }

        // Delete old file if exists
        if ($facturacion->factura_path) {
            Storage::disk('public')->delete($facturacion->factura_path);
        }

        $file = $request->file('factura');
        $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre);
        $filename = $facturacion->docente->ci . '_' . $carreraNombre . '_' . $facturacion->corte->nombre . '.pdf';
        $path = $file->storeAs('facturas', $filename, 'public');

        $facturacion->update([
            'factura_path' => $path,
            'fecha_subida' => now(),
            'estado_subida' => 'SUBIDA'
        ]);

        return response()->json(['message' => 'Factura subida correctamente']);
    }

    public function denyFactura(Facturacion $facturacion)
    {
        if ($facturacion->factura_path) {
            Storage::disk('public')->delete($facturacion->factura_path);
        }

        $facturacion->update([
            'factura_path' => null,
            'estado_subida' => 'DENEGADO'
        ]);

        return response()->json(['message' => 'Factura denegada']);
    }

    public function approveFactura(Facturacion $facturacion)
    {
        if ($facturacion->estado_subida !== 'SUBIDA') {
            return response()->json(['message' => 'Solo se pueden aprobar facturas en estado SUBIDA'], 400);
        }

        $facturacion->update([
            'estado_subida' => 'APROBADO'
        ]);

        return response()->json(['message' => 'Factura aprobada correctamente']);
    }

    public function exportFacturaciones(Request $request)
    {
        $corteId = $request->corte_id;
        $tipoContrato = $request->tipo_contrato;
        $estadoSubida = $request->estado_subida;
        $sedeNombre = $request->sede_nombre;
        $carreraNombre = $request->carrera_nombre;

        // Get corte name for filename
        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Corte';

        // Generate filename with date
        $date = date('Y-m-d_His');
        $filename = "Facturas_{$corteName}_{$date}.xlsx";

        return Excel::download(
            new FacturacionesExport($corteId, $tipoContrato, $estadoSubida, $sedeNombre, $carreraNombre),
            $filename
        );
    }
}
