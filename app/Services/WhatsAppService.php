<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de integración con WhatsApp Business API.
 *
 * En desarrollo funciona en modo MOCK (loguea el mensaje sin enviarlo).
 * En producción con WHATSAPP_MODE=live llama a la Graph API de Meta.
 * La decisión de usar stub/mock en local fue tomada por el cliente (FASE 8, T4).
 */
class WhatsAppService
{
    protected string $mode;
    protected ?string $token;
    protected ?string $phoneId;
    protected ?string $senderNumber;

    public function __construct()
    {
        $this->mode = (string) config('services.whatsapp.mode', 'mock');
        $this->token = (string) config('services.whatsapp.token', '');
        $this->phoneId = (string) config('services.whatsapp.phone_id', '');
        $this->senderNumber = (string) config('services.whatsapp.sender_number', '');
    }

    public function isMock(): bool
    {
        return $this->mode !== 'live';
    }

    /**
     * Envía un mensaje de texto a un destinatario (número con código de país, sin '+'),
     * p.ej. "18091234567". Devuelve array {success, message_id, mock}.
     */
    public function sendMessage(string $to, string $text): array
    {
        if ($this->isMock()) {
            return $this->mockSend('text', $to, ['text' => $text]);
        }

        $url = "https://graph.facebook.com/v19.0/{$this->phoneId}/messages";

        try {
            $response = Http::withToken($this->token)
                ->withoutVerifying()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['preview_url' => false, 'body' => $text],
                ]);

            if ($response->successful()) {
                $messageId = data_get($response->json(), 'messages.0.id');

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'mock' => false,
                ];
            }

            Log::error('WhatsApp sendMessage failed: '.$response->body());

            return [
                'success' => false,
                'message' => 'Error enviando mensaje de WhatsApp',
                'mock' => false,
            ];

        } catch (\Throwable $e) {
            Log::error('WhatsApp sendMessage exception: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Excepción en WhatsAppService',
                'mock' => false,
            ];
        }
    }

    /**
     * Envía una plantilla aprobada (requerida para mensajes comerciales de primer contacto).
     */
    public function sendTemplate(string $to, string $templateName, array $components = []): array
    {
        if ($this->isMock()) {
            return $this->mockSend('template', $to, [
                'template' => $templateName,
                'components' => $components,
            ]);
        }

        $url = "https://graph.facebook.com/v19.0/{$this->phoneId}/messages";

        try {
            $response = Http::withToken($this->token)
                ->withoutVerifying()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => config('services.whatsapp.language', 'es')],
                        'components' => $components,
                    ],
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message_id' => data_get($response->json(), 'messages.0.id'),
                    'mock' => false,
                ];
            }

            Log::error('WhatsApp sendTemplate failed: '.$response->body());

            return ['success' => false, 'mock' => false];

        } catch (\Throwable $e) {
            Log::error('WhatsApp sendTemplate exception: '.$e->getMessage());

            return ['success' => false, 'mock' => false];
        }
    }

    protected function mockSend(string $type, string $to, array $payload): array
    {
        Log::info('[WHATSAPP-MOCK] '.strtoupper($type).' a '.$to.' desde '.($this->senderNumber ?: 'n/a'), $payload);

        return [
            'success' => true,
            'message_id' => 'mock_'.uniqid(),
            'mock' => true,
        ];
    }
}
