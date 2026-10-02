<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturacion;
use App\Models\Corte;
use App\Models\SedeCarrera;
use App\Imports\DocentesImport;
use App\Imports\DocentesPracticaImport;
use App\Exports\FacturacionesExport;
use App\Exports\TemplatePracticasExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Services\FacturacionUpdateService;
use App\Services\FacturacionUploadService;
use App\Services\FacturacionPrintPackageService;

class FacturacionController extends Controller
{
    protected FacturacionUpdateService $updateService;
    protected FacturacionUploadService $uploadService;
    protected FacturacionPrintPackageService $printPackageService;

    public function __construct(
        FacturacionUpdateService $updateService,
        FacturacionUploadService $uploadService,
        FacturacionPrintPackageService $printPackageService
    ) {
        $this->updateService = $updateService;
        $this->uploadService = $uploadService;
        $this->printPackageService = $printPackageService;
    }

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
            'corte_id'        => 'required|exists:cortes,id',
            'file'            => 'required|file|mimes:xlsx,xls'
        ]);

        $corte = Corte::where('id', $request->corte_id)
            ->where('estado', 1)
            ->where('tipo_corte', 'PRACTICA')
            ->first();

        if (!$corte) {
            return response()->json(['message' => 'El corte seleccionado no es un corte de prácticas activo'], 400);
        }

        Excel::import(
            new DocentesPracticaImport(
                $request->sede_carrera_id,
                $corte->id
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

    /**
     * Public upload endpoint (respects closed cuts and approved locks).
     */
    public function uploadFactura(Request $request, Facturacion $facturacion)
    {
        $request->validate([
            'factura' => 'required|file|mimes:pdf|max:2048' // 2MB max
        ]);

        try {
            $file = $request->file('factura');
            $updatedFact = $this->uploadService->upload($facturacion, $file, false);
            return response()->json([
                'message' => 'Factura subida correctamente',
                'facturacion' => $updatedFact
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Private administrative upload endpoint (can bypass closed cuts and force replace approved invoices).
     */
    public function adminUploadFactura(Request $request, Facturacion $facturacion)
    {
        $request->validate([
            'factura' => 'required|file|mimes:pdf|max:2048', // 2MB max
            'comment' => 'nullable|string',
            'force'   => 'nullable|boolean'
        ]);

        try {
            $file = $request->file('factura');
            $comment = $request->comment;
            $force = $request->boolean('force');

            $updatedFact = $this->uploadService->upload($facturacion, $file, true, $comment, $force);
            return response()->json([
                'message' => 'Factura subida administrativamente de forma correcta',
                'facturacion' => $updatedFact
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
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

    /**
     * Administrative comprehensive update of docente and billing assignment.
     */
    public function update(Request $request, Facturacion $facturacion)
    {
        $request->validate([
            'nombres'                 => 'required|string',
            'apellidos'               => 'required|string',
            'ci'                      => 'required|string',
            'complemento'             => 'nullable|string|max:10',
            'correo'                  => 'nullable|email',
            'telefono'                => 'nullable|string',
            'sede_id'                 => 'required|exists:sedes,id',
            'carrera_id'              => 'required|exists:carreras,id',
            'corte_id'                => 'required|exists:cortes,id',
            'monto'                   => 'required|numeric',
            'carga_horaria'           => 'required|integer',
            'tipo_contrato'           => 'required|in:FACTURACION,RETENCION,AFILIACION',
            'estado_subida'           => 'nullable|string',
            'es_practica'             => 'nullable|boolean',
            'fecha_inicio_practica'   => 'nullable|date',
            'fecha_fin_practica'      => 'nullable|date',
            'materia_practica'        => 'nullable|string',
            'hospital_practica'       => 'nullable|string',
            'observaciones'           => 'nullable|string',
            'docente_estado'          => 'nullable|integer',
            'comment'                 => 'nullable|string',
            'force'                   => 'nullable|boolean',
        ]);

        try {
            $updatedFact = $this->updateService->update(
                $facturacion,
                $request->all(),
                true, // isAdmin
                $request->comment,
                $request->boolean('force')
            );

            return response()->json([
                'message' => 'Registro actualizado correctamente',
                'facturacion' => $updatedFact
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error interno al actualizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Administrative bulk update (updates Sede/Carrera for multiple assignments transaccionaly).
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:facturacions,id',
            'sede_id' => 'required|exists:sedes,id',
            'carrera_id' => 'required|exists:carreras,id',
            'comment' => 'nullable|string'
        ]);

        $updatedCount = 0;
        $errors = [];

        $facturaciones = Facturacion::whereIn('id', $request->ids)->get();

        foreach ($facturaciones as $facturacion) {
            try {
                $this->updateService->update(
                    $facturacion,
                    [
                        'sede_id' => $request->sede_id,
                        'carrera_id' => $request->carrera_id
                    ],
                    true, // isAdmin
                    $request->comment ?? 'Bulk Update Sede/Carrera',
                    true // force override for bulk
                );
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

    /**
     * Generate the consolidated print package PDF.
     */
    public function printPackage(Request $request)
    {
        $request->validate([
            'corte_id'      => 'required|exists:cortes,id',
            'sede_id'       => 'required|exists:sedes,id',
            'carrera_id'    => 'nullable|exists:carreras,id',
            'estado_subida' => 'nullable|string'
        ]);

        try {
            $pdfContent = $this->printPackageService->generatePackage($request->all());

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Consolidado_Facturas_' . now()->format('Ymd_His') . '.pdf"',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al generar compilado de impresión: ' . $e->getMessage()], 500);
        }
    }

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
     * Delete/destroy a facturacion record administratively.
     */
    public function destroy(Facturacion $facturacion, \App\Services\FacturacionAuditService $auditService)
    {
        // 1. Delete associated invoice file if it exists
        if ($facturacion->factura_path) {
            Storage::disk('public')->delete($facturacion->factura_path);
        }

        // 2. Audit log
        $auditService->logAction(
            'DELETE_FACTURACION_RECORD',
            null, // Pass null so it survives the database cascade delete
            $facturacion->toArray(),
            null,
            'Registro de facturación ID ' . $facturacion->id . ' de docente ' . ($facturacion->docente->nombre ?? '') . ' ' . ($facturacion->docente->apellidos ?? '') . ' eliminado por el administrador debido a carga errónea o corrección.'
        );

        // 3. Delete from DB
        $facturacion->delete();

        return response()->json([
            'message' => 'Registro de facturación eliminado correctamente.'
        ]);
    }

    /**
     * Generar el nombre de archivo estandarizado para el PDF de la factura:
     * {GESTION}_{CI}_{SEDE}_{CARRERA}_{CORTE}.pdf
     * Si no tiene gestión asociada, mantiene {CI}_{SEDE}_{CARRERA}_{CORTE}.pdf
     */
    public function generateFacturaFilename(Facturacion $facturacion): string
    {
        $facturacion->loadMissing(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte.gestion']);

        $ci = $facturacion->docente->ci ?? 'SIN_CI';
        $sedeIdentifier = $facturacion->sedeCarrera->sede->abreviacion ?? $facturacion->sedeCarrera->sede->id ?? 'SEDE';
        $carreraNombre = str_replace(' ', '_', $facturacion->sedeCarrera->carrera->nombre ?? 'CARRERA');
        $corteNombre = str_replace(' ', '_', $facturacion->corte->nombre ?? 'CORTE');

        $gestionPrefix = '';
        if ($facturacion->corte && $facturacion->corte->gestion && !empty($facturacion->corte->gestion->nombre)) {
            $gestionClean = str_replace(['/', ' '], ['-', '_'], trim($facturacion->corte->gestion->nombre));
            $gestionPrefix = $gestionClean . '_';
        }

        $filename = $gestionPrefix . $ci . '_' . $sedeIdentifier . '_' . $carreraNombre . '_' . $corteNombre . '.pdf';
        return preg_replace('/[^a-zA-Z0-9_\.-]/', '', $filename);
    }
}
