<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProductoAdminController extends Controller
{
    // GET /api/admin/productos
    public function index(Request $request)
    {
        $per   = (int)($request->get('per_page', 20));
        $sort  = $request->get('sort','created_at');
        $order = $request->get('order','desc');

        $q = Producto::query()
            ->with(['categoria:id_categoria,nombre']);

        // Acepta q|search
        $term = trim((string)($request->input('q') ?? $request->input('search') ?? ''));
        if ($term !== '') {
            $q->where(function($w) use ($term){
                $w->where('nombre','like',"%$term%")
                  ->orWhere('descripcion','like',"%$term%");
            });
        }

        // Acepta categoria|id_categoria
        $categoria = $request->input('categoria') ?? $request->input('id_categoria');
        if ($categoria !== null && $categoria !== '') {
            $q->where('id_categoria',$categoria);
        }

        if ($request->filled('proveedor')) $q->where('id_proveedor',$request->proveedor);
        if ($request->filled('estado'))    $q->where('estado',$request->estado);

        if ($per <= 0) {
            $rows = $q->orderBy($sort,$order)->get();
            return response()->json([
                'data' => $rows,
                'meta' => ['total' => $rows->count()]
            ]);
        }

        return $q->orderBy($sort,$order)->paginate($per);
    }

    // GET /api/admin/productos/{id}
    public function show($id)
    {
        $prod = Producto::with(['categoria:id_categoria,nombre'])->find($id);
        if (!$prod) return response()->json(['message'=>'Producto no encontrado'],404);
        return $prod;
    }

    // POST /api/admin/productos
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'        => ['required','string','max:255'],
            'descripcion'   => ['nullable','string'],
            'precio_compra' => ['required','numeric','min:0'],
            'precio_venta'  => ['required','numeric','min:0'],
            'stock'         => ['required','integer','min:0'],
            'id_categoria'  => ['required','integer','exists:categorias,id_categoria'],
            'id_proveedor'  => ['required','integer','exists:proveedores,id_proveedor'],
            'estado'        => ['required', Rule::in(['activo','inactivo'])],
            'imagen_url'    => ['nullable','string','max:1000'],
            'slug'          => ['nullable','string','max:255','unique:productos,slug'],
            'created_at'    => ['nullable','date'],
            'updated_at'    => ['nullable','date'],
        ]);

        // Genera slug si no vino
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['nombre']).'-'.Str::random(6);
        }

        $p = new Producto();
        // para que updated_at quede NULL como pediste
        $p->timestamps = false;

        $p->nombre         = $data['nombre'];
        $p->descripcion    = $data['descripcion'] ?? null;
        $p->precio_compra  = $data['precio_compra'];
        $p->precio_venta   = $data['precio_venta'];
        $p->stock          = $data['stock'];
        $p->id_categoria   = $data['id_categoria'];
        $p->id_proveedor   = $data['id_proveedor'];
        $p->estado         = $data['estado'];
        $p->imagen_url     = $data['imagen_url'] ?? null;
        $p->slug           = $data['slug'];

        $p->created_at     = isset($data['created_at']) ? Carbon::parse($data['created_at']) : now();
        $p->updated_at     = null;

        $p->save();

        return response()->json($p, 201);
    }

    // PUT /api/admin/productos/{id}
    public function update($id, Request $request)
    {
        $p = Producto::find($id);
        if (!$p) return response()->json(['message'=>'Producto no encontrado'],404);

        $data = $request->validate([
            'nombre'        => ['required','string','max:255'],
            'descripcion'   => ['nullable','string'],
            'precio_compra' => ['required','numeric','min:0'],
            'precio_venta'  => ['required','numeric','min:0'],
            'stock'         => ['required','integer','min:0'],
            'id_categoria'  => ['required','integer','exists:categorias,id_categoria'],
            'id_proveedor'  => ['required','integer','exists:proveedores,id_proveedor'],
            'estado'        => ['required', Rule::in(['activo','inactivo'])],
            'imagen_url'    => ['nullable','string','max:1000'],
            'slug'          => ['nullable','string','max:255', Rule::unique('productos','slug')->ignore($p->id_producto,'id_producto')],
            'updated_at'    => ['nullable','date'],
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = $p->slug ?: Str::slug($data['nombre']).'-'.Str::random(6);
        }

        // manejamos updated_at manualmente para cumplir tu regla
        $p->timestamps = false;

        $p->nombre         = $data['nombre'];
        $p->descripcion    = $data['descripcion'] ?? null;
        $p->precio_compra  = $data['precio_compra'];
        $p->precio_venta   = $data['precio_venta'];
        $p->stock          = $data['stock'];
        $p->id_categoria   = $data['id_categoria'];
        $p->id_proveedor   = $data['id_proveedor'];
        $p->estado         = $data['estado'];
        $p->imagen_url     = $data['imagen_url'] ?? null;
        $p->slug           = $data['slug'];

        // si viene updated_at nulo o no viene, ponemos ahora
        $p->updated_at     = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : now();

        $p->save();

        return response()->json($p);
    }

    // DELETE /api/admin/productos/{id}
    public function destroy($id)
    {
        $p = Producto::find($id);
        if (!$p) return response()->json(['message'=>'Producto no encontrado'],404);
        $p->delete();
        return response()->json(['ok' => true]);
    }

    // GET /api/admin/alertas/stock-bajo?threshold=3
    public function stockBajo(Request $request)
    {
        $threshold = (int) $request->input('threshold', 3);

        $rows = Producto::query()
            ->with(['categoria:id_categoria,nombre'])
            ->where('stock', '<=', $threshold)
            ->orderBy('stock', 'asc')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows,
            'meta' => ['threshold' => $threshold, 'count' => $rows->count()],
        ]);
    }
}
