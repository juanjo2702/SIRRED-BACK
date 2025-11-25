<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return User::with('role')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'apellidos' => 'required',
            'ci' => 'required|unique:users',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'apellidos' => $validated['apellidos'],
            'ci' => $validated['ci'],
            'role_id' => $validated['role_id'],
            'password' => Hash::make($validated['ci']),
            'status' => 1,
        ]);

        $user->load('role');
        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required',
            'apellidos' => 'required',
            'ci' => ['required', Rule::unique('users')->ignore($user->id)],
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update($validated);
        $user->load('role');
        return response()->json($user);
    }

    public function resetPassword(User $user)
    {
        $user->password = Hash::make($user->ci);
        $user->password_changed_at = null;
        $user->save();
        return response()->json(['message' => 'Contraseña restablecida']);
    }

    public function toggleStatus(User $user)
    {
        $user->status = !$user->status;
        $user->save();
        return response()->json(['message' => 'Estado actualizado', 'status' => $user->status]);
    }
}
