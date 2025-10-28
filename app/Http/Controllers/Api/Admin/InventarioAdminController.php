<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Inventario;
use App\Models\Producto;
use Carbon\Carbon;

class InventarioAdminController extends Controller
{
    // GET /api/admin/inventario
    public function index(Request $request)
    {
        $per = (int) ($request->get('per_page', 20));

        // Aliases admitidos desde el front
        $tipo       = $request->get('tipo_movimiento', $request->get('tipo'));
        $desde      = $request->get('desde', $request->get('fecha_desde'));
        $hasta      = $request->get('hasta', $request->get('fecha_hasta'));
        $idProducto = $request->get('producto', $request->get('id_producto'));
        $idEmpleado = $request->get('empleado', $request->get('id_empleado'));
        $refTipo    = $request->get('referencia_tipo');

        $q = DB::table('inventario as i')
            ->join('productos as p', 'p.id_producto', '=', 'i.id_producto')
            ->leftJoin('empleados as e', 'e.id_empleado', '=', 'i.id_empleado')
            ->leftJoin('empleado_rol as er', 'er.id_empleado', '=', 'e.id_empleado')
            ->leftJoin('roles as r', 'r.id_rol', '=', 'er.id_rol')
            ->select(
                'i.id_movimiento',
                'i.id_producto',
                'i.tipo_movimiento',
                'i.cantidad',
                'i.fecha',
                'i.observacion',
                'i.referencia_tipo',
                'i.referencia_id',
                'i.id_empleado',
                'p.nombre as producto_nombre',
                DB::raw("COALESCE(CONCAT(e.nombre,' ',e.apellido),'') as empleado_nombre"),
                DB::raw("COALESCE(GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR ','),'') as empleado_roles")
            );

        if ($idProducto) $q->where('i.id_producto', $idProducto);
        if ($tipo)       $q->where('i.tipo_movimiento', $tipo);
        if ($refTipo)    $q->where('i.referencia_tipo', $refTipo);
        if ($idEmpleado) $q->where('i.id_empleado', $idEmpleado);
        if ($desde)      $q->where('i.fecha', '>=', $desde);
        if ($hasta)      $q->where('i.fecha', '<=', $hasta);

        $q->groupBy([
            'i.id_movimiento',
            'i.id_producto',
            'i.tipo_movimiento',
            'i.cantidad',
            'i.fecha',
            'i.observacion',
            'i.referencia_tipo',
            'i.referencia_id',
            'i.id_empleado',
            'p.nombre',
            'e.nombre',
            'e.apellido',
        ]);

        $q->orderBy($request->get('sort', 'i.fecha'), $request->get('order', 'desc'));

        // Si per_page <= 0 => devolver todo sin paginar (data + meta.total)
        if ($per <= 0) {
            $rows = $q->get();
            return response()->json([
                'data' => $rows,
                'meta' => ['total' => $rows->count()]
            ]);
        }

        return $q->paginate($per);
    }

    // POST /api/admin/inventario/entrada
    public function entrada(Request $request)
    {
        $data = $request->validate([
            'id_producto'     => 'required|integer|exists:productos,id_producto',
            'cantidad'        => 'required|integer|min:1',
            'observacion'     => 'nullable|string',
            'referencia_tipo' => 'nullable|in:pedido,ajuste,otro',
            'referencia_id'   => 'nullable|integer',
            'fecha'           => 'nullable|date',
            'id_empleado'     => 'nullable|integer|exists:empleados,id_empleado',
        ]);

        $empId = $data['id_empleado'] ?? optional($request->user())->id_empleado;
        $fecha = isset($data['fecha']) ? Carbon::parse($data['fecha']) : now();

        return DB::transaction(function () use ($data, $empId, $fecha) {
            $p = Producto::lockForUpdate()->findOrFail($data['id_producto']);
            $p->stock += (int) $data['cantidad'];
            $p->save();

            $mov = Inventario::create([
                'id_producto'     => $p->id_producto,
                'tipo_movimiento' => 'entrada',
                'cantidad'        => (int) $data['cantidad'],
                'fecha'           => $fecha,
                'observacion'     => $data['observacion'] ?? null,
                'referencia_tipo' => $data['referencia_tipo'] ?? null,
                'referencia_id'   => $data['referencia_id'] ?? null,
                'id_empleado'     => $empId,
            ]);

            return response()->json(['ok' => true, 'stock' => $p->stock, 'movimiento' => $mov]);
        });
    }

