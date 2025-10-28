<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoClienteStoreController extends Controller
{
    // POST /api/pedidos
    public function store(Request $request)
    {
        $data = $request->validate([
            'forma_pago'        => 'nullable|string|max:50',
            'direccion_entrega' => 'nullable|string',
            'items'             => 'required|array|min:1',
            'items.*.id_producto' => 'required|integer|exists:productos,id_producto',
            'items.*.cantidad'    => 'required|integer|min:1',
        ]);

        $user = $request->user();

        return DB::transaction(function() use ($data, $user){
            $pedido = Pedido::create([
                'id_cliente'       => $user->id_cliente,
                'fecha_pedido'     => now(),
                'estado'           => 'pendiente',
                'total'            => 0,
                'forma_pago'       => $data['forma_pago'] ?? null,
                'direccion_entrega'=> $data['direccion_entrega'] ?? null,
            ]);

            $total = 0;
            foreach($data['items'] as $it){
                $prod = Producto::findOrFail($it['id_producto']);
                $precio = $prod->precio_venta;

                DetallePedido::create([
                    'id_pedido'     => $pedido->id_pedido,
                    'id_producto'   => $prod->id_producto,
                    'cantidad'      => $it['cantidad'],
                    'precio_unitario'=> $precio,
                ]);

                $total += $precio * $it['cantidad'];
            }

            $pedido->total = $total;
            $pedido->save();

            return response()->json([
                'id_pedido' => $pedido->id_pedido,
                'total'     => $pedido->total,
                'estado'    => $pedido->estado
            ], 201);
        });
    }
}
