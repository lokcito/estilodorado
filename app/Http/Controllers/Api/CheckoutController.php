<?php

// app/Http/Controllers/Api/CheckoutController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\SunatService; // el que ya te pasé

class CheckoutController extends Controller
{
    public function confirmar(Request $req, SunatService $sunat)
    {
        // Requiere estar logueado (middleware sanctum en rutas)
        $user = $req->user();
        if (!$user) return response()->json(['message'=>'No autenticado'], 401);

        $data = $req->validate([
            'forma_pago'         => 'required|in:tarjeta,yape',
            'culqi_id'           => 'required|string',
            'direccion_entrega'  => 'required|string',
            'items'              => 'required|array|min:1',
            'items.*.id_producto'=> 'required|integer|exists:productos,id_producto',
            'items.*.cantidad'   => 'required|integer|min:1',
            'comprobante'        => 'nullable|in:BO,FA', // BO default
        ]);

        return DB::transaction(function () use ($user, $data, $sunat) {

            $pedido = Pedido::create([
                'id_cliente'       => $user->id_cliente,
                'fecha_pedido'     => now(),
                'estado'           => 'pagado',
                'total'            => 0,
                'forma_pago'       => $data['forma_pago'],
                'culqi_id'         => $data['culqi_id'],
                'direccion_entrega'=> $data['direccion_entrega'],
            ]);

            $total = 0;
            $items = [];
            foreach ($data['items'] as $it) {
                $prod   = Producto::findOrFail($it['id_producto']);
                $precio = $prod->precio_venta;
                $qty    = $it['cantidad'];

                DetallePedido::create([
                    'id_pedido'      => $pedido->id_pedido,
                    'id_producto'    => $prod->id_producto,
                    'cantidad'       => $qty,
                    'precio_unitario'=> $precio,
                ]);

                $items[] = [
                    'codigo'      => (string)$prod->id_producto,
                    'descripcion' => $prod->nombre,
                    'unidad'      => 'NIU',
                    'cantidad'    => $qty,
                    'valor'       => (float)$precio, // sin IGV si decides manejarlo así
                ];
                $total += $precio * $qty;
            }

            $pedido->total = $total;
            $pedido->save();

            // -------- Emisión de comprobante (boleta por defecto) --------
            $tipo = $data['comprobante'] ?? 'BO';
            $result = $sunat->emitirComprobante([
                'tipo'        => $tipo,                        // 'BO' | 'FA'
                'serie'       => null,                         // deja que SunatService lo resuelva
                'numero'      => null,
                'cliente'     => [
                    'tipoDoc' => '1',                         // DNI para boleta (demo)
                    'numDoc'  => '00000000',
                    'razon'   => $user->nombre.' '.($user->apellido ?? ''),
                    'email'   => $user->email,
                ],
                'moneda'      => 'PEN',
                'total'       => (float)$total,
                'items'       => $items,
            ]);

            if ($result['ok'] === false) {
                // si falló la emisión, igual dejamos pedido 'pagado' y devolvemos aviso
                return response()->json([
                    'id_pedido' => $pedido->id_pedido,
                    'message'   => 'Pedido creado, pero no se pudo emitir el comprobante',
                    'sunat'     => $result
                ], 201);
            }

            // Guardamos data de comprobante en pedido
            $pedido->update([
                'comprobante_tipo'   => $result['tipo'],
                'comprobante_serie'  => $result['serie'],
                'comprobante_numero' => $result['numero'],
                'sunat_xml'          => $result['xml_path'],
                'sunat_cdr'          => $result['cdr_path'],
                'sunat_pdf'          => $result['pdf_path'],
            ]);

            // URLs públicas (si usas "public")
            $xmlUrl = Storage::url($pedido->sunat_xml);
            $cdrUrl = Storage::url($pedido->sunat_cdr);
            $pdfUrl = Storage::url($pedido->sunat_pdf);

            return response()->json([
                'id_pedido'        => $pedido->id_pedido,
                'fecha_pedido'     => $pedido->fecha_pedido,
                'estado'           => $pedido->estado,
                'total'            => $pedido->total,
                'forma_pago'       => $pedido->forma_pago,
                'direccion_entrega'=> $pedido->direccion_entrega,
                'comprobante'      => [
                    'tipo'   => $pedido->comprobante_tipo,
                    'serie'  => $pedido->comprobante_serie,
                    'numero' => $pedido->comprobante_numero,
                    'xml'    => $xmlUrl,
                    'cdr'    => $cdrUrl,
                    'pdf'    => $pdfUrl,
                ]
            ], 201);
        });
    }
}
