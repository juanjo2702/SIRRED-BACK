<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Sede;

class SedeController extends Controller
{
    public function index()
    {
        return Sede::with('sedeCarreras.carrera')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required',
            'estado' => 'boolean',
            'abreviacion' => 'nullable|string|max:10'
        ]);
        $sede = Sede::create($validated);
        return response()->json($sede, 201);
    }

    public function update(Request $request, Sede $sede)
    {
        $validated = $request->validate([
            'nombre' => 'required',
            'estado' => 'boolean',
            'abreviacion' => 'nullable|string|max:10'
        ]);
        $sede->update($validated);
        return response()->json($sede);
    }

    public function destroy(Sede $sede)
    {
        $sede->delete();
        return response()->json(['message' => 'Sede eliminada']);
    }

    public function attachCarrera(Request $request, Sede $sede)
    {
        $request->validate(['carrera_id' => 'required|exists:carreras,id']);

        $exists = $sede->sedeCarreras()->where('carrera_id', $request->carrera_id)->exists();
        if ($exists) {
            return response()->json(['message' => 'Carrera ya asignada'], 400);
        }

        $sede->sedeCarreras()->create(['carrera_id' => $request->carrera_id, 'estado' => 1]);
        return response()->json(['message' => 'Carrera asignada']);
    }

    public function syncCarreras(Request $request, Sede $sede)
    {
        $validated = $request->validate([
            'carrera_ids' => 'required|array',
            'carrera_ids.*' => 'exists:carreras,id'
        ], [
            'carrera_ids.required' => 'Debe seleccionar al menos una carrera',
            'carrera_ids.array' => 'El formato de carreras no es válido',
            'carrera_ids.*.exists' => 'Una o más carreras seleccionadas no existen'
        ]);

        // Delete all current associations
        $sede->sedeCarreras()->delete();

        // Create new associations
        foreach ($validated['carrera_ids'] as $carreraId) {
            $sede->sedeCarreras()->create(['carrera_id' => $carreraId, 'estado' => 1]);
        }

        // Return updated sede with carreras
        $sede->load('sedeCarreras.carrera');
        return response()->json($sede);
    }

    public function detachCarrera(Sede $sede, $carreraId)
    {
        $sede->sedeCarreras()->where('carrera_id', $carreraId)->delete();
        return response()->json(['message' => 'Carrera desasignada']);
    }
}
