<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaAdminController extends Controller
{
    public function index(Request $request)
    {
        // Si pides todo (para combos): /api/admin/categorias?all=1
        if ($request->boolean('all')) {
            return response()->json([
                'data' => Categoria::orderBy('nombre')->get(['id_categoria','nombre'])
            ]);
        }

        $per = (int)($request->get('per_page', 20));
        $q = Categoria::query();

        if ($request->filled('q')) {
            $term = $request->q;
            $q->where('nombre', 'like', "%{$term}%");
        }

        // Paginar devolviendo solo lo que usa el front
        return $q->orderBy('nombre')
            ->paginate($per, ['id_categoria','nombre','descripcion']);
    }

    public function show($id)
    {
        $c = Categoria::find($id);
        if (!$c) return response()->json(['message'=>'No encontrado'],404);
        return $c;
    }
}
