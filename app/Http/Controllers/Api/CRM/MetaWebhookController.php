<?php

namespace App\Http\Controllers\Api\CRM;

use App\Services\MetaService;
use App\Models\AgentMetaCredential;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /**
     * Validar webhook de Meta (GET request)
     */
    public function verify(Request $request)
    {
        $hubMode = $request->get('hub.mode');
        $hubChallenge = $request->get('hub.challenge');
        $hubVerifyToken = $request->get('hub.verify_token');

        // Obtener todas las credenciales activas para buscar la correcta
        $credential = AgentMetaCredential::where('webhook_verify_token', $hubVerifyToken)
            ->where('activa', true)
            ->first();

        if (!$credential) {
            Log::warning('Meta webhook verification failed: credential not found');
            return response('Unauthorized', 401);
        }

        // Validar webhook
        $challenge = MetaService::validarWebhook(
            $hubMode,
            $hubChallenge,
            $hubVerifyToken,
            $credential->webhook_verify_token
        );

        if (!$challenge) {
            return response('Unauthorized', 401);
        }

        Log::info('Meta webhook verified for agent: ' . $credential->agent_id);
        return response($challenge, 200);
    }

    /**
     * Recibir eventos de webhook de Meta (POST request)
     */
    public function handle(Request $request)
    {
        try {
            $data = $request->all();

            if (!isset($data['entry']) || empty($data['entry'])) {
                return response()->json(['status' => 'ok']);
            }

            // Obtener el webhook verify token del header o del body
            $webhookVerifyToken = $request->header('X-Hub-Signature') ?? 
                                  $request->get('webhook_verify_token');

            // Buscar la credencial por webhook token
            $credential = AgentMetaCredential::where('webhook_verify_token', $webhookVerifyToken)
                ->orWhere('id', $request->get('credential_id'))
                ->first();

            if (!$credential) {
                // Intentar identificar por signature
                $credential = $this->validarSignature($request);

                if (!$credential) {
                    Log::warning('Meta webhook received but credential not found');
                    return response()->json(['status' => 'ok']);
                }
            }

            // Procesar webhook
            $resultado = MetaService::procesarWebhook($data, $credential->agent_id);

            if ($resultado) {
                Log::info('Meta webhook processed successfully for agent: ' . $credential->agent_id);
                return response()->json(['status' => 'ok']);
            } else {
                Log::warning('Meta webhook processing failed for agent: ' . $credential->agent_id);
                return response()->json(['status' => 'processed_with_errors']);
            }
        } catch (\Exception $e) {
            Log::error('Error handling Meta webhook: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Validar firma de webhook
     */
    private function validarSignature(Request $request)
    {
        $signature = $request->header('X-Hub-Signature');

        if (!$signature) {
            return null;
        }

        // Signature format: sha1=<hash>
        list($algo, $hash) = explode('=', $signature, 2);

        // Buscar credencial por app secret
        $credentials = AgentMetaCredential::where('activa', true)->get();

        foreach ($credentials as $credential) {
            $payload = $request->getContent();
            $expectedHash = hash_hmac('sha1', $payload, $credential->meta_app_secret);

            if (hash_equals($hash, $expectedHash)) {
                return $credential;
            }
        }

        return null;
    }

    /**
     * Obtener logs de sincronización de Meta
     */
    public function obtenerLogs(Request $request)
    {
        $user = auth()->user();
        $limite = $request->get('limite', 100);

        if ($user->role !== 'admin' && $request->get('agent_id') && $request->get('agent_id') != $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $agentId = $request->get('agent_id') ?? $user->id;
        $logs = MetaService::obtenerLogs($agentId, $limite);

        return response()->json([
            'data' => $logs,
            'total' => $logs->count(),
        ]);
    }

    /**
     * Sincronizar leads manualmente
     */
    public function sincronizarLeads(Request $request)
    {
        $user = auth()->user();
        $agentId = $user->id;

        $resultado = MetaService::sincronizarLeadsDesMeta($agentId);

        if ($resultado['success']) {
            return response()->json([
                'message' => 'Sincronización completada',
                'data' => $resultado,
            ]);
        } else {
            return response()->json([
                'message' => 'Error en sincronización',
                'error' => $resultado['message'],
            ], 400);
        }
    }

    /**
     * Crear campaña en Meta
     */
    public function crearCampaña(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'campaign_id' => 'required|exists:crm_campaigns,id',
        ]);

        $campaign = \App\Models\CrmCampaign::findOrFail($validated['campaign_id']);

        // Autorización
        if ($user->role !== 'admin' && $campaign->agent_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $resultado = MetaService::crearCampañaEnMeta($campaign, $campaign->agent_id);

        if ($resultado) {
            return response()->json([
                'message' => 'Campaña creada en Meta exitosamente',
                'data' => $campaign->fresh(),
            ]);
        } else {
            return response()->json([
                'message' => 'Error al crear campaña en Meta',
            ], 400);
        }
    }

    /**
     * Obtener leads de campaña Meta
     */
    public function obtenerLeadsDeCampaña(Request $request, $campaignId)
    {
        $user = auth()->user();

        $campaign = \App\Models\CrmCampaign::findOrFail($campaignId);

        // Autorización
        if ($user->role !== 'admin' && $campaign->agent_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!$campaign->meta_campaign_id) {
            return response()->json(['message' => 'Esta campaña no tiene ID de Meta'], 400);
        }

        $leads = MetaService::obtenerLeadsDeCampañaDesMeta($campaign->meta_campaign_id, $campaign->agent_id);

        return response()->json([
            'data' => $leads,
            'total' => count($leads),
        ]);
    }

    /**
     * Enviar mensaje por Messenger
     */
    public function enviarMensaje(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'lead_id' => 'required|exists:crm_leads,id',
            'mensaje' => 'required|string|min:1|max:1000',
        ]);

        $lead = \App\Models\CrmLead::findOrFail($validated['lead_id']);

        // Autorización
        if ($user->role !== 'admin' && $lead->agent_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $facebookId = $lead->metadata['facebook_id'] ?? null;

        if (!$facebookId) {
            return response()->json(['message' => 'Este lead no tiene Facebook ID'], 400);
        }

        $resultado = MetaService::enviarMensajeMessenger($facebookId, $validated['mensaje'], $lead->agent_id);

        if ($resultado) {
            // Registrar interacción
            $lead->registrarInteraccion(
                'facebook',
                $validated['mensaje'],
                'mensaje_enviado'
            );

            return response()->json([
                'message' => 'Mensaje enviado exitosamente',
                'data' => [
                    'lead_id' => $lead->id,
                    'mensaje' => $validated['mensaje'],
                ],
            ]);
        } else {
            return response()->json([
                'message' => 'Error al enviar mensaje',
            ], 400);
        }
    }

    /**
     * Renovar token de Meta
     */
    public function renovarToken(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'credential_id' => 'required|exists:agent_meta_credentials,id',
            'nuevo_token' => 'required|string',
            'fecha_expiracion' => 'nullable|date',
        ]);

        $credential = \App\Models\AgentMetaCredential::findOrFail($validated['credential_id']);

        // Autorización
        if ($user->role !== 'admin' && $credential->agent_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $resultado = MetaService::renovarToken(
            $validated['credential_id'],
            $validated['nuevo_token'],
            $validated['fecha_expiracion'] ?? null
        );

        if ($resultado) {
            return response()->json([
                'message' => 'Token renovado exitosamente',
                'data' => $credential->fresh(),
            ]);
        } else {
            return response()->json([
                'message' => 'Error al renovar token',
            ], 400);
        }
    }
}
