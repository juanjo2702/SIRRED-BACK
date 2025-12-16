<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Corte;

class CorteController extends Controller
{
    public function index()
    {
        return Corte::orderBy('fecha_inicio', 'desc')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'estado' => 'required|boolean',
            'fecha_inicio_facturacion' => 'nullable|date',
            'fecha_fin_facturacion' => 'nullable|date|after_or_equal:fecha_inicio_facturacion',
        ], [
            'nombre.required' => 'El nombre del corte es obligatorio',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
            'fecha_inicio_facturacion.date' => 'La fecha de inicio de facturación debe ser una fecha válida',
            'fecha_fin_facturacion.date' => 'La fecha de fin de facturación debe ser una fecha válida',
            'fecha_fin_facturacion.after_or_equal' => 'La fecha fin de facturación debe ser igual o posterior a la fecha de inicio',
        ]);

        if ($validated['estado']) {
            Corte::where('estado', 1)->update(['estado' => 0]);
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
            'fecha_inicio_facturacion' => 'nullable|date',
            'fecha_fin_facturacion' => 'nullable|date|after_or_equal:fecha_inicio_facturacion',
        ], [
            'nombre.required' => 'El nombre del corte es obligatorio',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio',
            'fecha_inicio_facturacion.date' => 'La fecha de inicio de facturación debe ser una fecha válida',
            'fecha_fin_facturacion.date' => 'La fecha de fin de facturación debe ser una fecha válida',
            'fecha_fin_facturacion.after_or_equal' => 'La fecha fin de facturación debe ser igual o posterior a la fecha de inicio',
        ]);

        if ($validated['estado'] && !$corte->estado) {
            Corte::where('estado', 1)->update(['estado' => 0]);
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
