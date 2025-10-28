<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class PagoCulqiController extends Controller
{
    public function pagar(Request $request)
    {
        $data = $request->validate([
            'token'       => 'required|string',
            'monto'       => 'required|numeric|min:1',
            'descripcion' => 'required|string',
            'correo'      => 'required|email',
        ]);

        try {
            // Base request con Bearer de Culqi
            $http = Http::withToken(config('services.culqi.private_key'));

            // ⚠️ SOLO EN LOCAL: desactiva verificación SSL para evitar cURL error 60
            // En prod (APP_ENV != local) NO se toca y queda 100% seguro
            if (app()->environment('local')) {
                $http = $http->withOptions(['verify' => false]);
            }

            $response = $http->post(config('services.culqi.api_url') . '/charges', [
                'amount'        => intval($data['monto'] * 100),  // céntimos
                'currency_code' => 'PEN',
                'email'         => $data['correo'],
                'source_id'     => $data['token'],
                'description'   => $data['descripcion'],
            ]);

            if ($response->failed()) {
                Log::error('[CULQI] Respuesta con error', [
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                return response()->json([
                    'error'   => true,
                    'message' => $response->json()['user_message'] ?? 'Error procesando pago.',
                    'detalle' => $response->json(),
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data'    => $response->json(),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Aquí caen los errores tipo cURL 60 en local
            Log::error('[CULQI] Error de conexión', ['error' => $e->getMessage()]);

            return response()->json([
                'error'   => true,
                'message' => 'No se pudo conectar con el servidor de pagos. Intenta nuevamente.',
            ], 500);
        } catch (\Throwable $e) {
            Log::error('[CULQI] Excepción no controlada', ['error' => $e->getMessage()]);
            return response()->json([
                'error'   => true,
                'message' => 'Ocurrió un problema al procesar el pago.',
            ], 500);
        }
    }
}
