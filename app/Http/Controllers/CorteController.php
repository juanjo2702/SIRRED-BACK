<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Corte;

class CorteController extends Controller
{
    public function index(Request $request)
    {
        $query = Corte::with('gestion')->orderBy('fecha_inicio', 'desc');

        if ($request->has('tipo_corte')) {
            $query->where('tipo_corte', $request->tipo_corte);
        } else {
            $query->where('tipo_corte', 'REGULAR');
        }

        if ($request->has('gestion_id') && !empty($request->gestion_id)) {
            $query->where('gestion_id', $request->gestion_id);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gestion_id' => 'nullable|exists:gestiones,id',
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
            'gestion_id.exists' => 'La gestión seleccionada no es válida',
        ]);

        if ($validated['estado']) {
            $tipo = $validated['tipo_corte'] ?? 'REGULAR';
            // Solo para REGULAR se mantiene la restricción de un único corte activo
            if ($tipo === 'REGULAR') {
                Corte::where('estado', 1)->where('tipo_corte', 'REGULAR')->update(['estado' => 0]);
            }
        }

        $corte = Corte::create($validated);
        $corte->load('gestion');
        return response()->json($corte, 201);
    }

    public function update(Request $request, Corte $corte)
    {
        $validated = $request->validate([
            'gestion_id' => 'nullable|exists:gestiones,id',
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
            'gestion_id.exists' => 'La gestión seleccionada no es válida',
        ]);

        if ($validated['estado'] && !$corte->estado) {
            $tipo = $validated['tipo_corte'] ?? 'REGULAR';
            // Solo para REGULAR se mantiene la restricción de un único corte activo
            if ($tipo === 'REGULAR') {
                Corte::where('estado', 1)->where('tipo_corte', 'REGULAR')->update(['estado' => 0]);
            }
        }

        $corte->update($validated);
        $corte->load('gestion');
        return response()->json($corte);
    }

    public function destroy(Corte $corte)
    {
        $corte->delete();
        return response()->json(['message' => 'Corte eliminado']);
    }
}
