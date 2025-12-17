<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturacion;
use App\Models\Corte;
use Carbon\Carbon;

/**
 * Controlador para gestión de rezagados
 * Maneja facturas subidas fuera del periodo de facturación
 */
class RezagadoController extends Controller
{
    /**
     * Obtiene las facturaciones que subieron factura después del periodo (rezagados que sí subieron)
     */
    public function index(Request $request)
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

            $fechaFinFacturacion = Carbon::parse($corte->fecha_fin_facturacion)->endOfDay();

            // Filtrar directamente en SQL en lugar de en memoria
            $facturaciones = Facturacion::with(['docente', 'sedeCarrera.sede', 'sedeCarrera.carrera', 'corte', 'datoFactura'])
                ->where('corte_id', $request->corte_id)
                ->where('tipo_contrato', 'FACTURACION')
                ->whereNotNull('factura_path')
                ->whereNotNull('fecha_subida')
                ->where('fecha_subida', '>', $fechaFinFacturacion)
                ->get();

            return response()->json($this->formatFacturaciones($facturaciones));
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Formatea las facturaciones para la respuesta JSON
     */
    private function formatFacturaciones($facturaciones)
    {
        return $facturaciones->map(function ($f) {
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
                'fecha_subida' => $f->fecha_subida ? Carbon::parse($f->fecha_subida)->format('d/m/Y H:i') : null,
                'datos_extraidos' => $f->datoFactura ? [
                    'nit_emisor' => $f->datoFactura->nit_emisor,
                    'razon_social' => $f->datoFactura->razon_social_emisor,
                    'numero_factura' => $f->datoFactura->numero_factura,
                    'codigo_autorizacion' => $f->datoFactura->codigo_autorizacion,
                    'fecha_factura' => $f->datoFactura->fecha_factura ? Carbon::parse($f->datoFactura->fecha_factura)->format('d/m/Y') : null,
                    'monto_extraido' => $f->datoFactura->monto_total,
                ] : null,
            ];
        })->values();
    }
}
