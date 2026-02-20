<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Facturacion;
use App\Models\Corte;
use App\Models\DatoFactura;
use App\Imports\DocentesImport;
use App\Services\PdfExtractorService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

use App\Models\SedeCarrera;
use App\Services\RezagadoService;
use App\Services\FacturaValidatorService;

/**
 * Controlador principal de Facturaciones
 * Maneja CRUD, importación Excel, upload de PDFs y aprobación/denegación
 *
 * Para exportaciones: ver FacturaExportController
 * Para rezagados: ver RezagadoController
 */
class FacturacionController extends Controller
{
    /**
     * Importa docentes y facturaciones desde archivo Excel
     */
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

    /**
     * Lista todas las facturaciones con filtros
     */
    public function getFacturaciones(Request $request)
    {
        // Marcar automáticamente como REZAGADO los registros de periodos cerrados
        RezagadoService::marcarRezagadosAutomaticamente();

        $query = Facturacion::with(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte', 'datoFactura']);

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

    /**
     * Sube una factura PDF y la valida automáticamente
     */
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

        // Verificar si el periodo de facturación está abierto
        // Excepción: si es REZAGADO, se permite subir (Opción B)
        $corte = $facturacion->corte;
        if (!$corte->isPeriodoFacturacionAbierto() && $facturacion->estado_subida !== 'REZAGADO') {
            $fechaFin = $corte->fecha_fin_facturacion
                ? $corte->fecha_fin_facturacion->format('d/m/Y')
                : 'no definida';
            return response()->json([
                'message' => "El periodo de facturación ha cerrado. La fecha límite fue: {$fechaFin}. Contacte al administrador si necesita ayuda."
            ], 400);
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
            'estado_subida' => 'SUBIDA',
            'errores_validacion' => null,
        ]);

        // Extraer datos del PDF automáticamente
        $datosExtraidos = null;
        $validacionResult = null;
        $estadoFinal = 'SUBIDA';
        $erroresValidacion = [];

        try {
            $pdfExtractor = new PdfExtractorService();
            $fullPath = Storage::disk('public')->path($path);
            $result = $pdfExtractor->extractFromInvoice($fullPath);

            if ($result['success'] && $result['data']) {
                // Eliminar datos anteriores si existen
                $facturacion->datoFactura()->delete();

                // Guardar nuevos datos extraídos
                $datosExtraidos = DatoFactura::create([
                    'facturacion_id' => $facturacion->id,
                    'nit_emisor' => $result['data']['nit_emisor'],
                    'razon_social_emisor' => $result['data']['razon_social_emisor'],
                    'nit_cliente' => $result['data']['nit_cliente'],
                    'razon_social_cliente' => $result['data']['razon_social_cliente'],
                    'numero_factura' => $result['data']['numero_factura'],
                    'codigo_autorizacion' => $result['data']['codigo_autorizacion'],
                    'fecha_factura' => $result['data']['fecha_factura'],
                    'monto_total' => $result['data']['monto_total'],
                    'texto_completo' => $result['data']['texto_completo'],
                    'qr_url' => $result['data']['qr_url'] ?? null,
                ]);

                // Validación automática
                $validator = new FacturaValidatorService();
                $facturacion->load('corte'); // Reload corte for validation
                $validacionResult = $validator->validar($facturacion, $datosExtraidos);

                $estadoFinal = $validator->determinarEstado($validacionResult['valido']);
                $erroresValidacion = $validacionResult['errores'];
            } else {
                // No se pudo extraer datos
                $erroresValidacion = ['No se pudieron extraer los datos del PDF. Verifique que el archivo sea legible.'];
                $validator = new FacturaValidatorService();
                $estadoFinal = $validator->determinarEstado(false);
            }
        } catch (\Exception $e) {
            // Si falla la extracción
            $erroresValidacion = ['Error al procesar el PDF: ' . $e->getMessage()];
            $validator = new FacturaValidatorService();
            $estadoFinal = $validator->determinarEstado(false);
        }

        // Actualizar estado final y errores
        // Guardamos los detalles completos para mostrar feedback visual persistente
        $detallesGuardar = null;
        if ($validacionResult && isset($validacionResult['detalles'])) {
            $detallesGuardar = $validacionResult['detalles'];
        } elseif (!empty($erroresValidacion)) {
            $detallesGuardar = $erroresValidacion;
        }

        // Si fue rechazado, eliminamos el archivo físico para forzar nueva subida
        if ($estadoFinal === 'RECHAZADO') {
            if ($facturacion->factura_path) {
                Storage::disk('public')->delete($facturacion->factura_path);
            }
            $facturacion->factura_path = null;
        }

        $facturacion->update([
            'estado_subida' => $estadoFinal,
            'errores_validacion' => $detallesGuardar,
            'factura_path' => $facturacion->factura_path,
        ]);

        // Preparar respuesta
        $response = [
            'message' => $this->getMensajeEstado($estadoFinal, $erroresValidacion),
            'estado' => $estadoFinal,
            'datos_extraidos' => $datosExtraidos,
            'validacion' => [
                'valido' => $validacionResult ? $validacionResult['valido'] : false,
                'errores' => $erroresValidacion,
                'detalles' => $validacionResult ? ($validacionResult['detalles'] ?? []) : []
            ],
            'facturacion' => $facturacion,
        ];

        // Si hay errores de validación, devolver 422
        if (!empty($erroresValidacion) && $estadoFinal === 'RECHAZADO') {
            return response()->json($response, 422);
        }

        return response()->json($response);
    }

