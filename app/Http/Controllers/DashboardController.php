<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Facturacion;
use App\Models\Corte;
use App\Models\Sede;
use App\Models\Docente;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $corteActivo = Corte::where('estado', 1)->first();

        if (!$corteActivo) {
            return response()->json(['message' => 'No hay corte activo'], 400);
        }

        $sedeId = $request->sede_id;

        // Base query - TODOS (para docentes, montos globales, carga horaria)
        $queryAll = Facturacion::where('corte_id', $corteActivo->id);

        if ($sedeId) {
            $queryAll->whereHas('sedeCarrera', function($q) use ($sedeId) {
                $q->where('sede_id', $sedeId);
            });
        }

        // Query SOLO FACTURACION (para conteo de facturas y estados)
        $queryFacturacion = clone $queryAll;
        $queryFacturacion->where('tipo_contrato', 'FACTURACION');

        // Total de facturaciones por tipo de contrato (Global)
        $facturacionesPorTipo = (clone $queryAll)
            ->select('tipo_contrato', DB::raw('count(*) as total'))
            ->groupBy('tipo_contrato')
            ->get();

        // Facturaciones por estado de subida (solo FACTURACION)
        $facturacionesPorEstado = (clone $queryFacturacion)
            ->select('estado_subida', DB::raw('count(*) as total'))
            ->groupBy('estado_subida')
            ->get();

        // Facturaciones por sede (solo FACTURACION)
        $facturacionesPorSede = (clone $queryFacturacion)
            ->join('sede_carreras', 'facturacions.sede_carrera_id', '=', 'sede_carreras.id')
            ->join('sedes', 'sede_carreras.sede_id', '=', 'sedes.id')
            ->select('sedes.nombre as sede', DB::raw('count(*) as total'))
            ->groupBy('sedes.id', 'sedes.nombre')
            ->get();

        // Montos por sede (Global - para ver el dinero total real)
        $montosPorSede = (clone $queryAll)
            ->join('sede_carreras', 'facturacions.sede_carrera_id', '=', 'sede_carreras.id')
            ->join('sedes', 'sede_carreras.sede_id', '=', 'sedes.id')
            ->select('sedes.nombre as sede', DB::raw('SUM(monto) as total_monto'))
            ->groupBy('sedes.id', 'sedes.nombre')
            ->get();

        // Top 10 carreras (Global - para ver actividad real)
        $facturacionesPorCarrera = (clone $queryAll)
            ->join('sede_carreras', 'facturacions.sede_carrera_id', '=', 'sede_carreras.id')
            ->join('carreras', 'sede_carreras.carrera_id', '=', 'carreras.id')
            ->select('carreras.nombre as carrera', DB::raw('count(*) as total'))
            ->groupBy('carreras.id', 'carreras.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Montos totales por tipo de contrato (Global)
        $montosPorTipo = (clone $queryAll)
            ->select('tipo_contrato', DB::raw('SUM(monto) as total_monto'))
            ->groupBy('tipo_contrato')
            ->get();

        // Total de docentes únicos (Global)
        $totalDocentes = (clone $queryAll)
            ->distinct('docente_id')
            ->count('docente_id');

        // Resumen general
        $resumen = [
            'total_facturaciones' => (clone $queryFacturacion)->count(), // SOLO FACTURACION
            'total_docentes' => $totalDocentes, // GLOBAL
            'monto_total' => round((float) (clone $queryAll)->sum('monto'), 2), // GLOBAL
            'carga_horaria_total' => round((float) (clone $queryAll)->sum('carga_horaria'), 2), // GLOBAL
            'facturas_pendientes' => (clone $queryFacturacion)->whereNull('estado_subida')->count(),
            'facturas_aprobadas' => (clone $queryFacturacion)->where('estado_subida', 'APROBADO')->count(),
            'facturas_subidas' => (clone $queryFacturacion)->where('estado_subida', 'SUBIDA')->count(),
        ];

        return response()->json([
            'corte_activo' => $corteActivo,
            'resumen' => $resumen,
            'facturaciones_por_tipo' => $facturacionesPorTipo,
            'facturaciones_por_estado' => $facturacionesPorEstado,
            'facturaciones_por_sede' => $facturacionesPorSede,
            'montos_por_sede' => $montosPorSede,
            'facturaciones_por_carrera' => $facturacionesPorCarrera,
            'montos_por_tipo' => $montosPorTipo,
        ]);
    }
}
