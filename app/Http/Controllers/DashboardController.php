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

        // Base query
        $query = Facturacion::where('corte_id', $corteActivo->id);

        if ($sedeId) {
            $query->whereHas('sedeCarrera', function($q) use ($sedeId) {
                $q->where('sede_id', $sedeId);
            });
        }

        // Total de facturaciones por tipo de contrato
        $facturacionesPorTipo = (clone $query)
            ->select('tipo_contrato', DB::raw('count(*) as total'))
            ->groupBy('tipo_contrato')
            ->get();

        // Facturaciones por estado de subida (solo FACTURACION)
        $facturacionesPorEstado = Facturacion::where('corte_id', $corteActivo->id)
            ->where('tipo_contrato', 'FACTURACION')
            ->when($sedeId, function($q) use ($sedeId) {
                $q->whereHas('sedeCarrera', function($sq) use ($sedeId) {
                    $sq->where('sede_id', $sedeId);
                });
            })
            ->select('estado_subida', DB::raw('count(*) as total'))
            ->groupBy('estado_subida')
            ->get();

        // Facturaciones por sede
        $facturacionesPorSede = Facturacion::where('corte_id', $corteActivo->id)
            ->join('sede_carreras', 'facturacions.sede_carrera_id', '=', 'sede_carreras.id')
            ->join('sedes', 'sede_carreras.sede_id', '=', 'sedes.id')
            ->when($sedeId, function($q) use ($sedeId) {
                $q->where('sedes.id', $sedeId);
            })
            ->select('sedes.nombre as sede', DB::raw('count(*) as total'))
            ->groupBy('sedes.id', 'sedes.nombre')
            ->get();

        // Top 10 carreras con más facturaciones
        $facturacionesPorCarrera = Facturacion::where('corte_id', $corteActivo->id)
            ->join('sede_carreras', 'facturacions.sede_carrera_id', '=', 'sede_carreras.id')
            ->join('carreras', 'sede_carreras.carrera_id', '=', 'carreras.id')
            ->when($sedeId, function($q) use ($sedeId) {
                $q->where('sede_carreras.sede_id', $sedeId);
            })
            ->select('carreras.nombre as carrera', DB::raw('count(*) as total'))
            ->groupBy('carreras.id', 'carreras.nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Montos totales por tipo de contrato
        $montosPorTipo = (clone $query)
            ->select('tipo_contrato', DB::raw('SUM(monto) as total_monto'))
            ->groupBy('tipo_contrato')
            ->get();

        // Total de docentes únicos
        $totalDocentes = (clone $query)
            ->distinct('docente_id')
            ->count('docente_id');

        // Resumen general
        $resumen = [
            'total_facturaciones' => (clone $query)->count(),
            'total_docentes' => $totalDocentes,
            'monto_total' => (clone $query)->sum('monto'),
            'carga_horaria_total' => (clone $query)->sum('carga_horaria'),
            'facturas_pendientes' => Facturacion::where('corte_id', $corteActivo->id)
                ->where('tipo_contrato', 'FACTURACION')
                ->whereNull('estado_subida')
                ->when($sedeId, function($q) use ($sedeId) {
                    $q->whereHas('sedeCarrera', function($sq) use ($sedeId) {
                        $sq->where('sede_id', $sedeId);
                    });
                })
                ->count(),
            'facturas_aprobadas' => Facturacion::where('corte_id', $corteActivo->id)
                ->where('tipo_contrato', 'FACTURACION')
                ->where('estado_subida', 'APROBADO')
                ->when($sedeId, function($q) use ($sedeId) {
                    $q->whereHas('sedeCarrera', function($sq) use ($sedeId) {
                        $sq->where('sede_id', $sedeId);
                    });
                })
                ->count(),
            'facturas_subidas' => Facturacion::where('corte_id', $corteActivo->id)
                ->where('tipo_contrato', 'FACTURACION')
                ->where('estado_subida', 'SUBIDA')
                ->when($sedeId, function($q) use ($sedeId) {
                    $q->whereHas('sedeCarrera', function($sq) use ($sedeId) {
                        $sq->where('sede_id', $sedeId);
                    });
                })
                ->count(),
        ];

        return response()->json([
            'corte_activo' => $corteActivo,
            'resumen' => $resumen,
            'facturaciones_por_tipo' => $facturacionesPorTipo,
            'facturaciones_por_estado' => $facturacionesPorEstado,
            'facturaciones_por_sede' => $facturacionesPorSede,
            'facturaciones_por_carrera' => $facturacionesPorCarrera,
            'montos_por_tipo' => $montosPorTipo,
        ]);
    }
}
