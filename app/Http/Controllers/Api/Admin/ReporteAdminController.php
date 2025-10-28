<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteAdminController extends Controller
{
   public function productos(string $ext, Request $request)
{
    if ($ext === 'csv') {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_productos.csv"'
        ];
        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Nombre', 'Descripción', 'Precio venta', 'Stock']);
            foreach (\App\Models\Producto::all() as $p) {
                fputcsv($out, [$p->id_producto, $p->nombre, $p->descripcion, $p->precio_venta, $p->stock]);
            }
            fclose($out);
        };
        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, $headers);
    }
    return response()->json(['error' => ['message' => 'Formato no implementado']], 400);
}

    public function clientes(string $ext, Request $request)
{
    if ($ext === 'csv') {
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="reporte_clientes.csv"'];
        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Nombre', 'Apellido', 'Teléfono', 'Email', 'Dirección', 'Fecha Registro']);
            foreach (\App\Models\Cliente::all() as $c) {
                fputcsv($out, [$c->id_cliente, $c->nombre, $c->apellido, $c->telefono, $c->email, $c->direccion, $c->created_at]);
            }
            fclose($out);
        };
        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, $headers);
    }
    return response()->json(['error' => ['message' => 'Formato no implementado']], 400);
}

   public function pedidos(string $ext, Request $request)
{
    if ($ext === 'csv') {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_pedidos.csv"'
        ];
        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Cliente', 'Estado', 'Total', 'Fecha']);
            foreach (\App\Models\Pedido::with('cliente')->get() as $p) {
                fputcsv($out, [
                    $p->id_pedido,
                    $p->cliente->nombre . ' ' . $p->cliente->apellido,
                    $p->estado,
                    $p->total,
                    $p->fecha_pedido
                ]);
            }
            fclose($out);
        };
        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, $headers);
    }
    return response()->json(['error' => ['message' => 'Formato no implementado']], 400);
}

    public function inventario(string $ext, Request $request)
{
    if ($ext === 'csv') {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="reporte_inventario.csv"'
        ];
        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Producto', 'Tipo movimiento', 'Cantidad', 'Empleado', 'Fecha']);
            foreach (\App\Models\Inventario::with('producto', 'empleado')->get() as $m) {
                fputcsv($out, [
                    $m->id_movimiento,
                    $m->producto?->nombre,
                    $m->tipo_movimiento,
                    $m->cantidad,
                    $m->empleado?->nombre,
                    $m->fecha_movimiento
                ]);
            }
            fclose($out);
        };
        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, $headers);
    }
    return response()->json(['error' => ['message' => 'Formato no implementado']], 400);
}
}
