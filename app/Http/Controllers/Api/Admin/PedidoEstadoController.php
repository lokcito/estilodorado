<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\PedidoEstadoHistorial;
use Illuminate\Http\Request;

class PedidoEstadoController extends Controller
{
    // GET /api/admin/pedidos/{id}/estado-historial
    public function historial($id)
    {
        $rows = PedidoEstadoHistorial::where('id_pedido', $id)
            ->orderBy('fecha','asc')
            ->get();

        return response()->json(['data' => $rows]);
    }

    // POST /api/admin/pedidos/{id}/estado
    public function cambiarEstado($id, Request $request)
    {
        return $this->update($id, $request);
    }

    // PUT /api/admin/pedidos/{id}/estado
    public function update($id, Request $request)
    {
        $data = $request->validate([
            'estado'     => 'required|in:pendiente,pagado,enviado,entregado,cancelado',
            'comentario' => 'nullable|string',
            'id_empleado'=> 'nullable|integer',
        ]);

        $pedido = Pedido::find($id);
        if (!$pedido) return response()->json(['message'=>'Pedido no encontrado'],404);

        $anterior = $pedido->estado;
        $pedido->estado = $data['estado'];
        $pedido->save();

        PedidoEstadoHistorial::create([
            'id_pedido'       => $pedido->id_pedido,
            'estado_anterior' => $anterior,
            'estado_nuevo'    => $data['estado'],
            'fecha'           => now(),
            'comentario'      => $data['comentario'] ?? null,
            'id_empleado'     => $data['id_empleado'] ?? null,
        ]);

        return response()->json(['message'=>'Estado actualizado'],200);
    }
}
