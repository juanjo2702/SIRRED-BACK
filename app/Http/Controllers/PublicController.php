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

        $corteActivo = Corte::where('estado', 1)->first();

        if (!$corteActivo) {
            return response()->json(['message' => 'No hay ningún corte activo en este momento'], 404);
        }

        $docente = Docente::where('ci', $request->ci)->first();

        if (!$docente) {
            return response()->json(['message' => 'No se encontró información para este CI'], 404);
        }

        $facturaciones = $docente->facturacions()
            ->with(['sedeCarrera.sede', 'sedeCarrera.carrera', 'corte'])
            ->where('corte_id', $corteActivo->id)
            ->get();

        if ($facturaciones->isEmpty()) {
            return response()->json(['message' => 'No hay registros para este docente en el corte activo'], 404);
        }

        return response()->json([
            'docente' => $docente,
            'corte_activo' => $corteActivo,
            'facturaciones' => $facturaciones
        ]);
    }
}
