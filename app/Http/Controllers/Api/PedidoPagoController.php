<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ComprobanteService;

class PedidoPagoController extends Controller
{
    
    public function index(Request $request)
{
    $user = $request->user();

    $pedidos = \App\Models\Pedido::where('id_cliente', $user->id_cliente)
        ->with(['detalles.producto'])
        ->orderByDesc('id_pedido')
        ->get();

    $list = $pedidos->map(function ($p) {
        $det = $p->detalles;
        $first = $det->first();
        $firstName = $first?->producto?->nombre ?? ('#' . ($first?->id_producto ?? '—'));
        $extra = max(0, $det->count() - 1);
        $label = $extra > 0 ? "{$firstName} (+{$extra})" : $firstName;

        $tipo  = $p->comprobante_tipo;
        $serie = $p->comprobante_serie;
        $num8  = str_pad((string)$p->comprobante_numero, 8, '0', STR_PAD_LEFT);

        return [
            'id_pedido'         => $p->id_pedido,
            'fecha_pedido'      => optional($p->fecha_pedido)->format('Y-m-d H:i:s'),
            'estado'            => $p->estado,
            'total'             => $p->total,
            'forma_pago'        => $p->forma_pago,
            'direccion_entrega' => $p->direccion_entrega,
            'producto_label'    => $label,
            'comprobante_tipo'  => $tipo,
            'comprobante_serie' => $serie,
            'comprobante_numero'=> $p->comprobante_numero,
            'friendly'          => $serie && $p->comprobante_numero ? "{$serie}-{$num8}" : null,
        ];
    });

    return response()->json($list);
    }
    
    
    public function confirmar(Request $request)
    {
        $data = $request->validate([
    'forma_pago'        => 'required|in:tarjeta,yape,efectivo',
    'culqi_id'          => 'required_if:forma_pago,tarjeta,yape|string',
    'direccion_entrega' => 'nullable|string',
    'items'             => 'required|array|min:1',
    'items.*.id_producto' => 'required|integer|exists:productos,id_producto',
    'items.*.cantidad'    => 'required|integer|min:1',
    'comprobante'       => 'nullable|in:BO,FA,bo,fa',
    'factura'           => 'nullable|array',
    'boleta'            => 'nullable|array',
         ]);


        $user = $request->user();

        // =========================
        // MODO EFECTIVO (retiro en tienda)
        // =========================
        if ($data['forma_pago'] === 'efectivo') {
            return DB::transaction(function () use ($data, $user) {

                // Crear pedido SIN comprobante (usamos valores neutros para evitar NOT NULL)
                $pedido = new Pedido();
                $pedido->id_cliente         = $user->id_cliente;
                $pedido->fecha_pedido       = now();
                $pedido->estado             = 'pendiente'; // se pagará en tienda
                $pedido->total              = 0;
                $pedido->forma_pago         = 'efectivo';
                $pedido->culqi_id           = null;
                $pedido->direccion_entrega  = $data['direccion_entrega'] ?? null;

                // Evitar error 1364 si tus columnas son NOT NULL
                $pedido->comprobante_tipo   = 'EF';
                $pedido->comprobante_serie  = 'EF00';
                $pedido->comprobante_numero = 0;

                $pedido->save();

                // Detalles + total
                $total = 0;
                foreach ($data['items'] as $it) {
                    $prod   = Producto::findOrFail($it['id_producto']);
                    $precio = $prod->precio_venta;

                    DetallePedido::create([
                        'id_pedido'       => $pedido->id_pedido,
                        'id_producto'     => $prod->id_producto,
                        'cantidad'        => $it['cantidad'],
                        'precio_unitario' => $precio,
                    ]);

                    $total += $precio * $it['cantidad'];
                }
                $pedido->total = $total;
                $pedido->save();

                // Respuesta (sin comprobante)
                return response()->json([
                    'id_pedido'         => $pedido->id_pedido,
                    'fecha_pedido'      => $pedido->fecha_pedido?->format('Y-m-d H:i:s'),
                    'estado'            => $pedido->estado,
                    'total'             => $pedido->total,
                    'forma_pago'        => $pedido->forma_pago,
                    'direccion_entrega' => $pedido->direccion_entrega,

                    'sunat_pdf' => null,
                    'sunat_xml' => null,
                    'sunat_cdr' => null,
                    'comprobante' => null, // <- clave para el front

                ], 201);
            });
        }

        // =========================
        // MODO CULQI (tarjeta/yape) – emite 1 comprobante (FA o BO)
        // =========================

        $hasFA = !empty($data['factura']);
        $hasBO = !empty($data['boleta']);

        if (!$hasFA && !$hasBO) {
            return response()->json([
                'success' => false,
                'message' => 'Debes ingresar datos de FACTURA o BOLETA para emitir el comprobante.',
            ], 422);
        }

        if ($hasFA && $hasBO) {
            $sel = strtoupper((string)($data['comprobante'] ?? ''));
            if (!in_array($sel, ['FA','BO'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Has enviado datos de FACTURA y BOLETA. Indica en "comprobante" si quieres emitir FA o BO.',
                ], 422);
            }
            if ($sel === 'FA') unset($data['boleta']);
            if ($sel === 'BO') unset($data['factura']);
            $hasFA = isset($data['factura']);
            $hasBO = isset($data['boleta']);
        }

        $tipoElegido = $hasFA ? 'FA' : 'BO';
        if (!empty($data['comprobante'])) {
            $tipoElegido = strtoupper($data['comprobante']) === 'FA' ? 'FA' : 'BO';
        }

        $nextNumero = function(string $serie){
            $last = DB::table('pedidos')
                ->where('comprobante_serie',$serie)
                ->selectRaw('COALESCE(MAX(CAST(comprobante_numero AS UNSIGNED)),0) as n')
                ->value('n');
            return (int)$last + 1;
        };

        return DB::transaction(function () use ($data, $user, $tipoElegido, $nextNumero) {

            // Serie & número antes del insert para evitar NOT NULL
            $serie  = $tipoElegido === 'FA' ? 'F001' : 'B001';
            $numero = $nextNumero($serie);

            $pedido = new Pedido();
            $pedido->id_cliente         = $user->id_cliente;
            $pedido->fecha_pedido       = now();
            $pedido->estado             = 'pagado';
            $pedido->total              = 0;
            $pedido->forma_pago         = $data['forma_pago']; // tarjeta|yape
            $pedido->culqi_id           = $data['culqi_id'];
            $pedido->direccion_entrega  = $data['direccion_entrega'] ?? null;

            $pedido->comprobante_tipo   = $tipoElegido;
            $pedido->comprobante_serie  = $serie;
            $pedido->comprobante_numero = $numero;
            $pedido->save();

            // Detalles + total
            $total = 0;
            foreach ($data['items'] as $it) {
                $prod   = Producto::findOrFail($it['id_producto']);
                $precio = $prod->precio_venta;

                DetallePedido::create([
                    'id_pedido'       => $pedido->id_pedido,
                    'id_producto'     => $prod->id_producto,
                    'cantidad'        => $it['cantidad'],
                    'precio_unitario' => $precio,
                ]);

                $total += $precio * $it['cantidad'];
            }
            $pedido->total = $total;
            $pedido->save();

            /** @var ComprobanteService $svc */
            $svc = app(ComprobanteService::class);
            $res = $svc->emitir($pedido, $data);

            $pedido->sunat_pdf = $res['pdf'];
            $pedido->sunat_xml = $res['xml'];
            $pedido->sunat_cdr = $res['cdr'] ?? null;
            $pedido->save();

            $tipo = $pedido->comprobante_tipo;
            $num8 = str_pad((string)$pedido->comprobante_numero, 8, '0', STR_PAD_LEFT);
            $friendly = "{$pedido->comprobante_serie}-{$num8}";

            $pdfUrl = route('fe.pdf', ['tipo' => $tipo, 'serie' => $pedido->comprobante_serie, 'name' => "{$friendly}.pdf"]);
            $xmlUrl = route('fe.xml', ['tipo' => $tipo, 'serie' => $pedido->comprobante_serie, 'name' => "{$friendly}.xml"]);
            $cdrUrl = route('fe.cdr', ['tipo' => $tipo, 'name'  => "R-{$friendly}.zip"]);

            return response()->json([
                'id_pedido'         => $pedido->id_pedido,
                'fecha_pedido'      => $pedido->fecha_pedido?->format('Y-m-d H:i:s'),
                'estado'            => $pedido->estado,
                'total'             => $pedido->total,
                'forma_pago'        => $pedido->forma_pago,
                'direccion_entrega' => $pedido->direccion_entrega,

                'sunat_pdf' => $pdfUrl,
                'sunat_xml' => $xmlUrl,
                'sunat_cdr' => $cdrUrl,

                'comprobante' => [
                    'tipo'   => $tipo,
                    'serie'  => $pedido->comprobante_serie,
                    'numero' => $pedido->comprobante_numero,
                    'pdf'    => $pdfUrl,
                    'xml'    => $xmlUrl,
                    'cdr'    => $cdrUrl,
                ],
            ], 201);
        });
    }

