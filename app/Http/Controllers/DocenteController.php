<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use Illuminate\Http\Request;

class DocenteController extends Controller
{
    /**
     * Lista todos los docentes con filtros opcionales
     */
    public function index(Request $request)
    {
        $query = Docente::query();

        // Filtro de búsqueda por texto (nombre, apellidos, CI)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellidos', 'like', "%{$search}%")
                    ->orWhere('ci', 'like', "%{$search}%");
            });
        }

        // Filtro por tipo_compra
        if ($request->has('tipo_compra') && $request->tipo_compra !== null && $request->tipo_compra !== '') {
            $query->where('tipo_compra', $request->tipo_compra);
        }

        // Filtro por estado
        if ($request->has('estado') && $request->estado !== null && $request->estado !== '') {
            $query->where('estado', $request->estado);
        }

        // Ordenar por apellidos y nombre
        $query->orderBy('apellidos')->orderBy('nombre');

        return $query->get();
    }

    /**
     * Actualiza el tipo_compra de un docente
     */
    public function update(Request $request, Docente $docente)
    {
        $request->validate([
            'tipo_compra' => 'required|in:1,2'
        ]);

        $docente->update([
            'tipo_compra' => $request->tipo_compra
        ]);

        return response()->json([
            'message' => 'Tipo de compra actualizado correctamente',
            'docente' => $docente
        ]);
    }
}
