<?php

namespace App\Services;

use App\Jobs\AnalyzeClientScreeningJob;
use App\Models\ClientScreening;
use App\Models\ClientScreeningQuestion;
use App\Models\ClientScreeningResponse;
use Illuminate\Support\Facades\Log;

/**
 * Motor de calificación de depuración de clientes.
 *
 * Flujo:
 *  1. `storeSubmission()` — persiste el screening + respuestas y calcula el
 *     score heurístico ponderado (0-100).
 *  2. Encola `AnalyzeClientScreeningJob` para el análisis IA (solvencia).
 *  3. `decide()` — el agente aprueba/rechaza; se actualiza estado y lead.
 *
 * El score final SIEMPRE lo decide el agente; la IA solo recomienda.
 */
class ClientQualificationService
{
    /**
     * Calcula el puntaje ponderado (0-100) de un screening dado sus respuestas.
     */
    public static function calculateScore(ClientScreening $screening): int
    {
        $responses = $screening->responses()->with('question')->get();

        $totalWeight = 0;
        $weightedPoints = 0;

        foreach ($responses as $response) {
            $question = $response->question;
            if (! $question || ! $question->status) {
                continue;
            }

            $weight = max(0, (float) $question->weight);

            if ($response->points === 0 && ! $question->isRequiredField($response)) {
                // Respuesta vacía en campo no obligatorio → puntos neutrales.
                $points = 50;
            } else {
                $points = $response->points;
            }

            $totalWeight += $weight;
            $weightedPoints += $points * $weight;
        }

        if ($totalWeight <= 0) {
            return 0;
        }

        return (int) round($weightedPoints / $totalWeight);
    }

    /**
     * Persiste el screening y sus respuestas, calcula score y encola la IA.
     *
     * @param  array  $data  customer_name, customer_email, customer_phone, origin, property_id, agent_id, lead_id
     * @param  array  $answers  [question_key => value, ...]
     */
    public static function storeSubmission(array $data, array $answers): ClientScreening
    {
        $screening = ClientScreening::create([
            'property_id' => $data['property_id'],
            'agent_id' => $data['agent_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? 'Cliente',
            'customer_email' => $data['customer_email'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'origin' => $data['origin'] ?? 'link',
            'status' => 'en_evaluacion',
            'score' => 0,
        ]);

        $questions = ClientScreeningQuestion::query()
            ->where('status', true)
            ->get()
            ->keyBy('question_key');

        foreach ($answers as $questionKey => $value) {
            $question = $questions->get($questionKey);
            if (! $question) {
                continue;
            }

            $points = $question->pointsForValue($value);

            ClientScreeningResponse::create([
                'client_screening_id' => $screening->id,
                'question_id' => $question->id,
                'value' => $value,
                'points' => $points,
            ]);
        }

        // Recalcular con las respuestas persistidas (puntos reales).
        $score = self::calculateScore($screening);
        $screening->score = $score;
        $screening->nivel = self::nivelFor($score);
        $screening->responded_at = now();
        $screening->save();

        // Análisis IA en segundo plano — no rompe el guardado.
        try {
            AnalyzeClientScreeningJob::dispatch($screening->id);
        } catch (\Throwable $e) {
            Log::warning('ClientQualificationService: no se pudo encolar análisis IA', [
                'screening_id' => $screening->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $screening->fresh(['responses.question', 'property']);
    }

    /**
     * Decisión humana del agente. Actualiza el screening y el lead asociado.
     */
    public static function decide(ClientScreening $screening, string $decision, ?string $notas): ClientScreening
    {
        $decision = in_array($decision, ['aprobado', 'rechazado']) ? $decision : 'rechazado';

        $screening->decision_agente = $decision;
        $screening->notas_agente = $notas;
        $screening->status = $decision;
        $screening->decided_at = now();
        $screening->save();

        // Reflejar la decisión en el lead CRM (si existe).
        $lead = $screening->lead;
        if ($lead) {
            $lead->status = $decision === 'aprobado' ? 'calificado' : 'perdido';
            $metadata = is_array($lead->metadata) ? $lead->metadata : [];
            $metadata['screening'] = [
                'id' => $screening->id,
                'score' => $screening->score,
                'nivel' => $screening->nivel,
                'decision' => $decision,
                'decidido_at' => $screening->decided_at?->toISOString(),
            ];
            $lead->metadata = $metadata;
            $lead->save();
        }

        return $screening->fresh(['responses.question', 'property', 'lead']);
    }

    /**
     * Normaliza el puntaje a un nivel cualitativo.
     */
    public static function nivelFor(int $score): string
    {
        if ($score >= 80) {
            return 'alto';
        }

        return $score >= 50 ? 'medio' : 'bajo';
    }
}