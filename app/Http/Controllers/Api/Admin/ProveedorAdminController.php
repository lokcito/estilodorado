<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorAdminController extends Controller
{
    public function index(Request $request)
    {
        $per = (int)($request->get('per_page', 20));
        $q = Proveedor::query();
        if ($request->filled('q')) $q->where('nombre_empresa', 'like', "%{$request->q}%");
        return $q->orderBy('nombre_empresa')->paginate($per);
    }

    public function show($id)
    {
        $p = Proveedor::find($id);
        if (!$p) return response()->json(['message' => 'No encontrado'], 404);
        return $p;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre_empresa' => 'required|string|max:255',
            'contacto' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'created_at' => 'nullable|date',
        ]);
        $proveedor = Proveedor::create($data);
        return response()->json($proveedor, 201);
    }

    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::find($id);
        if (!$proveedor) return response()->json(['message' => 'No encontrado'], 404);

        $data = $request->validate([
            'nombre_empresa' => 'required|string|max:255',
            'contacto' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'created_at' => 'nullable|date',
        ]);

        $proveedor->update($data);
        return response()->json($proveedor);
    }

    public function destroy($id)
    {
        $p = Proveedor::find($id);
        if (!$p) return response()->json(['message' => 'No encontrado'], 404);
        $p->delete();
        return response()->json(['message' => 'Eliminado']);
    }
}