    /**
     * Genera el mensaje según el estado de la factura
     */
    private function getMensajeEstado(string $estado, array $errores): string
    {
        switch ($estado) {
            case 'APROBADO':
                return '✅ Factura aprobada automáticamente. Todos los datos son correctos.';

            case 'RECHAZADO':
                return '❌ La factura tiene errores. Por favor, corrija su factura y vuelva a subirla.';

            case 'SUBIDA':
                if (!empty($errores)) {
                    return '⚠️ La factura fue subida pero requiere revisión manual debido a errores en la validación automática.';
                }
                return 'Factura subida correctamente. Pendiente de revisión.';

            default:
                return 'Factura procesada.';
        }
    }

    /**
     * Deniega una factura
     */
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

    /**
     * Aprueba una factura manualmente
     */
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
     * Actualiza la sede/carrera de una facturación
     */
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

        return response()->json(['message' => 'Facturación actualizada correctamente', 'facturacion' => $facturacion]);
    }

    /**
     * Actualiza sede/carrera de múltiples facturaciones
     */
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

    /**
     * Obtiene las facturaciones con sus datos extraídos del PDF
     */
    public function getDatosExtraidos(Request $request)
    {
        $query = Facturacion::with(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte', 'datoFactura'])
            ->whereNotNull('factura_path')
            ->whereHas('datoFactura');

        if ($request->corte_id) {
            $query->where('corte_id', $request->corte_id);

            // Filtrar en SQL: solo facturas subidas dentro del periodo
            $corte = Corte::find($request->corte_id);
            if ($corte && $corte->fecha_fin_facturacion) {
                $fechaFinFacturacion = $corte->fecha_fin_facturacion->endOfDay();
                $query->where(function ($q) use ($fechaFinFacturacion) {
                    $q->whereNull('fecha_subida')
                        ->orWhere('fecha_subida', '<=', $fechaFinFacturacion);
                });
            }
        }

        $facturaciones = $query->orderBy('updated_at', 'desc')->get();

        return response()->json($facturaciones->map(function ($f) {
            return [
                'id' => $f->id,
                'docente' => [
                    'ci' => $f->docente?->ci,
                    'nombre' => $f->docente?->nombre,
                    'apellidos' => $f->docente?->apellidos,
                ],
                'sede' => $f->sedeCarrera?->sede?->nombre,
                'carrera' => $f->sedeCarrera?->carrera?->nombre,
                'corte' => $f->corte?->nombre,
                'monto_excel' => $f->monto,
                'factura_path' => $f->factura_path,
                'estado_subida' => $f->estado_subida,
                'datos_extraidos' => $f->datoFactura ? [
                    'nit_emisor' => $f->datoFactura->nit_emisor,
                    'razon_social' => $f->datoFactura->razon_social_emisor,
                    'numero_factura' => $f->datoFactura->numero_factura,
                    'codigo_autorizacion' => $f->datoFactura->codigo_autorizacion,
                    'fecha_factura' => $f->datoFactura->fecha_factura?->format('d/m/Y'),
                    'monto_extraido' => $f->datoFactura->monto_total,
                ] : null,
            ];
        })->values());
    }
}
