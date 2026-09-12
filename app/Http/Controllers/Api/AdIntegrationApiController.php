<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentAdIntegration;
use App\Services\ApiResponseService;
use App\Services\MetaConversionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Integraciones de marketing por agente.
 * Las credenciales (Pixel ID, Access Token CAPI, Test Event Code, WhatsApp) se
 * capturan DESDE EL FRONTEND en el dashboard del agente y se guardan cifradas.
 */
class AdIntegrationApiController extends Controller
{
    public function show(Request $request)
    {
        $integration = AgentAdIntegration::where('agent_id', Auth::id())->first();

        if (! $integration) {
            return ApiResponseService::successResponse('ok', [
                'agent_id' => Auth::id(),
                'pixel_id' => null,
                'ad_account_id' => null,
                'capi_access_token' => null,
                'capi_token_configured' => false,
                'capi_test_event_code' => null,
                'whatsapp_mode' => 'mock',
                'whatsapp_token' => null,
                'whatsapp_token_configured' => false,
                'whatsapp_phone_id' => null,
                'whatsapp_sender_number' => null,
                'is_active' => false,
            ]);
        }

        return ApiResponseService::successResponse('ok', $integration->toMaskedArray());
    }

    public function store(Request $request)
    {
        $agentId = Auth::id();

        $validator = Validator::make($request->all(), [
            'pixel_id' => 'nullable|string|max:191',
            'ad_account_id' => 'nullable|string|max:191',
            'capi_access_token' => 'nullable|string|max:600',
            'capi_test_event_code' => 'nullable|string|max:191',
            'whatsapp_mode' => 'nullable|in:mock,live',
            'whatsapp_token' => 'nullable|string|max:600',
            'whatsapp_phone_id' => 'nullable|string|max:191',
            'whatsapp_sender_number' => 'nullable|string|max:191',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }

        $existing = AgentAdIntegration::where('agent_id', $agentId)->first();

        $data = [
            'pixel_id' => $request->filled('pixel_id') ? $request->pixel_id : ($existing?->pixel_id ?? null),
            'ad_account_id' => $request->filled('ad_account_id') ? $request->ad_account_id : ($existing?->ad_account_id ?? null),
            'capi_access_token' => $request->filled('capi_access_token') ? $request->capi_access_token : null,
            'capi_test_event_code' => $request->filled('capi_test_event_code') ? $request->capi_test_event_code : ($existing?->capi_test_event_code ?? null),
            'whatsapp_mode' => $request->filled('whatsapp_mode') ? $request->whatsapp_mode : ($existing?->whatsapp_mode ?? 'mock'),
            'whatsapp_token' => $request->filled('whatsapp_token') ? $request->whatsapp_token : null,
            'whatsapp_phone_id' => $request->filled('whatsapp_phone_id') ? $request->whatsapp_phone_id : ($existing?->whatsapp_phone_id ?? null),
            'whatsapp_sender_number' => $request->filled('whatsapp_sender_number') ? $request->whatsapp_sender_number : ($existing?->whatsapp_sender_number ?? null),
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : ($existing?->is_active ?? true),
        ];

        // Si el cliente no envía token pero ya había uno guardado, se conserva.
        $data['capi_access_token'] = $data['capi_access_token'] ?? ($existing?->capi_access_token ?? null);
        $data['whatsapp_token'] = $data['whatsapp_token'] ?? ($existing?->whatsapp_token ?? null);

        AgentAdIntegration::updateOrCreate(['agent_id' => $agentId], $data);

        $integration = AgentAdIntegration::where('agent_id', $agentId)->firstOrFail();

        return ApiResponseService::successResponse('Integración guardada correctamente.', $integration->toMaskedArray());
    }

    /**
     * Envía un evento de TEST real a Meta (test_event_code) para que el agente
     * valide desde su dashboard que las credenciales funcionan.
     */
    public function test(Request $request)
    {
        $integration = AgentAdIntegration::where('agent_id', Auth::id())->first();

        if (! $integration || ! $integration->pixel_id) {
            return ApiResponseService::errorResponse('Primero guarda tu Pixel ID y Access Token.');
        }

        $userData = [
            'email' => $request->email ?? 'test@omko.do',
            'phone' => $request->phone ?? null,
            'first_name' => $request->first_name ?? 'Test',
            'last_name' => $request->last_name ?? 'OMKO',
            'client_ip_address' => $request->ip(),
            'client_user_agent' => $request->userAgent(),
        ];

        $service = new MetaConversionsService();
        $result = $service->track(
            ['pixel_id' => $integration->pixel_id, 'capi_access_token' => $integration->capi_access_token],
            'Lead',
            $userData,
            ['content_name' => 'Test OMKO', 'content_id' => 'test', 'source_url' => $request->url()],
            'TEST_' . date('YmdHis') . '_' . Auth::id(),
            $integration->capi_test_event_code ?: $request->test_event_code
        );

        if ($result['success']) {
            return ApiResponseService::successResponse('Evento de prueba enviado a Meta.', $result);
        }

        return ApiResponseService::errorResponse('No se pudo enviar el evento de prueba.', $result['body'] ?? ($result['error'] ?? $result['reason']));
    }
}