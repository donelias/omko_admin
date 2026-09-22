<?php

namespace App\Jobs;

use App\Models\ClientScreening;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Análisis IA del formulario de depuración del cliente.
 *
 * Corre en segundo plano tras guardar el screening. Llama a Gemini para
 * identificar señales de riesgo y emitir una recomendación. Si la IA falla,
 * degrada con una recomendación genérica y conserva el score heurístico.
 */
class AnalyzeClientScreeningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $screeningId;

    public $timeout = 45;

    public $tries = 2;

    public function __construct(int $screeningId)
    {
        $this->screeningId = $screeningId;
    }

    public function handle(GeminiService $gemini): void
    {
        $screening = ClientScreening::with(['responses.question', 'property'])->find($this->screeningId);
        if (! $screening) {
            Log::warning('AnalyzeClientScreeningJob: screening not found', ['id' => $this->screeningId]);

            return;
        }

        $responses = [];
        foreach ($screening->responses as $response) {
            $key = $response->question->question_key ?? ('pregunta_'.$response->question_id);
            $responses[$key] = $response->value;
        }

        try {
            $ia = $gemini->analyzeClientScreening([
                'customer_name' => $screening->customer_name,
                'property_title' => $screening->property->title ?? $screening->property->name ?? 'N/A',
                'property_city' => $screening->property->city ?? '',
                'property_price' => $screening->property->price ?? $screening->property->possible_price ?? 'N/A',
                'responses' => $responses,
            ]);

            if (! empty($ia['success']) && ! empty($ia['data'])) {
                $payload = $ia['data'];
            } else {
                Log::info('AnalyzeClientScreeningJob: IA fallida, recomendación genérica', [
                    'screening_id' => $screening->id,
                    'error' => $ia['error'] ?? null,
                ]);
                $payload = [
                    'recomendacion' => 'revisar',
                    'nivel_riesgo' => $screening->score >= 80 ? 'bajo' : ($screening->score >= 50 ? 'medio' : 'alto'),
                    'resumen' => 'Análisis IA no disponible. Evaluar manualmente con base en el puntaje heurístico.',
                    'observaciones' => 'Revisar ingresos, estabilidad laboral y señales de riesgo manualmente.',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('AnalyzeClientScreeningJob: excepción, recomendación genérica', [
                'screening_id' => $screening->id,
                'error' => $e->getMessage(),
            ]);
            $payload = [
                'recomendacion' => 'revisar',
                'nivel_riesgo' => $screening->nivel,
                'resumen' => 'Análisis IA no disponible por error. Evaluar manualmente.',
                'observaciones' => 'Revisar la información del formulario manualmente.',
            ];
        }

        $audit = $screening->ia_metadata ?? [];
        $audit['ia'] = [
            'recomendacion' => (string) ($payload['recomendacion'] ?? 'revisar'),
            'nivel_riesgo' => (string) ($payload['nivel_riesgo'] ?? 'medio'),
            'resumen' => (string) ($payload['resumen'] ?? ''),
            'observaciones' => (string) ($payload['observaciones'] ?? ''),
            'procesado_at' => now()->toISOString(),
        ];

        $screening->recomendacion_ia = (string) ($payload['resumen'] ?? '');
        $screening->ia_metadata = $audit;
        $screening->save();

        Log::info('AnalyzeClientScreeningJob: screening analizado', ['screening_id' => $screening->id, 'recomendacion' => $payload['recomendacion'] ?? 'revisar']);
    }
}