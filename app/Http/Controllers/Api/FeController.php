<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Fe\SunatService;
use Greenter\Model\Sale\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeController extends Controller
{
    public function __construct(private SunatService $fe){}

    /**
     * POST /api/fe/emitir
     * payload:
     *  - tipo: 'boleta' | 'factura'
     *  - cliente: { tipoDoc:'1|6', numDoc:'...', nombre:'...', direccion:'...' }
     *  - items: [{ codigo, descripcion, cantidad, precioUnit }]
     *  - moneda: 'PEN'
     */
    public function emitir(Request $request)
    {
        $data = $request->validate([
            'tipo' => 'required|in:boleta,factura',
            'cliente.tipoDoc' => 'required|in:1,6',
            'cliente.numDoc'  => 'required|string',
            'cliente.nombre'  => 'required|string',
            'cliente.direccion' => 'nullable|string',
            'moneda' => 'nullable|in:PEN,USD',
            'items'  => 'required|array|min:1',
            'items.*.codigo' => 'nullable|string',
            'items.*.descripcion' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:1',
            'items.*.precioUnit' => 'required|numeric|min:0',
        ]);

        try {
            $invoice = $this->fe->buildInvoice($data);   // Invoice (01/03)
            $res = $this->fe->sendAndStore($invoice);    // Enviar y guardar XML/CDR/PDF
            return response()->json($res, $res['success'] ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error emitiendo: '.$e->getMessage(),
            ], 500);
        }
    }

    // Descargas
    public function xml($name)
    {
        $path = "fe/xml/{$name}.xml";
        abort_unless(Storage::disk('local')->exists($path), 404);
        return response()->file(storage_path("app/{$path}"), [
            'Content-Type' => 'application/xml'
        ]);
    }

    public function cdr($name)
    {
        $path = "fe/cdr/{$name}.zip";
        abort_unless(Storage::disk('local')->exists($path), 404);
        return response()->download(storage_path("app/{$path}"));
    }

    public function pdf($name)
    {
        $path = "fe/pdf/{$name}.pdf";
        abort_unless(Storage::disk('local')->exists($path), 404);
        return response()->file(storage_path("app/{$path}"), [
            'Content-Type' => 'application/pdf'
        ]);
    }
}
