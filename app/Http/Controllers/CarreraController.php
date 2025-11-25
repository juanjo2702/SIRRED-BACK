<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Carrera;

class CarreraController extends Controller
{
    public function index()
    {
        return Carrera::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['nombre' => 'required', 'estado' => 'boolean']);
        $carrera = Carrera::create($validated);
        return response()->json($carrera, 201);
    }

    public function update(Request $request, Carrera $carrera)
    {
        $validated = $request->validate(['nombre' => 'required', 'estado' => 'boolean']);
        $carrera->update($validated);
        return response()->json($carrera);
    }

    public function destroy(Carrera $carrera)
    {
        $carrera->delete();
        return response()->json(['message' => 'Carrera eliminada']);
    }
}
