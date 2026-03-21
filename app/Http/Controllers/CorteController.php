<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Corte;

class CorteController extends Controller
{
    public function index(Request $request)
    {
        $query = Corte::orderBy('fecha_inicio', 'desc');
        if ($request->has('tipo_corte')) {
            $query->where('tipo_corte', $request->tipo_corte);
        } else {
            $query->where('tipo_corte', 'REGULAR');
        }
        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'estado' => 'required|boolean',
            'tipo_corte' => 'nullable|string|in:REGULAR,PRACTICA',
        ], [
            'nombre.required' => 'El nombre del corte es obligatorio',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
        ]);

        if ($validated['estado']) {
            $tipo = $validated['tipo_corte'] ?? 'REGULAR';
            Corte::where('estado', 1)->where('tipo_corte', $tipo)->update(['estado' => 0]);
        }

        $corte = Corte::create($validated);
        return response()->json($corte, 201);
    }

    public function update(Request $request, Corte $corte)
    {
        $validated = $request->validate([
            'nombre' => 'required',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'estado' => 'required|boolean',
            'tipo_corte' => 'nullable|string|in:REGULAR,PRACTICA',
        ], [
            'nombre.required' => 'El nombre del corte es obligatorio',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
        ]);

        if ($validated['estado'] && !$corte->estado) {
            $tipo = $validated['tipo_corte'] ?? 'REGULAR';
            Corte::where('estado', 1)->where('tipo_corte', $tipo)->update(['estado' => 0]);
        }

        $corte->update($validated);
        return response()->json($corte);
    }

    public function destroy(Corte $corte)
    {
        $corte->delete();
        return response()->json(['message' => 'Corte eliminado']);
    }
}
