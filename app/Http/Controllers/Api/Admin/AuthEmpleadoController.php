<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Empleado;
use Illuminate\Support\Facades\Hash;

class AuthEmpleadoController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required','string'],
        ]);

        /** @var Empleado|null $emp */
        $emp = Empleado::where('email', $data['email'])->first();
        if (!$emp || !Hash::check($data['password'], $emp->password)) {
            return response()->json(['error' => ['message' => 'Credenciales inválidas']], 401);
        }

        // Carga roles (si existe pivot)
        $emp->loadMissing('roles:id_rol,nombre');

        // Token Sanctum
        $token = $emp->createToken('panel-admin')->plainTextToken;

        // Normaliza roles
        $roles = $emp->roles ? $emp->roles->pluck('nombre')->map(fn($n) => strtoupper($n))->values()->all() : [];
        if (empty($roles) && !empty($emp->cargo)) {
            $roles = [strtoupper($emp->cargo)];
        }

        return response()->json([
            'token' => $token,
            'user'  => [
                'id_empleado' => $emp->id_empleado,
                'nombre'      => $emp->nombre,
                'apellido'    => $emp->apellido,
                'email'       => $emp->email,
                'cargo'       => $emp->cargo,
            ],
            'roles' => $roles,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user(); // auth:sanctum
        // 🔒 Si llega un token de CLIENTE, corta con 401 (antes te daba 500)
        if (!$user || !($user instanceof Empleado)) {
            return response()->json(['message' => 'Token no válido para ADMIN'], 401);
        }

        $user->loadMissing('roles:id_rol,nombre');

        $roles = $user->roles ? $user->roles->pluck('nombre')->map(fn($n) => strtoupper($n))->values()->all() : [];
        if (empty($roles) && !empty($user->cargo)) {
            $roles = [strtoupper($user->cargo)];
        }

        return response()->json([
            'user'  => [
                'id_empleado' => $user->id_empleado,
                'nombre'      => $user->nombre,
                'apellido'    => $user->apellido,
                'email'       => $user->email,
                'cargo'       => $user->cargo,
            ],
            'roles' => $roles,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}
