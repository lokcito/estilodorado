<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class GeoController extends Controller
{
    public function search(Request $request)
    {
        if (!filter_var(config('services.geo.enabled', true), FILTER_VALIDATE_BOOL)) {
            return response()->json([]); // desactivado por env
        }

        $q = trim((string)$request->query('q', ''));
        if ($q === '') return response()->json([]);

        // sanea y recorta (Nominatim recomienda consultas razonables)
        $q = Str::of($q)->substr(0, 200)->__toString();

        $cacheMin = (int)config('services.geo.cache_min', 1440);
        $key = 'geo:nominatim:' . md5($q);

        return Cache::remember($key, now()->addMinutes($cacheMin), function () use ($q) {
            try {
                $base   = rtrim(config('services.geo.base', 'https://nominatim.openstreetmap.org'), '/');
                $verify = (bool)config('services.geo.verify', true);
                $timeout= (int)config('services.geo.timeout', 5);
                $email  = (string)config('services.geo.email', '');
                $ua     = 'EstiloDorado/1.0 (Laravel API)'.($email ? " <$email>" : '');

                $params = [
                    'format'          => 'json',
                    'limit'           => 1,
                    'addressdetails'  => 1,
                    'q'               => $q,
                ];
                if ($email) $params['email'] = $email; // recomendado por política

                $res = Http::withHeaders(['User-Agent' => $ua])
                    ->withOptions(['verify' => $verify, 'timeout' => $timeout])
                    ->get("$base/search", $params);

                if ($res->successful()) {
                    return $res->json();
                }

                // Si hay 429/503 u otro, devolvemos vacío pero sin romper UX
                Log::warning('[Geo] respuesta no exitosa de Nominatim', [
                    'status' => $res->status(), 'q' => $q
                ]);
                return [];
            } catch (\Throwable $e) {
                Log::warning('[Geo] fallo geocoding: '.$e->getMessage());
                return [];
            }
        });
    }

    public function reverse(Request $request)
{
    if (!filter_var(config('services.geo.enabled', true), FILTER_VALIDATE_BOOL)) {
        return response()->json(null, 204);
    }

    $lat = (float) $request->query('lat');
    $lon = (float) $request->query('lon');
    if (!$lat && !$lon) return response()->json(null, 400);

    try {
        $base   = rtrim(config('services.geo.base', 'https://nominatim.openstreetmap.org'), '/');
        $verify = (bool)config('services.geo.verify', true);
        $timeout= (int)config('services.geo.timeout', 5);
        $email  = (string)config('services.geo.email', '');
        $ua     = 'EstiloDorado/1.0 (Laravel API)'.($email ? " <$email>" : '');

        $params = [
            'format'         => 'json',
            'lat'            => $lat,
            'lon'            => $lon,
            'addressdetails' => 1,
            'zoom'           => 20, // más granular para captar número
        ];
        if ($email) $params['email'] = $email;

        $res = \Illuminate\Support\Facades\Http::withHeaders(['User-Agent' => $ua])
            ->withOptions(['verify' => $verify, 'timeout' => $timeout])
            ->get("$base/reverse", $params);

        if (!$res->successful()) return response()->json(null, 204);

        $data = $res->json();
        $a = $data['address'] ?? [];

        $via = $a['road'] ?? $a['pedestrian'] ?? $a['residential'] ?? $a['path'] ?? '';
        $numero = $a['house_number'] ?? '';
        $departamento = $a['state'] ?? $a['region'] ?? '';
        $provincia    = $a['county'] ?? $a['state_district'] ?? $a['city'] ?? '';
        $distrito     = $a['city_district'] ?? $a['suburb'] ?? $a['town'] ?? $a['village'] ?? $a['neighbourhood'] ?? '';

        return response()->json([
            'via'          => $via,
            'numero'       => $numero,
            'departamento' => $departamento,
            'provincia'    => $provincia,
            'distrito'     => $distrito,
            'display'      => $data['display_name'] ?? null,
        ]);
    } catch (\Throwable $e) {
        \Log::warning('[Geo reverse] '.$e->getMessage());
        return response()->json(null, 204);
    }
}

}
