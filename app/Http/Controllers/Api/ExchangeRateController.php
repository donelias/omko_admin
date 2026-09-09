<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class ExchangeRateController extends Controller
{
    public function getUsdToDop(): JsonResponse
    {
        // Guarda la tasa en caché por 24 horas (86400 segundos)
        $rate = Cache::remember('usd_to_dop_rate', 86400, function () {
            try {
                $response = Http::get('https://open.er-api.com/v6/latest/USD');
                
                if ($response->successful() && isset($response->json()['rates']['DOP'])) {
                    return $response->json()['rates']['DOP'];
                }
            } catch (\Exception $e) {
                // Loguear error si es necesario
            }

            // Valor de respaldo (fallback) por si la API externa falla temporalmente
            return 58.50; 
        });

        return response()->json([
            'rate' => (float) $rate
        ]);
    }
}