    // POST /api/admin/inventario/salida
    public function salida(Request $request)
    {
        $data = $request->validate([
            'id_producto'     => 'required|integer|exists:productos,id_producto',
            'cantidad'        => 'required|integer|min:1',
            'observacion'     => 'nullable|string',
            'referencia_tipo' => 'nullable|in:pedido,ajuste,otro',
            'referencia_id'   => 'nullable|integer',
            'fecha'           => 'nullable|date',
            'id_empleado'     => 'nullable|integer|exists:empleados,id_empleado',
        ]);

        $empId = $data['id_empleado'] ?? optional($request->user())->id_empleado;
        $fecha = isset($data['fecha']) ? Carbon::parse($data['fecha']) : now();

        return DB::transaction(function () use ($data, $empId, $fecha) {
            $p = Producto::lockForUpdate()->findOrFail($data['id_producto']);

            if ($p->stock < (int) $data['cantidad']) {
                return response()->json(['ok' => false, 'message' => 'Stock insuficiente'], 422);
            }

            $p->stock -= (int) $data['cantidad'];
            $p->save();

            $mov = Inventario::create([
                'id_producto'     => $p->id_producto,
                'tipo_movimiento' => 'salida',
                'cantidad'        => (int) $data['cantidad'],
                'fecha'           => $fecha,
                'observacion'     => $data['observacion'] ?? null,
                'referencia_tipo' => $data['referencia_tipo'] ?? null,
                'referencia_id'   => $data['referencia_id'] ?? null,
                'id_empleado'     => $empId,
            ]);

            return response()->json(['ok' => true, 'stock' => $p->stock, 'movimiento' => $mov]);
        });
    }

    // POST /api/admin/inventario/ajuste
    public function ajuste(Request $request)
    {
        $data = $request->validate([
            'id_producto'     => 'required|integer|exists:productos,id_producto',
            'cantidad'        => 'required|integer', // puede ser positivo o negativo
            'observacion'     => 'nullable|string',
            'referencia_tipo' => 'nullable|in:pedido,ajuste,otro',
            'referencia_id'   => 'nullable|integer',
            'fecha'           => 'nullable|date',
            'id_empleado'     => 'nullable|integer|exists:empleados,id_empleado',
        ]);

        $empId = $data['id_empleado'] ?? optional($request->user())->id_empleado;
        $fecha = isset($data['fecha']) ? Carbon::parse($data['fecha']) : now();
        $delta = (int) $data['cantidad'];

        return DB::transaction(function () use ($data, $delta, $empId, $fecha) {
            $p = Producto::lockForUpdate()->findOrFail($data['id_producto']);

            $nuevo = $p->stock + $delta;
            if ($nuevo < 0) $nuevo = 0;

            $p->stock = $nuevo;
            $p->save();

            $mov = Inventario::create([
                'id_producto'     => $p->id_producto,
                'tipo_movimiento' => 'ajuste',
                'cantidad'        => $delta,
                'fecha'           => $fecha,
                'observacion'     => $data['observacion'] ?? null,
                'referencia_tipo' => $data['referencia_tipo'] ?? null,
                'referencia_id'   => $data['referencia_id'] ?? null,
                'id_empleado'     => $empId,
            ]);

            return response()->json(['ok' => true, 'stock' => $p->stock, 'movimiento' => $mov]);
        });
    }
}
