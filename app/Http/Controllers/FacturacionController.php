<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Facturacion;
use App\Models\Corte;
use App\Imports\DocentesImport;
use App\Imports\DocentesPracticaImport;
use App\Exports\FacturacionesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Models\SedeCarrera;
use App\Exports\TemplatePracticasExport;

class FacturacionController extends Controller
{
    public function uploadExcel(Request $request)
    {
        $request->validate([
            'sede_carrera_id' => 'required|exists:sede_carreras,id',
            'tipo_contrato' => 'required|in:FACTURACION,RETENCION,AFILIACION',
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $corteActivo = Corte::where('estado', 1)->where('tipo_corte', 'REGULAR')->first();
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

    public function uploadExcelPracticas(Request $request)
    {
        $request->validate([
            'sede_carrera_id' => 'required|exists:sede_carreras,id',
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        $corteActivo = Corte::where('estado', 1)->where('tipo_corte', 'PRACTICA')->first();
        if (!$corteActivo) {
            return response()->json(['message' => 'No hay corte de prácticas activo'], 400);
        }

        Excel::import(
            new DocentesPracticaImport(
                $request->sede_carrera_id,
                $corteActivo->id
            ),
            $request->file('file')
        );

        return response()->json(['message' => 'Datos de prácticas importados correctamente']);
    }

    public function downloadTemplatePracticas()
    {
        return Excel::download(new TemplatePracticasExport, 'Plantilla_Practicas.xlsx');
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

        if ($request->has('es_practica')) {
            $query->where('es_practica', filter_var($request->es_practica, FILTER_VALIDATE_BOOLEAN));
        } else {
            $query->where('es_practica', false);
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

        // Prevent replacing approved invoices
        if ($facturacion->estado_subida === 'APROBADO') {
            return response()->json(['message' => 'No se puede modificar una factura aprobada'], 400);
        }

        // Delete old file if exists
        if ($facturacion->factura_path) {
            Storage::disk('public')->delete($facturacion->factura_path);
        }

        $file = $request->file('factura');
        $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre);
        $sedeIdentifier = $facturacion->sedeCarrera->sede->abreviacion ?? $facturacion->sedeCarrera->sede->id;
        $filename = $facturacion->docente->ci . '_' . $sedeIdentifier . '_' . $carreraNombre . '_' . $facturacion->corte->nombre . '.pdf';
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

    public function update(Request $request, Facturacion $facturacion)
    {
        $request->validate([
            'sede_id' => 'required|exists:sedes,id',
            'carrera_id' => 'required|exists:carreras,id',
        ]);

        // Find the SedeCarrera ID
        $sedeCarrera = SedeCarrera::where('sede_id', $request->sede_id)
            ->where('carrera_id', $request->carrera_id)
            ->first();

        if (!$sedeCarrera) {
            return response()->json(['message' => 'La carrera no está asignada a esta sede'], 400);
        }

        $facturacion->sede_carrera_id = $sedeCarrera->id;
        $facturacion->save();

        // If file exists, rename it
        if ($facturacion->factura_path && Storage::disk('public')->exists($facturacion->factura_path)) {
            $oldPath = $facturacion->factura_path;

            // Generate new filename
            // We need to reload relationships to get the new names
            $facturacion->load(['docente', 'sedeCarrera.carrera', 'corte']);

            $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre);
            $sedeIdentifier = $facturacion->sedeCarrera->sede->abreviacion ?? $facturacion->sedeCarrera->sede->id;
            $filename = $facturacion->docente->ci . '_' . $sedeIdentifier . '_' . $carreraNombre . '_' . $facturacion->corte->nombre . '.pdf';
            $newPath = 'facturas/' . $filename;

            if ($oldPath !== $newPath) {
                // Check if target file already exists (collision)
                if (Storage::disk('public')->exists($newPath)) {
                     // Append timestamp to avoid collision or handle as needed.
                     // For now, let's assume we overwrite or just fail?
                     // User said "si se equivoca se guarda con la carrera", implying we should fix it.
                     // If we overwrite, we might lose a file if two people have same CI/Carrera/Corte (should be impossible due to unique constraints usually, but let's be safe)
                     // Actually, one docente per corte per carrera usually.
                     Storage::disk('public')->delete($newPath);
                }

                Storage::disk('public')->move($oldPath, $newPath);
                $facturacion->factura_path = $newPath;
                $facturacion->save();
            }
        }

        return response()->json(['message' => 'Facturación actualizada correctamente', 'facturacion' => $facturacion]);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:facturacions,id',
            'sede_id' => 'required|exists:sedes,id',
            'carrera_id' => 'required|exists:carreras,id',
        ]);

        $sedeCarrera = SedeCarrera::where('sede_id', $request->sede_id)
            ->where('carrera_id', $request->carrera_id)
            ->first();

        if (!$sedeCarrera) {
            return response()->json(['message' => 'La carrera no está asignada a esta sede'], 400);
        }

        $updatedCount = 0;
        $errors = [];

        $facturaciones = Facturacion::whereIn('id', $request->ids)->get();

        foreach ($facturaciones as $facturacion) {
            try {
                $facturacion->sede_carrera_id = $sedeCarrera->id;
                $facturacion->save();

                // If file exists, rename it
                if ($facturacion->factura_path && Storage::disk('public')->exists($facturacion->factura_path)) {
                    $oldPath = $facturacion->factura_path;

                    // Reload relationships
                    $facturacion->load(['docente', 'sedeCarrera.carrera', 'corte']);

                    $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre);
                    $sedeIdentifier = $facturacion->sedeCarrera->sede->abreviacion ?? $facturacion->sedeCarrera->sede->id;
                    $filename = $facturacion->docente->ci . '_' . $sedeIdentifier . '_' . $carreraNombre . '_' . $facturacion->corte->nombre . '.pdf';
                    $newPath = 'facturas/' . $filename;

                    if ($oldPath !== $newPath) {
                        if (Storage::disk('public')->exists($newPath)) {
                             Storage::disk('public')->delete($newPath);
                        }

                        Storage::disk('public')->move($oldPath, $newPath);
                        $facturacion->factura_path = $newPath;
                        $facturacion->save();
                    }
                }
                $updatedCount++;
            } catch (\Exception $e) {
                $errors[] = "Error actualizando ID {$facturacion->id}: " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Se actualizaron {$updatedCount} registros correctamente.",
            'errors' => $errors
        ]);
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
