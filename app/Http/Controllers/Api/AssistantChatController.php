<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AssistantChatController extends Controller
{
    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $message = trim($request->message);

        try {
            $catalog = $this->catalog();
            $prompt = $this->buildSystemPrompt($catalog, $message);

            $gemini = app(GeminiService::class);
            $result = $gemini->generateContent($prompt);

            if (! is_array($result) || empty($result['success'])) {
                return response()->json([
                    'error' => true,
                    'message' => 'El asistente no está disponible ahora mismo. Intenta más tarde.',
                ], 503);
            }

            $text = $result['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $text = trim($text);
            $text = preg_replace('/^```/m', '', $text);
            $text = trim($text);

            return response()->json([
                'error' => false,
                'message' => $text,
                'data' => [
                    'reply' => $text,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AssistantChatController: '.$e->getMessage());

            return response()->json([
                'error' => true,
                'message' => 'El asistente no está disponible ahora mismo. Intenta más tarde.',
            ], 503);
        }
    }

    private function catalog(): array
    {
        return Cache::remember('assistant_property_catalog', 600, function () {
            return Property::query()
                ->where('status', 1)
                ->where('request_status', 'approved')
                ->orderByDesc('is_premium')
                ->limit(15)
                ->get(['id', 'slug_id', 'title', 'price', 'currency', 'city', 'propery_type', 'bedrooms', 'bathrooms', 'build_area', 'land_area'])
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'slug' => $p->slug_id,
                        'titulo' => $p->title,
                        'precio' => $p->price.' '.($p->currency ?? ''),
                        'ciudad' => $p->city,
                        'tipo' => (int) $p->propery_type === 0 ? 'Alquiler' : 'Venta',
                        'habitaciones' => $p->bedrooms,
                        'bathrooms' => $p->bathrooms,
                        'area' => $p->build_area,
                        'enlace' => url('/my-property/'.$p->slug_id),
                    ];
                })
                ->values()
                ->all();
        });
    }

    private function buildSystemPrompt(array $catalog, string $message): string
    {
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE);

        $tipoCompra = array_values(array_filter($catalog, fn ($p) => $p['tipo'] === 'Venta'));
        $tipoAlquiler = array_values(array_filter($catalog, fn ($p) => $p['tipo'] === 'Alquiler'));
        $ciudades = array_values(array_unique(array_column($catalog, 'ciudad')));

        $stats = 'Hay '.count($catalog).' propiedades en el catálogo activo.';
        if (count($ciudades)) {
            $stats .= ' Ciudades disponibles: '.implode(', ', $ciudades).'.';
        }
        if (count($tipoCompra)) {
            $stats .= ' Hay '.count($tipoCompra).' en venta y '.count($tipoAlquiler).' en alquiler.';
        }

        $prompt = "Eres 'Omko', el asistente inmobiliario oficial de la plataforma inmobiliaria de República Dominicana (properties.omko.do).\n";
        $prompt .= "Responde SIEMPRE en español, con tono amable, claro y profesional.\n";
        $prompt .= "Tu objetivo es ayudar al visitante a encontrar una propiedad y ayudarle a comprar, vender o alquilar.\n\n";
        $prompt .= "REGLAS ESTRICTAS:\n";
        $prompt .= "1. SOLO recomiendas propiedades que existen en el catálogo siguiente. NUNCA inventes propiedades, precios o enlaces.\n";
        $prompt .= "2. Si el usuario busca algo (ciudad, precio, habitaciones, compra o alquiler), filtra el catálogo y sugiere 2-3 opciones concretas con título, precio y enlace.\n";
        $prompt .= "3. Si no hay coincidencias, dilo con honestidad y ofrece contactar a un asesor humano o dejar sus datos.\n";
        $prompt .= "4. Mantén las respuestas concisas (máx. ~180 palabras) y termina ofreciendo el siguiente paso.\n\n";
        $prompt .= "DATOS DEL CATALOGO ACTIVO:\n";
        $prompt .= $stats."\n";
        $prompt .= "Propiedades (JSON):\n".$catalogJson."\n\n";
        $prompt .= "MENSAJE DEL USUARIO:\n".$message;

        return $prompt;
    }
}
