<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Corte;

class CorteController extends Controller
{
    public function index(Request $request)
    {
        $query = Corte::with('gestion')->orderBy('fecha_inicio', 'desc');

        if ($request->has('gestion_id')) {
            $query->where('gestion_id', $request->gestion_id);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gestion_id' => 'required|exists:gestions,id',
            'nombre' => 'required',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'estado' => 'required|boolean',
            'fecha_inicio_facturacion' => 'nullable|date',
            'fecha_fin_facturacion' => 'nullable|date|after_or_equal:fecha_inicio_facturacion',
        ], [
            'gestion_id.required' => 'La gestión es obligatoria',
            'gestion_id.exists' => 'La gestión seleccionada no es válida',
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
            // Deactivate other cortes in the SAME gestion? Or globally?
            // "Solo un corte activo a la vez" usually implies global context for the active period to upload invoices.
            // PROPOSAL: The system should enforce only one active corte globally to avoid ambiguity in public search.
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