    public function show($id, Request $request)
    {
        $user = $request->user();
        $p = Pedido::where('id_pedido', $id)
            ->where('id_cliente', $user->id_cliente)
            ->with('detalles.producto')
            ->firstOrFail();

        $tipo  = $p->comprobante_tipo;    // 'FA'|'BO'| 'EF'
        $serie = $p->comprobante_serie;
        $num8  = str_pad((string)$p->comprobante_numero, 8, '0', STR_PAD_LEFT);
        $friendly = "{$serie}-{$num8}";

        $pdfUrl = $p->sunat_pdf ? route('fe.pdf', ['tipo' => $tipo, 'serie' => $serie, 'name' => "{$friendly}.pdf"]) : null;
        $xmlUrl = $p->sunat_xml ? route('fe.xml', ['tipo' => $tipo, 'serie' => $serie, 'name' => "{$friendly}.xml"]) : null;
        $cdrUrl = $p->sunat_cdr ? route('fe.cdr', ['tipo' => $tipo, 'name'  => "R-{$friendly}.zip"]) : null;

        return response()->json([
            'id_pedido'         => $p->id_pedido,
            'fecha_pedido'      => $p->fecha_pedido?->format('Y-m-d H:i:s'),
            'estado'            => $p->estado,
            'total'             => $p->total,
            'forma_pago'        => $p->forma_pago,
            'direccion_entrega' => $p->direccion_entrega,

            'sunat_pdf' => $pdfUrl,
            'sunat_xml' => $xmlUrl,
            'sunat_cdr' => $cdrUrl,

            // Si fue efectivo, el front sabrá que no hay comprobante
            'comprobante' => ($tipo === 'FA' || $tipo === 'BO') ? [
                'tipo'   => $tipo,
                'serie'  => $serie,
                'numero' => $p->comprobante_numero,
                'pdf'    => $pdfUrl,
                'xml'    => $xmlUrl,
                'cdr'    => $cdrUrl,
            ] : null,

            'detalles' => $p->detalles->map(function ($d) {
                return [
                    'id_producto'     => $d->id_producto,
                    'producto'        => $d->producto?->nombre,
                    'cantidad'        => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'subtotal'        => $d->cantidad * $d->precio_unitario,
                ];
            }),
        ]);
    }
}
