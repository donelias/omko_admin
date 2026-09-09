<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * FASE 7 (T1) — Calificación automática de un lead por IA.
 *
 * Se ejecuta en segundo plano tras crear/recibir un lead. Llama a Gemini para
 * analizar el mensaje y asignar un score (0-100) + nivel + sugerencia.
 * Nunca deja el job en failed_jobs: si la API falla, degrada con un cálculo
 * heurístico local y guarda el resultado igualmente.
 */
class ScoreLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $leadId;

    public $timeout = 60;

    public $tries = 2;

    public function __construct(int $leadId)
    {
        $this->leadId = $leadId;
    }

    public function handle(GeminiService $gemini): void
    {
        $lead = Lead::with('property')->find($this->leadId);
        if (! $lead) {
            Log::warning('ScoreLeadJob: lead not found', ['lead_id' => $this->leadId]);

            return;
        }

        $mensaje = (string) $lead->notas;
        $precio = $lead->property->price ?? $lead->property->possible_price ?? 'N/A';

        try {
            $ia = $gemini->scoreLead([
                'nombre' => $lead->nombre,
                'notas' => $mensaje,
                'property_title' => $lead->property->title ?? 'N/A',
                'property_type' => $lead->property->property_type ?? 'propiedad',
                'price' => $precio,
                'city' => $lead->property->city ?? '',
            ]);

            if (! empty($ia['success']) && ! empty($ia['data'])) {
                $payload = $ia['data'];
                $score = (int) $payload['score'];
                $nivel = (string) ($payload['nivel'] ?? 'desconocido');
            } else {
                Log::info('ScoreLeadJob: IA fallida, usando heurística local', [
                    'lead_id' => $lead->id,
                    'error' => $ia['error'] ?? null,
                ]);
                [$score, $nivel] = $this->heuristic($mensaje, (string) $precio);
                $payload = [
                    'score' => $score,
                    'nivel' => $nivel,
                    'resumen' => 'Calificación heurística local (IA no disponible)',
                    'sugerencia' => 'Revisar el mensaje del cliente manualmente.',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ScoreLeadJob: excepción, usamos heurística', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
            [$score, $nivel] = $this->heuristic($mensaje, (string) $precio);
            $payload = [
                'score' => $score,
                'nivel' => $nivel,
                'resumen' => 'Calificación heurística local (excepción en IA)',
                'sugerencia' => 'Revisar el mensaje del cliente manualmente.',
            ];
        }

        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $metadata['ia'] = [
            'score' => (int) $score,
            'nivel' => (string) $nivel,
            'resumen' => (string) ($payload['resumen'] ?? ''),
            'sugerencia' => (string) ($payload['sugerencia'] ?? ''),
            'procesado_at' => now()->toISOString(),
        ];

        $lead->score = max(0, min(100, (int) $score));
        $lead->metadata = $metadata;
        $lead->save();

        Log::info('ScoreLeadJob: lead puntuado', ['lead_id' => $lead->id, 'score' => $lead->score, 'nivel' => $nivel]);
    }

    /**
     * Heurística local de respaldo: asigna un score 0-100 según señales del
     * mensaje (urgencia, presupuesto, intención) y del precio.
     */
    private function heuristic(string $mensaje, string $precio): array
    {
        $m = mb_strtolower($mensaje);

        $score = 40;

        $intents = ['comprar', 'quiero', 'interesado', 'necesito', 'busco', 'me interesa', 'ver', 'visitar', 'precio'];
        $matched = 0;
        foreach ($intents as $w) {
            if (mb_strpos($m, $w) !== false) {
                $matched++;
            }
        }
        $score += min(25, $matched * 6);

        $urgency = ['urgente', 'urgentemente', 'ya', 'rápido', 'cuanto antes', 'inmediato', 'pronto', 'hoy', 'ahora', 'mañana'];
        $uMatched = 0;
        foreach ($urgency as $w) {
            if (mb_strpos($m, $w) !== false) {
                $uMatched++;
            }
        }
        $score += min(20, $uMatched * 10);

        if (preg_match('/\d{3,}([kK]|\s*(mil|millones?|millon))?/', $mensaje)
            || preg_match('/budget|presupuesto|pago|financ|efectivo|cash|hipoteca/', $m)) {
            $score += 10;
        }

        if (preg_match('/\?/', $mensaje) === 1) {
            $score -= 8;
        }

        $score = max(0, min(100, $score));

        $nivel = $score >= 80 ? 'alto' : ($score >= 50 ? 'medio' : 'bajo');

        return [$score, $nivel];
    }
}