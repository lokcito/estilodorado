<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClienteAdminController extends Controller
{
    public function index(Request $request)
    {
        $per = (int)($request->get('per_page', 20));
        $q = Cliente::query();
        if ($request->filled('q')) {
            $term = $request->q;
            $q->where(function ($w) use ($term) {
                $w->where('nombre', 'like', "%$term%")
                  ->orWhere('apellido', 'like', "%$term%")
                  ->orWhere('email', 'like', "%$term%");
            });
        }
        $q->orderBy($request->get('sort', 'created_at'), $request->get('order', 'desc'));
        if ($per <= 0) {
    return $q->get(); // sin paginación
}
return $q->paginate($per);
    }

    public function show($id)
    {
        $c = Cliente::find($id);
        if (!$c) return response()->json(['message' => 'No encontrado'], 404);
        return $c;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150|unique:clientes,email',
            'direccion' => 'nullable|string|max:255',
            'contrasena' => 'nullable|string|min:4',
        ]);

        if (!empty($data['contrasena'])) {
            $data['contrasena'] = Hash::make($data['contrasena']);
        }

        $data['created_at'] = now();
        $data['updated_at'] = null;

        $cliente = Cliente::create($data);
        return response()->json($cliente, 201);
    }

    public function update(Request $request, $id)
    {
        $c = Cliente::find($id);
        if (!$c) return response()->json(['message' => 'No encontrado'], 404);

        $data = $request->validate([
            'nombre' => 'nullable|string|max:100',
            'apellido' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150|unique:clientes,email,' . $id . ',id_cliente',
            'direccion' => 'nullable|string|max:255',
        ]);

        $data['updated_at'] = now();
        $c->update($data);
        return response()->json($c);
    }
}
