<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Conversions API de Meta (server-side).
 * Usa las credenciales del AGENTE dueño de la propiedad/lead (AgentAdIntegration),
 * almacenadas desde el frontend y cifradas. Nunca se hardcodean credenciales.
 */
class MetaConversionsService
{
    protected string $version = 'v19.0';

    /**
     * Envía un evento de conversión a Meta.
     *
     * @param  array  $config      ['pixel_id' => ..., 'capi_access_token' => ...]
     * @param  string $eventName   Lead | PageView | ViewContent | CompleteRegistration ...
     * @param  array  $userData    ['email','phone','first_name','last_name','client_ip_address','client_user_agent','fbp','fbc']
     * @param  array  $customData  ['content_name','content_id','currency','value','source_url']
     * @param  string $eventId     ID único para dedup con el pixel del navegador
     * @return array               ['success' => bool, ...]
     */
    public function track(array $config, string $eventName, array $userData, array $customData, string $eventId, ?string $testCode = null): array
    {
        $pixelId = $config['pixel_id'] ?? null;
        $token = $config['capi_access_token'] ?? null;

        if (! $pixelId || ! $token) {
            return ['success' => false, 'skipped' => true, 'reason' => 'no-credentials'];
        }

        $hash = function ($value) {
            $value = $value ? strtolower(trim((string) $value)) : null;

            return $value ? hash('sha256', $value) : null;
        };

        $fbclid = $userData['fbclid'] ?? null;
        $payload = [
            'data' => [[
                'event_name' => $eventName,
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $customData['source_url'] ?? null,
                'user_data' => [
                    'em' => $hash($userData['email'] ?? null),
                    'ph' => $hash($userData['phone'] ?? null),
                    'fn' => $hash($userData['first_name'] ?? null),
                    'ln' => $hash($userData['last_name'] ?? null),
                    'client_ip_address' => $userData['client_ip_address'] ?? null,
                    'client_user_agent' => $userData['client_user_agent'] ?? null,
                    'fbp' => $userData['fbp'] ?? null,
                    'fbc' => $fbclid
                        ? 'fb.1.' . time() . '.' . $fbclid
                        : ($userData['fbc'] ?? null),
                ],
                'custom_data' => array_filter($customData, fn ($v) => $v !== null),
            ]],
        ];

        if ($testCode) {
            $payload['test_event_code'] = $testCode;
        }

        try {
            $response = Http::withToken($token)
                ->withoutVerifying()
                ->post("https://graph.facebook.com/{$this->version}/{$pixelId}/events", $payload);

            $body = $response->json();

            if ($response->successful()) {
                $received = $body['events_received'] ?? 0;

                return ['success' => $received > 0, 'events_received' => $received, 'body' => $body];
            }

            return ['success' => false, 'body' => $body, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('MetaConversionsService: excepción', ['event' => $eventName, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}