<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Docente;
use App\Models\Corte;

class PublicController extends Controller
{
    public function searchByCI(Request $request)
    {
        $request->validate(['ci' => 'required']);

        $cortesActivos = Corte::where('estado', 1)->get();

        if ($cortesActivos->isEmpty()) {
            return response()->json(['message' => 'No hay ningún corte activo en este momento'], 404);
        }

        $docente = Docente::where('ci', $request->ci)->first();

        if (!$docente) {
            return response()->json(['message' => 'No se encontró información para este CI'], 404);
        }

        $cortesIds = $cortesActivos->pluck('id');

        $facturaciones = $docente->facturacions()
            ->with(['sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->whereIn('corte_id', $cortesIds)
            ->get();

        if ($facturaciones->isEmpty()) {
            return response()->json(['message' => 'No hay registros para este docente en el corte activo'], 404);
        }

        $cortesConFacturas = $facturaciones->pluck('corte_id')->unique()->toArray();
        $cortesReales = $cortesActivos->filter(function($corte) use ($cortesConFacturas) {
            return in_array($corte->id, $cortesConFacturas);
        })->values();

        return response()->json([
            'docente' => $docente,
            'cortes_activos' => $cortesReales,
            'facturaciones' => $facturaciones
        ]);
    }
}
