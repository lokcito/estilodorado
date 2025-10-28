<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthClienteController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'nombre'    => 'required|string|max:100',
            'apellido'  => 'nullable|string|max:100',
            'telefono'  => 'nullable|string|max:20',
            'email'     => 'required|email|max:100|unique:clientes,email',
            'direccion' => 'nullable|string',
            'contrasena'=> 'required|string|min:6|max:255',
        ]);

        $data['contrasena'] = Hash::make($data['contrasena']);

        $cliente = Cliente::create($data);
        $token = $cliente->createToken('token_cliente')->plainTextToken;

        return response()->json([
            'cliente' => [
                'id_cliente' => $cliente->id_cliente,
                'nombre'     => $cliente->nombre,
                'apellido'   => $cliente->apellido,
                'telefono'   => $cliente->telefono,
                'direccion'  => $cliente->direccion,
                'email'      => $cliente->email,
            ],
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'      => 'required|email',
            'contrasena' => 'required|string',
        ]);

        $cliente = Cliente::where('email', $credentials['email'])->first();
        if (!$cliente) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }

        $stored = $cliente->getAuthPassword();
        $looksBcrypt = is_string($stored) && Str::startsWith($stored, '$2y$');

        if ($looksBcrypt) {
            if (!Hash::check($credentials['contrasena'], $stored)) {
                return response()->json(['message' => 'Credenciales inválidas'], 401);
            }
        } else {
            if ($credentials['contrasena'] !== $stored) {
                return response()->json(['message' => 'Credenciales inválidas'], 401);
            }
            $cliente->contrasena = Hash::make($credentials['contrasena']);
            $cliente->save();
        }

        $token = $cliente->createToken('token_cliente')->plainTextToken;

        return response()->json([
            'cliente' => [
                'id_cliente' => $cliente->id_cliente,
                'nombre'     => $cliente->nombre,
                'apellido'   => $cliente->apellido,
                'telefono'   => $cliente->telefono,
                'direccion'  => $cliente->direccion,
                'email'      => $cliente->email,
            ],
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user(); // auth:sanctum
        // 🔒 Si llega un token de EMPLEADO, corta con 401 (antes te daba 500)
        if (!$user || !($user instanceof Cliente)) {
            return response()->json(['message' => 'Token no válido para CLIENTE'], 401);
        }

        return response()->json([
            'id_cliente' => $user->id_cliente,
            'nombre'     => $user->nombre,
            'apellido'   => $user->apellido,
            'telefono'   => $user->telefono,
            'direccion'  => $user->direccion,
            'email'      => $user->email,
        ]);
    }

    public function update(Request $request)
    {
        $c = $request->user();
        if (!$c || !($c instanceof Cliente)) {
            return response()->json(['message' => 'Token no válido para CLIENTE'], 401);
        }

        $data = $request->validate([
            'nombre'    => 'required|string|max:100',
            'apellido'  => 'nullable|string|max:100',
            'telefono'  => 'nullable|string|max:20',
            'direccion' => 'nullable|string',
        ]);

        $c->fill($data)->save();

        return response()->json([
            'id_cliente' => $c->id_cliente,
            'nombre'     => $c->nombre,
            'apellido'   => $c->apellido,
            'telefono'   => $c->telefono,
            'direccion'  => $c->direccion,
            'email'      => $c->email,
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function checkEmail(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $exists = \App\Models\Cliente::where('email', $data['email'])->exists();
        return $exists
            ? response()->json(['exists' => true], 200)
            : response()->json(['exists' => false], 404);
    }

    public function resetSimple(Request $request)
    {
        $data = $request->validate([
            'email'      => 'required|email',
            'contrasena' => 'required|string|min:6|max:255',
        ]);
        $cliente = \App\Models\Cliente::where('email', $data['email'])->first();
        if (!$cliente) return response()->json(['message' => 'Cliente no encontrado'], 404);

        $cliente->contrasena = \Illuminate\Support\Facades\Hash::make($data['contrasena']);
        $cliente->save();

        return response()->json(['message' => 'Contraseña actualizada'], 200);
    }
}
