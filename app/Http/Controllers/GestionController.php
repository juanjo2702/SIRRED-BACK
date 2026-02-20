<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Gestion;

class GestionController extends Controller
{
    public function index()
    {
        return Gestion::orderBy('fecha_inicio', 'desc')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'required|boolean',
        ]);

        if ($validated['estado']) {
            Gestion::where('estado', 1)->update(['estado' => 0]);
        }

        $gestion = Gestion::create($validated);
        return response()->json($gestion, 201);
    }

    public function update(Request $request, Gestion $gestion)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'required|boolean',
        ]);

        if ($validated['estado'] && !$gestion->estado) {
            Gestion::where('estado', 1)->update(['estado' => 0]);
        }

        $gestion->update($validated);
        return response()->json($gestion);
    }

    public function destroy(Gestion $gestion)
    {
        // Check if it has cortes
        if ($gestion->cortes()->count() > 0) {
            return response()->json(['message' => 'No se puede eliminar una gestión con cortes asociados.'], 400);
        }

        $gestion->delete();
        return response()->json(['message' => 'Gestión eliminada correctamente']);
    }
}
