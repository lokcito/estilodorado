<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Producto;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PedidoAdminController extends Controller
{
    public function index(Request $request)
    {
        $per  = (int)($request->input('per_page', 10));
        $page = (int)($request->input('page', 1));

        $q = Pedido::query()
            ->leftJoin('clientes as c', 'c.id_cliente', '=', 'pedidos.id_cliente')
            ->select([
                'pedidos.*',
                DB::raw("TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,''))) as cliente_nombre"),
            ])
            ->orderByDesc('pedidos.id_pedido');

        if ($request->filled('cliente')) {
            $cliente = trim((string)$request->input('cliente'));
            $q->where(function ($w) use ($cliente) {
                $w->whereRaw("TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,''))) = ?", [$cliente])
                  ->orWhere('c.nombre', '=', $cliente);
            });
        }

        if ($request->filled('estado')) {
            $q->where('pedidos.estado', $request->input('estado'));
        }

        if ($request->filled('forma_pago')) {
            $q->where('pedidos.forma_pago', $request->input('forma_pago'));
        }

        if ($request->filled('fecha_desde')) {
            $q->whereDate('pedidos.fecha_pedido', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $q->whereDate('pedidos.fecha_pedido', '<=', $request->input('fecha_hasta'));
        }

        if ($per <= 0) {
            $rows = $q->get()->map(fn($p) => $this->decorateRow($p));
            return response()->json([
                'data' => $rows,
                'meta' => ['total' => $rows->count()],
            ]);
        }

        $p = $q->paginate($per, ['*'], 'page', $page);
        $p->getCollection()->transform(fn($row) => $this->decorateRow($row));

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'page'        => $p->currentPage(),
                'per_page'    => $p->perPage(),
                'total'       => $p->total(),
                'total_pages' => $p->lastPage(),
            ],
        ]);
    }

    public function show($id)
    {
        $p = Pedido::with(['cliente','detalles.producto','historial'])->find($id);
        if (!$p) return response()->json(['message'=>'No encontrado'],404);

        $p->cliente_nombre = trim(($p->cliente->nombre ?? '').' '.($p->cliente->apellido ?? ''));
        $urls = $this->buildComprobanteUrls($p);
        $p->pdf_url = $urls['pdf'] ?? null;
        $p->xml_url = $urls['xml'] ?? null;
        $p->cdr_url = $urls['cdr'] ?? null;

        return $p;
    }

    /**
     * POST /api/admin/pedidos
     * Acepta: campos del pedido + detalles[]
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_cliente'         => 'required|integer|exists:clientes,id_cliente',
            'fecha_pedido'       => 'nullable|date',
            'estado'             => 'required|in:pendiente,pagado,enviado,entregado,cancelado',
            'total'              => 'nullable|numeric|min:0',
            'forma_pago'         => 'nullable|in:tarjeta,yape,efectivo',
            'direccion_entrega'  => 'nullable|string',
            'comprobante_tipo'   => 'nullable|in:FA,BO,EF',
            'comprobante_serie'  => 'nullable|string|max:10',
            'comprobante_numero' => 'nullable|integer|min:0',

            'detalles'                   => 'required|array|min:1',
            'detalles.*.id_producto'     => 'required|integer|exists:productos,id_producto',
            'detalles.*.cantidad'        => 'required|integer|min:1',
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0',
        ]);

        $pedido = DB::transaction(function () use ($data) {
            $p = new Pedido();
            $p->id_cliente         = $data['id_cliente'];
            $p->fecha_pedido       = $data['fecha_pedido'] ?? now();
            $p->estado             = $data['estado'];
            $p->total              = 0; // se calculará abajo
            $p->forma_pago         = $data['forma_pago'] ?? null;
            $p->direccion_entrega  = $data['direccion_entrega'] ?? null;
            $p->comprobante_tipo   = $data['comprobante_tipo'] ?? null;
            $p->comprobante_serie  = $data['comprobante_serie'] ?? null;
            $p->comprobante_numero = $data['comprobante_numero'] ?? null;
            $p->sunat_xml          = null;
            $p->sunat_pdf          = null;
            $p->sunat_cdr          = null;
            $p->save();

            $total = 0.0;
            foreach ($data['detalles'] as $d) {
                $prod = Producto::find($d['id_producto']);

                $precio = array_key_exists('precio_unitario', $d) && $d['precio_unitario'] !== null
                    ? (float)$d['precio_unitario']
                    : (float)$prod->precio_venta;

                $cant = (int)$d['cantidad'];
                $sub  = $precio * $cant;

                DetallePedido::create([
                    'id_pedido'       => $p->id_pedido,
                    'id_producto'     => $prod->id_producto,
                    'cantidad'        => $cant,
                    'precio_unitario' => $precio,
                    // ❌ NO enviar 'subtotal': es columna generada en MySQL
                ]);

                $total += $sub;
            }

            $p->total = round($total, 2);
            $p->save();

            return $p;
        });

        return response()->json($this->decorateRow(
            Pedido::leftJoin('clientes as c','c.id_cliente','=','pedidos.id_cliente')
                ->select(['pedidos.*', DB::raw("TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,''))) as cliente_nombre")])
                ->where('pedidos.id_pedido',$pedido->id_pedido)->first()
        ), 201);
    }

    /**
     * PUT/PATCH /api/admin/pedidos/{id}
     * Solo editable: fecha_pedido, estado, forma_pago
     */
    public function update($id, Request $request)
    {
        $data = $request->validate([
            'fecha_pedido' => 'nullable|date',
            'estado'       => 'required|in:pendiente,pagado,enviado,entregado,cancelado',
            'forma_pago'   => 'nullable|in:tarjeta,yape,efectivo',
        ]);

        $p = Pedido::find($id);
        if (!$p) return response()->json(['message'=>'Pedido no encontrado'],404);

        if (isset($data['fecha_pedido'])) $p->fecha_pedido = $data['fecha_pedido'];
        $p->estado     = $data['estado'];
        if (array_key_exists('forma_pago',$data)) $p->forma_pago = $data['forma_pago'];
        $p->save();

        $row = Pedido::leftJoin('clientes as c','c.id_cliente','=','pedidos.id_cliente')
            ->select(['pedidos.*', DB::raw("TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,''))) as cliente_nombre")])
            ->where('pedidos.id_pedido',$p->id_pedido)->first();

        return response()->json($this->decorateRow($row));
    }

    public function destroy($id)
    {
        $p = Pedido::find($id);
        if (!$p) return response()->json(['message' => 'Pedido no encontrado'], 404);

        $p->delete();
        return response()->json(['ok' => true]);
    }

    public function comprobantes($id)
    {
        $p = Pedido::find($id);
        if (!$p) return response()->json(['message'=>'Pedido no encontrado'],404);

        $urls = $this->buildComprobanteUrls($p);

        return response()->json([
            'pdf' => $urls['pdf'] ?? null,
            'xml' => $urls['xml'] ?? null,
            'cdr' => $urls['cdr'] ?? null,
        ]);
    }

    public function descargarComprobante($id, Request $request)
    {
        $tipo = strtolower((string)$request->query('tipo','pdf'));
        if (!in_array($tipo, ['pdf','xml','cdr'])) {
            return response()->json(['message'=>'Tipo inválido'], 422);
        }

        $p = Pedido::find($id);
        if (!$p) return response()->json(['message'=>'Pedido no encontrado'],404);

        $urls = $this->buildComprobantePaths($p);
        $rel  = $urls[$tipo] ?? null;
        if (!$rel || !Storage::disk('public')->exists($rel)) {
            return response()->json(['message'=>strtoupper($tipo).' no disponible'],404);
        }

        $mime = $tipo === 'pdf' ? 'application/pdf' : ($tipo === 'xml' ? 'application/xml' : 'application/zip');
        return response(Storage::disk('public')->get($rel), 200, [
            'Content-Type'              => $mime,
            'Content-Disposition'       => 'inline; filename="'.basename($rel).'"',
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET,HEAD,OPTIONS',
            'Access-Control-Allow-Headers' => '*',
        ]);
    }

    // -----------------------------
    // Helpers
    // -----------------------------
    private function decorateRow($row)
    {
        if (!$row) return $row;

        $urls = $this->buildComprobanteUrls($row);
        $row->pdf_url = $urls['pdf'] ?? null;
        $row->xml_url = $urls['xml'] ?? null;
        $row->cdr_url = $urls['cdr'] ?? null;
        return $row;
    }

    private function buildComprobanteUrls($pedido): array
    {
        if (!$pedido) return [];

        $tipo = $pedido->comprobante_tipo;
        $serie = $pedido->comprobante_serie;
        $num   = (int)($pedido->comprobante_numero ?? 0);
        if (!in_array($tipo, ['FA','BO'], true) || !$serie || !$num) {
            return [];
        }
        $num8 = str_pad((string)$num, 8, '0', STR_PAD_LEFT);
        $friendly = "{$serie}-{$num8}";

        return [
            'pdf' => route('fe.pdf', ['tipo'=>$tipo, 'serie'=>$serie, 'name'=> "{$friendly}.pdf"]),
            'xml' => route('fe.xml', ['tipo'=>$tipo, 'serie'=>$serie, 'name'=> "{$friendly}.xml"]),
            'cdr' => route('fe.cdr', ['tipo'=>$tipo, 'name'=> "R-{$friendly}.zip"]),
        ];
    }

    private function buildComprobantePaths($pedido): array
    {
        if (!$pedido) return [];

        $tipo = $pedido->comprobante_tipo;
        $serie = $pedido->comprobante_serie;
        $num   = (int)($pedido->comprobante_numero ?? 0);
        if (!in_array($tipo, ['FA','BO'], true) || !$serie || !$num) {
            return [];
        }
        $num8 = str_pad((string)$num, 8, '0', STR_PAD_LEFT);
        $friendly = "{$serie}-{$num8}";

        return [
            'pdf' => "comprobantes/pdf/{$tipo}/{$serie}/{$friendly}.pdf",
            'xml' => "comprobantes/xml/{$tipo}/{$serie}/{$friendly}.xml",
            'cdr' => "comprobantes/cdr/{$tipo}/R-{$friendly}.zip",
        ];
    }
}
