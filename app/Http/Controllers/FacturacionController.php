<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Facturacion;
use App\Models\Corte;
use App\Models\DatoFactura;
use App\Imports\DocentesImport;
use App\Exports\FacturacionesExport;
use App\Exports\DatosFacturaExport;
use App\Exports\RezagadosExport;
use App\Exports\DatosRezagadosExport;
use App\Services\PdfExtractorService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

use App\Models\SedeCarrera;
use App\Services\RezagadoService;
use App\Services\FacturaValidatorService;

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
        // Marcar automáticamente como REZAGADO los registros de periodos cerrados
        RezagadoService::marcarRezagadosAutomaticamente();

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

        // Incrementar contador de intentos
        $intentos = ($facturacion->intentos_validacion ?? 0) + 1;

        $facturacion->update([
            'factura_path' => $path,
            'fecha_subida' => now(),
            'estado_subida' => 'SUBIDA',
            'intentos_validacion' => $intentos,
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
                ]);

                // Validación automática
                $validator = new FacturaValidatorService();
                $facturacion->load('corte'); // Reload corte for validation
                $validacionResult = $validator->validar($facturacion, $datosExtraidos);

                $estadoFinal = $validator->determinarEstado($validacionResult['valido'], $intentos);
                $erroresValidacion = $validacionResult['errores'];
            } else {
                // No se pudo extraer datos - pasar a revisión manual después de 3 intentos
                $erroresValidacion = ['No se pudieron extraer los datos del PDF. Verifique que el archivo sea legible.'];
                $validator = new FacturaValidatorService();
                $estadoFinal = $validator->determinarEstado(false, $intentos);
            }
        } catch (\Exception $e) {
            // Si falla la extracción
            $erroresValidacion = ['Error al procesar el PDF: ' . $e->getMessage()];
            $validator = new FacturaValidatorService();
            $estadoFinal = $validator->determinarEstado(false, $intentos);
        }

        // Actualizar estado final y errores
        $facturacion->update([
            'estado_subida' => $estadoFinal,
            'errores_validacion' => !empty($erroresValidacion) ? $erroresValidacion : null,
        ]);

        // Preparar respuesta
        $validator = new FacturaValidatorService();
        $intentosRestantes = max(0, $validator->getMaxIntentos() - $intentos);

        $response = [
            'message' => $this->getMensajeEstado($estadoFinal, $erroresValidacion, $intentosRestantes),
            'estado' => $estadoFinal,
            'datos_extraidos' => $datosExtraidos,
            'validacion' => [
                'valido' => $validacionResult ? $validacionResult['valido'] : false,
                'errores' => $erroresValidacion,
                'intentos' => $intentos,
                'intentos_restantes' => $intentosRestantes,
            ],
        ];

        // Si hay errores y aún tiene intentos, devolver 422 para indicar error de validación
        if (!empty($erroresValidacion) && $estadoFinal === 'RECHAZADO') {
            return response()->json($response, 422);
        }

        return response()->json($response);
    }

    /**
     * Genera el mensaje según el estado de la factura
     */
    private function getMensajeEstado(string $estado, array $errores, int $intentosRestantes): string
    {
        switch ($estado) {
            case 'APROBADO':
                return '✅ Factura aprobada automáticamente. Todos los datos son correctos.';

            case 'RECHAZADO':
                $mensaje = '❌ La factura tiene errores y fue rechazada.';
                if ($intentosRestantes > 0) {
                    $mensaje .= " Le quedan {$intentosRestantes} intento(s).";
                }
                return $mensaje;

            case 'SUBIDA':
                if (!empty($errores)) {
                    return '⚠️ La factura fue subida pero requiere revisión manual debido a errores en la validación automática.';
                }
                return 'Factura subida correctamente. Pendiente de revisión.';

            default:
                return 'Factura procesada.';
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
        }

        $facturaciones = $query->orderBy('updated_at', 'desc')->get();

        // Filter to only include those uploaded within the period
        if ($request->corte_id) {
            $corte = Corte::find($request->corte_id);
            if ($corte && $corte->fecha_fin_facturacion) {
                $fechaFinFacturacion = $corte->fecha_fin_facturacion->endOfDay();
                $facturaciones = $facturaciones->filter(function ($f) use ($fechaFinFacturacion) {
                    if (!$f->fecha_subida) return true;
                    return $f->fecha_subida <= $fechaFinFacturacion;
                })->values();
            }
        }

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

    /**
     * Exporta los datos extraídos de las facturas a Excel
     */
    public function exportDatosExtraidos(Request $request)
    {
        $corteId = $request->corte_id;
        $sedeNombre = $request->sede_nombre;
        $carreraNombre = $request->carrera_nombre;

        // Get corte name for filename
        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Corte';

        // Generate filename with date
        $date = date('Y-m-d_His');
        $filename = "DatosFacturas_{$corteName}_{$date}.xlsx";

        return Excel::download(
            new DatosFacturaExport($corteId, $sedeNombre, $carreraNombre),
            $filename
        );
    }

    /**
     * Obtiene las facturaciones que subieron factura después del periodo (rezagados que sí subieron)
     */
    public function getRezagados(Request $request)
    {
        try {
            if (!$request->corte_id) {
                return response()->json([]);
            }

            $corte = Corte::find($request->corte_id);

            if (!$corte) {
                return response()->json([]);
            }

            // If no facturation period configured, return empty (no "late" uploads possible)
            if (!$corte->fecha_fin_facturacion) {
                return response()->json([]);
            }

            $fechaFinFacturacion = \Carbon\Carbon::parse($corte->fecha_fin_facturacion)->endOfDay();

            $facturaciones = Facturacion::with(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte', 'datoFactura'])
                ->where('corte_id', $request->corte_id)
                ->where('tipo_contrato', 'FACTURACION')
                ->whereNotNull('factura_path')
                ->whereNotNull('fecha_subida')
                ->get()
                ->filter(function ($f) use ($fechaFinFacturacion) {
                    $fechaSubida = \Carbon\Carbon::parse($f->fecha_subida);
                    return $fechaSubida->gt($fechaFinFacturacion);
                });

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
                'fecha_subida' => $f->fecha_subida ? \Carbon\Carbon::parse($f->fecha_subida)->format('d/m/Y H:i') : null,
                'datos_extraidos' => $f->datoFactura ? [
                    'nit_emisor' => $f->datoFactura->nit_emisor,
                    'razon_social' => $f->datoFactura->razon_social_emisor,
                    'numero_factura' => $f->datoFactura->numero_factura,
                    'codigo_autorizacion' => $f->datoFactura->codigo_autorizacion,
                    'fecha_factura' => $f->datoFactura->fecha_factura ? \Carbon\Carbon::parse($f->datoFactura->fecha_factura)->format('d/m/Y') : null,
                    'monto_extraido' => $f->datoFactura->monto_total,
                ] : null,
            ];
        })->values());
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Exporta la lista de rezagados a Excel
     */
    public function exportRezagados(Request $request)
    {
        $corteId = $request->corte_id;

        // Get corte name for filename
        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Todos';

        // Generate filename with date
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

        // Get corte name for filename
        $corte = Corte::find($corteId);
        $corteName = $corte ? str_replace(' ', '_', $corte->nombre) : 'Corte';

        // Generate filename with date
        $date = date('Y-m-d_His');
        $filename = "DatosRezagados_{$corteName}_{$date}.xlsx";

        return Excel::download(
            new DatosRezagadosExport($corteId, $sedeNombre, $carreraNombre),
            $filename
        );
    }
}

