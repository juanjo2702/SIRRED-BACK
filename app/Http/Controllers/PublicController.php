<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Docente;
use App\Models\Corte;
use App\Services\RezagadoService;

class PublicController extends Controller
{
    public function searchByCI(Request $request)
    {
        $request->validate(['ci' => 'required']);

        // Marcar automáticamente como REZAGADO los registros de periodos cerrados
        RezagadoService::marcarRezagadosAutomaticamente();

        $cortesActivos = Corte::where('estado', 1)->get();

        if ($cortesActivos->isEmpty()) {
            return response()->json(['message' => 'No hay ningún corte activo en este momento'], 404);
        }

        $docente = Docente::where('ci', $request->ci)->first();

        if (!$docente) {
            return response()->json(['message' => 'No se encontró información para este CI'], 404);
        }

        $cortesIds = $cortesActivos->pluck('id');

        // Obtener facturaciones del corte activo
        $facturacionesCorteActivo = $docente->facturacions()
            ->with(['sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->whereIn('corte_id', $cortesIds)
            ->get();

        // Obtener facturaciones REZAGADO de cortes anteriores (para que pueda subirlas)
        $facturacionesRezagadas = $docente->facturacions()
            ->with(['sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->whereNotIn('corte_id', $cortesIds)
            ->where('estado_subida', 'REZAGADO')
            ->get();

        // Combinar ambas colecciones
        $facturaciones = $facturacionesCorteActivo->merge($facturacionesRezagadas);

        if ($facturaciones->isEmpty()) {
            return response()->json(['message' => 'No hay registros para este docente'], 404);
        }

        $cortesConFacturas = $facturaciones->pluck('corte_id')->unique()->toArray();
        $cortesReales = $cortesActivos->filter(function($corte) use ($cortesConFacturas) {
            return in_array($corte->id, $cortesConFacturas);
        })->values();

        $corteRegular = $cortesActivos->where('tipo_corte', 'REGULAR')->first() ?? $cortesActivos->first();

        return response()->json([
            'docente' => $docente,
            'cortes_activos' => $cortesReales,
            'facturaciones' => $facturaciones,
            'periodo_facturacion' => $corteRegular ? [
                'fecha_inicio' => $corteRegular->fecha_inicio_facturacion?->format('Y-m-d'),
                'fecha_fin' => $corteRegular->fecha_fin_facturacion?->format('Y-m-d'),
                'estado' => $corteRegular->getPeriodoStatus(),
                'dias_restantes' => $corteRegular->getDiasRestantes(),
            ] : null

        ]);
    }
}

