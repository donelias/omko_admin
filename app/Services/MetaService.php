<?php

namespace App\Services;

use App\Models\CrmLead;
use App\Models\CrmCampaign;
use App\Models\AgentMetaCredential;
use App\Models\MetaSyncLog;
use App\Models\MetaNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use App\Models\User;
use App\Notifications\MetaLeadReceivedNotification;
use App\Notifications\MetaMessageReceivedNotification;
use App\Notifications\MetaActionNotification;
use App\Events\MetaLeadNotification;
use App\Events\MetaLeadReceivedEvent;
use App\Events\MetaMessageReceivedEvent;
use App\Events\MetaNotificationReceivedEvent;


class MetaService
{
    const META_GRAPH_API_URL = 'https://graph.instagram.com/v18.0';
    const META_MARKETING_API_URL = 'https://graph.facebook.com/v18.0';

    /**
     * Obtener credenciales Meta activas del agente
     */
    public static function obtenerCredenciales($agentId)
    {
        return AgentMetaCredential::where('agent_id', $agentId)
            ->where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_expiracion_token')
                  ->orWhere('fecha_expiracion_token', '>', now());
            })
            ->first();
    }

    /**
     * Validar webhook de Meta
     */
    public static function validarWebhook($hubMode, $hubChallenge, $hubVerifyToken, $webhookVerifyToken)
    {
        if ($hubMode !== 'subscribe') {
            return null;
        }

        if ($hubVerifyToken !== $webhookVerifyToken) {
            Log::warning('Meta webhook verification failed: invalid token');
            return null;
        }

        return $hubChallenge;
    }

    /**
     * Crear notificación para el agente
     */
    private static function crearNotificacion(
        $agentId,
        $leadId,
        $tipo,
        $titulo,
        $mensaje,
        $datos = null
    ) {
        try {
            // Crear notificación en BD
            $notificacion = MetaNotification::createNotification(
                $agentId,
                $leadId,
                $tipo,
                $titulo,
                $mensaje,
                $datos
            );

            // Notificar al agente en tiempo real via broadcast
            $lead = CrmLead::find($leadId);
            if ($lead) {
                Event::dispatch(new MetaLeadNotification(
                    $lead,
                    $agentId,
                    $tipo,
                    $mensaje
                ));
            }

            return $notificacion;
        } catch (\Exception $e) {
            Log::error('Error creando notificación Meta: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Procesar evento de webhook de Meta
     */
    public static function procesarWebhook($data, $agentId)
    {
        try {
            $credentials = self::obtenerCredenciales($agentId);

            if (!$credentials) {
                Log::error("No Meta credentials found for agent {$agentId}");
                return false;
            }

            // Registrar el webhook recibido
            self::registrarSyncLog($agentId, 'webhook_received', $data);

            $object = $data['object'] ?? null;
            $entry = $data['entry'][0] ?? null;

            if (!$entry) {
                return false;
            }

            // Procesar según el tipo de objeto
            match ($object) {
                'page' => self::procesarEventosPage($entry, $agentId, $credentials),
                'lead' => self::procesarEventosLead($entry, $agentId, $credentials),
                default => Log::warning("Unknown Meta object type: {$object}"),
            };

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing Meta webhook: ' . $e->getMessage());
            self::registrarSyncLog($agentId, 'webhook_error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Procesar eventos de página
     */
    private static function procesarEventosPage($entry, $agentId, $credentials)
    {
        $messaging = $entry['messaging'] ?? [];

        foreach ($messaging as $message) {
            $senderId = $message['sender']['id'] ?? null;
            $pageId = $message['recipient']['id'] ?? null;

            // Procesar mensaje
            if (isset($message['message'])) {
                self::procesarMensaje($message['message'], $senderId, $pageId, $agentId, $credentials);
            }

            // Procesar postback
            if (isset($message['postback'])) {
                self::procesarPostback($message['postback'], $senderId, $pageId, $agentId, $credentials);
            }
        }
    }

    /**
     * Procesar eventos de leads
     */
    private static function procesarEventosLead($entry, $agentId, $credentials)
    {
        $leadGenData = $entry['changes'][0]['value'] ?? [];
        $leadId = $leadGenData['leadgen_id'] ?? null;

        if (!$leadId) {
            return;
        }

        // Obtener datos del lead desde Meta
        $leadData = self::obtenerLeadDesMeta($leadId, $credentials);

        if (!$leadData) {
            return;
        }

        // Crear o actualizar lead
        self::crearOActualizarLead($leadData, $agentId, $credentials);
    }

    /**
     * Obtener datos del lead desde Meta API
     */
    public static function obtenerLeadDesMeta($leadId, $credentials)
    {
        try {
            $url = self::META_MARKETING_API_URL . "/{$leadId}";

            $response = Http::get($url, [
                'access_token' => $credentials->meta_access_token,
                'fields' => 'id,first_name,last_name,email,phone_number,created_time,ad_name,campaign_id,campaign_name,adset_id,adset_name,ad_id',
            ]);

            if (!$response->successful()) {
                Log::error('Error fetching lead from Meta: ' . $response->body());
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Exception fetching lead from Meta: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear o actualizar lead desde Meta
     */
    public static function crearOActualizarLead($metaData, $agentId, $credentials)
    {
        try {
            $email = $metaData['email'] ?? null;
            $phoneNumber = $metaData['phone_number'] ?? null;

            // Buscar lead existente
            $lead = CrmLead::where('agent_id', $agentId)
                ->where(function ($q) use ($email, $phoneNumber) {
                    if ($email) {
                        $q->where('email', $email);
                    }
                    if ($phoneNumber) {
                        $q->orWhere('whatsapp', $phoneNumber);
                    }
                })
                ->first();

            $leadData = [
                'nombre' => trim(($metaData['first_name'] ?? '') . ' ' . ($metaData['last_name'] ?? '')),
                'email' => $email,
                'whatsapp' => $phoneNumber,
                'agent_id' => $agentId,
                'origin' => 'meta',
                'meta_lead_id' => $metaData['id'],
                'meta_campaign_id' => $metaData['campaign_id'] ?? null,
                'status' => 'nuevo',
                'score' => 0,
                'metadata' => [
                    'meta_campaign_name' => $metaData['campaign_name'] ?? null,
                    'meta_adset_name' => $metaData['adset_name'] ?? null,
                    'meta_ad_name' => $metaData['ad_name'] ?? null,
                    'fecha_sync_meta' => now(),
                ],
            ];

            if ($lead) {
                $lead->update($leadData);
                self::registrarSyncLog($agentId, 'lead_updated', ['lead_id' => $lead->id, 'meta_lead_id' => $metaData['id']]);
                
                // 🔔 NOTIFICACIÓN: Lead actualizado
                self::crearNotificacion(
                    $agentId,
                    $lead->id,
                    MetaNotification::TYPE_LEAD_UPDATE,
                    '📝 Lead Actualizado',
                    "El lead {$metaData['first_name']} ha sido actualizado desde Meta",
                    ['lead_id' => $lead->id]
                );
            } else {
                $lead = CrmLead::create($leadData);
                self::registrarSyncLog($agentId, 'lead_created', ['lead_id' => $lead->id, 'meta_lead_id' => $metaData['id']]);
                
                // 🔔 NOTIFICACIÓN: Nuevo lead de Meta
                self::crearNotificacion(
                    $agentId,
                    $lead->id,
                    MetaNotification::TYPE_NEW_LEAD,
                    '🎯 Nuevo Lead de Meta',
                    "Nuevo cliente potencial: {$metaData['first_name']} {$metaData['last_name']} - {$metaData['email']}",
                    [
                        'lead_id' => $lead->id,
                        'name' => "{$metaData['first_name']} {$metaData['last_name']}",
                        'email' => $metaData['email'],
                        'campaign' => $metaData['campaign_name'],
                    ]
                );
            }

            // Registrar interacción
            $lead->registrarInteraccion(
                'nota_interna',
                "Lead sincronizado desde Meta (Campaign: {$metaData['campaign_name']})"
            );

            // Calcular score
            \App\Models\CrmLeadScoring::calcularScoreDelLead($lead);

            return $lead;
        } catch (\Exception $e) {
            Log::error('Error creating/updating lead from Meta: ' . $e->getMessage());
            self::registrarSyncLog($agentId, 'lead_sync_error', ['error' => $e->getMessage(), 'meta_data' => $metaData]);
            return null;
        }
    }

    /**
     * Procesar mensaje de Messenger
     */
    private static function procesarMensaje($message, $senderId, $pageId, $agentId, $credentials)
    {
        $text = $message['text'] ?? '';
        $attachments = $message['attachments'] ?? [];

        // Buscar o crear lead desde senderId
        $lead = self::buscarOCrearLeadDesdeFacebookId($senderId, $agentId);

        if (!$lead) {
            return;
        }

        // Registrar mensaje como interacción
        $contenido = !empty($text) ? $text : 'Mensaje con ' . count($attachments) . ' archivos adjuntos';

        $lead->registrarInteraccion(
            'facebook',
            $contenido,
            'mensaje_recibido'
        );

        // Cambiar status a contactado si estaba en nuevo
        if ($lead->status === 'nuevo') {
            $lead->cambiarStatus('contactado', 'Mensaje recibido de Meta');
        }

        // 🔔 NOTIFICACIÓN: Nuevo mensaje de cliente potencial
        $preview = substr($text, 0, 100);
        self::crearNotificacion(
            $agentId,
            $lead->id,
            MetaNotification::TYPE_MESSAGE,
            '💬 Nuevo Mensaje de ' . ($lead->nombre ?? 'Cliente'),
            $preview ?: 'Mensaje con archivos adjuntos',
            [
                'lead_id' => $lead->id,
                'lead_name' => $lead->nombre,
                'message_preview' => $preview,
                'sender_id' => $senderId,
            ]
        );
    }

    /**
     * Procesar postback de Messenger
     */
    private static function procesarPostback($postback, $senderId, $pageId, $agentId, $credentials)
    {
        $payload = $postback['payload'] ?? null;

        if (!$payload) {
            return;
        }

        $lead = self::buscarOCrearLeadDesdeFacebookId($senderId, $agentId);

        if (!$lead) {
            return;
        }

        $lead->registrarInteraccion(
            'facebook',
            "Acción: {$payload}",
            'postback'
        );
    }

    /**
     * Buscar o crear lead desde Facebook ID
     */
    private static function buscarOCrearLeadDesdeFacebookId($facebookId, $agentId)
    {
        try {
            $lead = CrmLead::where('agent_id', $agentId)
                ->where('metadata->facebook_id', $facebookId)
                ->first();

            if ($lead) {
                return $lead;
            }

            // Crear nuevo lead
            $lead = CrmLead::create([
                'nombre' => "Usuario Facebook {$facebookId}",
                'agent_id' => $agentId,
                'origin' => 'meta',
                'status' => 'nuevo',
                'score' => 0,
                'metadata' => [
                    'facebook_id' => $facebookId,
                    'fecha_sync_meta' => now(),
                ],
            ]);

            self::registrarSyncLog($agentId, 'facebook_lead_created', ['lead_id' => $lead->id, 'facebook_id' => $facebookId]);

            return $lead;
        } catch (\Exception $e) {
            Log::error('Error creating lead from Facebook ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear campaña en Meta
     */
    public static function crearCampañaEnMeta(CrmCampaign $campaign, $agentId)
    {
        try {
            $credentials = self::obtenerCredenciales($agentId);

            if (!$credentials) {
                Log::error("No Meta credentials found for agent {$agentId}");
                return false;
            }

            // Crear campaña en Meta API
            $url = self::META_MARKETING_API_URL . '/' . $credentials->meta_business_account_id . '/campaigns';

            $response = Http::post($url, [
                'name' => $campaign->nombre,
                'objective' => 'LEAD_GENERATION',
                'status' => 'PAUSED',
                'access_token' => $credentials->meta_access_token,
            ]);

            if (!$response->successful()) {
                Log::error('Error creating campaign in Meta: ' . $response->body());
                self::registrarSyncLog($agentId, 'campaign_creation_error', ['campaign_id' => $campaign->id, 'error' => $response->body()]);
                return false;
            }

            $metaCampaignData = $response->json();
            $metaCampaignId = $metaCampaignData['id'] ?? null;

            // Actualizar campaña con ID de Meta
            $campaign->update([
                'meta_campaign_id' => $metaCampaignId,
            ]);

            self::registrarSyncLog($agentId, 'campaign_created_in_meta', ['campaign_id' => $campaign->id, 'meta_campaign_id' => $metaCampaignId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception creating campaign in Meta: ' . $e->getMessage());
            self::registrarSyncLog($agentId, 'campaign_creation_exception', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Sincronizar leads desde Meta
     */
    public static function sincronizarLeadsDesMeta($agentId, $limit = 50)
    {
        try {
            $credentials = self::obtenerCredenciales($agentId);

            if (!$credentials) {
                Log::error("No Meta credentials found for agent {$agentId}");
                return ['success' => false, 'message' => 'No credentials'];
            }

            $url = self::META_MARKETING_API_URL . '/' . $credentials->meta_business_account_id . '/leads';

            $response = Http::get($url, [
                'access_token' => $credentials->meta_access_token,
                'limit' => $limit,
                'fields' => 'id,first_name,last_name,email,phone_number,created_time,ad_name,campaign_id,campaign_name',
            ]);

            if (!$response->successful()) {
                Log::error('Error syncing leads from Meta: ' . $response->body());
                self::registrarSyncLog($agentId, 'sync_error', ['error' => $response->body()]);
                return ['success' => false, 'message' => 'API error'];
            }

            $leads = $response->json()['data'] ?? [];
            $createdCount = 0;
            $updatedCount = 0;

            foreach ($leads as $metaLead) {
                $lead = self::crearOActualizarLead($metaLead, $agentId, $credentials);

                if ($lead) {
                    if ($lead->wasRecentlyCreated) {
                        $createdCount++;
                    } else {
                        $updatedCount++;
                    }
                }
            }

            self::registrarSyncLog($agentId, 'sync_completed', [
                'leads_created' => $createdCount,
                'leads_updated' => $updatedCount,
                'total' => count($leads),
            ]);

            return [
                'success' => true,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'total' => count($leads),
            ];
        } catch (\Exception $e) {
            Log::error('Exception syncing leads from Meta: ' . $e->getMessage());
            self::registrarSyncLog($agentId, 'sync_exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtener leads de campaña específica desde Meta
     */
    public static function obtenerLeadsDeCampañaDesMeta($metaCampaignId, $agentId)
    {
        try {
            $credentials = self::obtenerCredenciales($agentId);

            if (!$credentials) {
                return [];
            }

            $url = self::META_MARKETING_API_URL . "/{$metaCampaignId}/leads";

            $response = Http::get($url, [
                'access_token' => $credentials->meta_access_token,
                'fields' => 'id,first_name,last_name,email,phone_number,created_time,ad_name',
            ]);

            if (!$response->successful()) {
                return [];
            }

            return $response->json()['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error fetching leads from Meta campaign: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Enviar mensaje a través de Messenger
     */
    public static function enviarMensajeMessenger($facebookId, $mensaje, $agentId)
    {
        try {
            $credentials = self::obtenerCredenciales($agentId);

            if (!$credentials) {
                return false;
            }

            $url = self::META_MARKETING_API_URL . '/{$facebookId}/messages';

            $response = Http::post($url, [
                'message' => ['text' => $mensaje],
                'messaging_type' => 'RESPONSE',
                'access_token' => $credentials->meta_access_token,
            ]);

            if ($response->successful()) {
                self::registrarSyncLog($agentId, 'message_sent', ['facebook_id' => $facebookId]);
                return true;
            }

            Log::error('Error sending Messenger message: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending Messenger message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar log de sincronización
     */
    public static function registrarSyncLog($agentId, $tipo, $datos = [])
    {
        try {
            MetaSyncLog::create([
                'agent_id' => $agentId,
                'tipo' => $tipo,
                'datos' => $datos,
            ]);
        } catch (\Exception $e) {
            Log::error('Error registering Meta sync log: ' . $e->getMessage());
        }
    }

    /**
     * Obtener logs de sincronización
     */
    public static function obtenerLogs($agentId, $limite = 100)
    {
        return MetaSyncLog::where('agent_id', $agentId)
            ->latest('created_at')
            ->limit($limite)
            ->get();
    }

    /**
     * Renovar token de acceso
     */
    public static function renovarToken($credentialId, $nuevoToken, $fechaExpiracion = null)
    {
        try {
            $credential = AgentMetaCredential::findOrFail($credentialId);

            $credential->update([
                'meta_access_token' => $nuevoToken,
                'fecha_expiracion_token' => $fechaExpiracion,
            ]);

            self::registrarSyncLog($credential->agent_id, 'token_renewed', ['credential_id' => $credentialId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error renewing Meta token: ' . $e->getMessage());
            return false;
        }
    }
}
