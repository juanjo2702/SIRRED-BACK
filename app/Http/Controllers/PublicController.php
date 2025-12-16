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

        $corteActivo = Corte::where('estado', 1)->first();

        if (!$corteActivo) {
            return response()->json(['message' => 'No hay ningún corte activo en este momento'], 404);
        }

        $docente = Docente::where('ci', $request->ci)->first();

        if (!$docente) {
            return response()->json(['message' => 'No se encontró información para este CI'], 404);
        }

        // Obtener facturaciones del corte activo
        $facturacionesCorteActivo = $docente->facturacions()
            ->with(['sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->where('corte_id', $corteActivo->id)
            ->get();

        // Obtener facturaciones REZAGADO de cortes anteriores (para que pueda subirlas)
        $facturacionesRezagadas = $docente->facturacions()
            ->with(['sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->where('corte_id', '!=', $corteActivo->id)
            ->where('estado_subida', 'REZAGADO')
            ->get();

        // Combinar ambas colecciones
        $facturaciones = $facturacionesCorteActivo->merge($facturacionesRezagadas);

        if ($facturaciones->isEmpty()) {
            return response()->json(['message' => 'No hay registros para este docente'], 404);
        }

        return response()->json([
            'docente' => $docente,
            'corte_activo' => $corteActivo,
            'facturaciones' => $facturaciones,
            'periodo_facturacion' => [
                'fecha_inicio' => $corteActivo->fecha_inicio_facturacion?->format('Y-m-d'),
                'fecha_fin' => $corteActivo->fecha_fin_facturacion?->format('Y-m-d'),
                'estado' => $corteActivo->getPeriodoStatus(),
                'dias_restantes' => $corteActivo->getDiasRestantes(),
            ]
        ]);
    }
}

