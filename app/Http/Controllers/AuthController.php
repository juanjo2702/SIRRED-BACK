<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'ci' => 'required',
            'password' => 'required',
        ]);

        // Custom authentication using CI
        if (Auth::attempt(['ci' => $credentials['ci'], 'password' => $credentials['password']])) {
            $user = Auth::user();
            if (!$user->status) {
                Auth::logout();
                return response()->json(['message' => 'Usuario inactivo'], 403);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // Load role relationship
            $user->load('role');

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'force_change_password' => is_null($user->password_changed_at)
            ]);
        }

        return response()->json(['message' => 'Credenciales incorrectas'], 401);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|confirmed|min:6',
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->password_changed_at = now();
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada']);
    }
}
