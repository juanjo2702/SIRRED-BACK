<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Gestion;
use App\Models\Corte;

class GestionController extends Controller
{
    /**
     * Listar todas las gestiones
     */
    public function index(Request $request)
    {
        $query = Gestion::withCount('cortes')->orderBy('id', 'desc');

        if ($request->has('estado')) {
            $query->where('estado', filter_var($request->estado, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->get());
    }

    /**
     * Listado público de gestiones activas
     */
    public function publicIndex()
    {
        $gestiones = Gestion::where('estado', 1)
            ->with(['cortes' => function ($q) {
                $q->select('id', 'gestion_id', 'nombre', 'tipo_corte', 'fecha_inicio', 'fecha_fin', 'estado')
                  ->orderBy('fecha_inicio', 'asc');
            }])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($gestiones);
    }

    /**
     * Crear una nueva gestión
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:gestiones,nombre',
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre de la gestión es obligatorio',
            'nombre.unique' => 'Ya existe una gestión con ese nombre',
        ]);

        $gestion = Gestion::create([
            'nombre' => trim($validated['nombre']),
            'descripcion' => $validated['descripcion'] ?? null,
            'estado' => $validated['estado'] ?? true,
        ]);

        return response()->json($gestion, 201);
    }

    /**
     * Mostrar una gestión con sus cortes
     */
    public function show(Gestion $gestione)
    {
        $gestione->load(['cortes' => function ($q) {
            $q->orderBy('fecha_inicio', 'asc');
        }]);

        return response()->json($gestione);
    }

    /**
     * Actualizar una gestión existente
     */
    public function update(Request $request, Gestion $gestione)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100|unique:gestiones,nombre,' . $gestione->id,
            'descripcion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre de la gestión es obligatorio',
            'nombre.unique' => 'Ya existe una gestión con ese nombre',
        ]);

        $gestione->update([
            'nombre' => trim($validated['nombre']),
            'descripcion' => $validated['descripcion'] ?? $gestione->descripcion,
            'estado' => isset($validated['estado']) ? (bool)$validated['estado'] : $gestione->estado,
        ]);

        return response()->json($gestione);
    }

    /**
     * Eliminar una gestión
     */
    public function destroy(Gestion $gestione)
    {
        // Desvincular o verificar cortes existentes
        if ($gestione->cortes()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la gestión porque contiene cortes asociados.'
            ], 422);
        }

        $gestione->delete();

        return response()->json(['message' => 'Gestión eliminada correctamente']);
    }

    /**
     * API: Obtener todos los cortes de una gestión por ID (incluye fecha_inicio y fecha_fin)
     */
    public function getCortes(Request $request, $id)
    {
        $gestion = Gestion::find($id);

        if (!$gestion) {
            return response()->json(['message' => 'Gestión no encontrada'], 404);
        }

        $query = $gestion->cortes()
            ->select('id', 'gestion_id', 'nombre', 'tipo_corte', 'fecha_inicio', 'fecha_fin', 'estado')
            ->orderBy('fecha_inicio', 'asc');

        if ($request->has('tipo_corte')) {
            $query->where('tipo_corte', $request->tipo_corte);
        }

        if ($request->has('estado')) {
            $query->where('estado', filter_var($request->estado, FILTER_VALIDATE_BOOLEAN));
        }

        $cortes = $query->get();

        return response()->json([
            'gestion' => [
                'id' => $gestion->id,
                'nombre' => $gestion->nombre,
                'descripcion' => $gestion->descripcion,
                'estado' => $gestion->estado,
            ],
            'total_cortes' => $cortes->count(),
            'cortes' => $cortes
        ]);
    }

    /**
     * API: Obtener todos los cortes de una gestión por NOMBRE (ej. 1/2026 o 1-2026)
     */
    public function getCortesByNombre(Request $request, $nombre)
    {
        $cleanNombre = str_replace('-', '/', urldecode($nombre));

        $gestion = Gestion::where('nombre', $cleanNombre)
            ->orWhere('nombre', urldecode($nombre))
            ->first();

        if (!$gestion) {
            return response()->json(['message' => "Gestión '{$cleanNombre}' no encontrada"], 404);
        }

        $query = $gestion->cortes()
            ->select('id', 'gestion_id', 'nombre', 'tipo_corte', 'fecha_inicio', 'fecha_fin', 'estado')
            ->orderBy('fecha_inicio', 'asc');

        if ($request->has('tipo_corte')) {
            $query->where('tipo_corte', $request->tipo_corte);
        }

        if ($request->has('estado')) {
            $query->where('estado', filter_var($request->estado, FILTER_VALIDATE_BOOLEAN));
        }

        $cortes = $query->get();

        return response()->json([
            'gestion' => [
                'id' => $gestion->id,
                'nombre' => $gestion->nombre,
                'descripcion' => $gestion->descripcion,
                'estado' => $gestion->estado,
            ],
            'total_cortes' => $cortes->count(),
            'cortes' => $cortes
        ]);
    }
}
