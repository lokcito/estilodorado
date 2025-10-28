<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;

class PedidoClienteController extends Controller
{
    // GET /api/mis-pedidos
    public function index(Request $request)
    {
        $user = $request->user();
        $per = (int)($request->get('per_page',10));
        $sort = $request->get('sort','fecha_pedido');
        $order = $request->get('order','desc');

        $q = Pedido::with(['detalles.producto'])
            ->where('id_cliente',$user->id_cliente);

        if ($request->filled('estado')) $q->where('estado',$request->estado);
        if ($request->filled('desde'))  $q->where('fecha_pedido','>=',$request->desde);
        if ($request->filled('hasta'))  $q->where('fecha_pedido','<=',$request->hasta);

        return $q->orderBy($sort,$order)->paginate($per);
    }

    // GET /api/mis-pedidos/{id}
    public function show($id, Request $request)
    {
        $user = $request->user();
        $pedido = Pedido::with(['detalles.producto','historial'])
            ->where('id_pedido',$id)
            ->where('id_cliente',$user->id_cliente)
            ->first();

        if (!$pedido) return response()->json(['message'=>'Pedido no encontrado'],404);

        return $pedido;
    }
    public function mios(Request $request)
    {
    $u = $request->user(); // cliente autenticado por Sanctum
    if (!$u) return response()->json(['message' => 'No autenticado'], 401);

    $q     = Pedido::query()->where('id_cliente', $u->id_cliente);
    $desde = $request->query('desde', 'last'); // last | 3m | 1y
    $idQ   = $request->query('q');             // buscar por id_pedido exacto

    if (!empty($idQ)) {
        $q->where('id_pedido', (int)$idQ);
    } else {
        // OJO: tu tabla usa fecha_pedido, no created_at
        if ($desde === '3m') {
            $q->where('fecha_pedido', '>=', now()->subMonths(3)->toDateString());
        } elseif ($desde === '1y') {
            $q->where('fecha_pedido', '>=', now()->subYear()->toDateString());
        } else { // 'last' (último mes)
            $q->where('fecha_pedido', '>=', now()->subMonth()->toDateString());
        }
    }

    $pedidos = $q->orderByDesc('fecha_pedido')->get([
        'id_pedido',
        'id_cliente',
        'total',
        'estado',
        'fecha_pedido as fecha',
        'forma_pago',
        'direccion_entrega'
    ]);

    return response()->json($pedidos);
    }


}
