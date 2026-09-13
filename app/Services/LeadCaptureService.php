<?php

namespace App\Services;

use App\Jobs\ScoreLeadJob;
use App\Models\AgentAdIntegration;
use App\Models\CrmCampaign;
use App\Models\CrmCampaignLead;
use App\Models\Lead;
use App\Models\Notifications;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

/**
 * Pipeline único de captura de leads de visitantes (guest-to-lead).
 *
 * Compartido por todos los puntos de captura:
 *  - formulario de la tarjeta del propietario (origin='formulario')
 *  - botón WhatsApp (origin='whatsapp')
 *  - solicitud de cita de invitado (origin='formulario')
 *
 * El flujo (nunca debe romper el guardado del lead):
 *  1. Crea el lead en crm_leads.
 *  2. Encola calificación IA (ScoreLeadJob).
 *  3. Vincula campaña de CRM (si utm_campaign/fbclid).
 *  4. Dispara Lead vía Conversions API (credenciales del agente dueño).
 *  5. Notifica al agente (in-app + WhatsApp configurado por el agente).
 */
class LeadCaptureService
{
    /**
     * Crea el lead a partir del request del frontend.
     *
     * @throws ValidationException
     */
    public static function createFromRequest(Request $request, string $origin = 'formulario'): Lead
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer|exists:propertys,id',
            'nombre' => 'required|string|max:191',
            'email' => 'nullable|email|max:191',
            'telefono' => 'nullable|string|max:191',
            'whatsapp' => 'nullable|string|max:191',
            'notas' => 'nullable|string|max:5000',
            'utm_source' => 'nullable|string|max:191',
            'utm_campaign' => 'nullable|string|max:191',
            'utm_medium' => 'nullable|string|max:191',
            'utm_content' => 'nullable|string|max:191',
            'utm_term' => 'nullable|string|max:191',
            'fbclid' => 'nullable|string|max:191',
            'page_url' => 'nullable|string|max:1000',
            'lead_uid' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // email/teléfono/whatsapp: al menos uno para poder contactar al lead.
        if (! $request->filled('email') && ! $request->filled('telefono') && ! $request->filled('whatsapp')) {
            throw ValidationException::withMessages([
                'contacto' => trans('Se requiere al menos un medio de contacto (email o teléfono)'),
            ]);
        }

        $property = Property::find($request->property_id);
        if (! $property) {
            throw ValidationException::withMessages([
                'property_id' => trans('Propiedad no encontrada'),
            ]);
        }

        $lead = Lead::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'whatsapp' => $request->whatsapp ?? $request->telefono,
            'property_id' => $property->id,
            'agent_id' => $property->added_by,
            'status' => 'nuevo',
            'origin' => $origin,
            'notas' => $request->notas,
            'utm_source' => $request->utm_source,
            'utm_campaign' => $request->utm_campaign,
            'utm_medium' => $request->utm_medium,
            'utm_content' => $request->utm_content,
            'utm_term' => $request->utm_term,
            'fbclid' => $request->fbclid,
            'page_url' => $request->page_url,
            'lead_uid' => $request->lead_uid,
            'metadata' => [
                'page' => 'property-details',
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'shot' => in_array($request->utm_source, ['facebook', 'instagram']) ? 'meta' : null,
            ],
            'score' => 0,
            'fecha_primer_contacto' => now(),
        ]);

        // Calificación IA en segundo plano — nunca rompe el registro.
        try {
            ScoreLeadJob::dispatch($lead->id);
        } catch (\Throwable $e) {
            Log::warning('LeadCaptureService: no se pudo encolar calificación IA', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Meta Ads: campaña + CAPI + notificación — aislados, nunca rompen.
        foreach ([
            'recordCampaign' => static fn () => self::recordCampaign($lead, $property),
            'trackLeadToMeta' => static fn () => self::trackLeadToMeta($lead, $property, $request),
            'notifyAgent' => static fn () => self::notifyAgent($lead, $property),
        ] as $name => $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                Log::warning("LeadCaptureService: {$name} falló", [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $lead;
    }

    /**
     * Vincula el lead a una campaña de CRM si llegó utm_campaign o fbclid.
     */
    public static function recordCampaign(Lead $lead, Property $property): void
    {
        $campaignKey = $lead->utm_campaign ?: $lead->fbclid;
        if (! $campaignKey && ! $lead->utm_source) {
            return;
        }

        $platform = in_array($lead->utm_source, ['facebook', 'instagram']) ? 'meta' : ($lead->utm_source ?: 'otro');

        $campaign = CrmCampaign::firstOrCreate(
            ['agent_id' => $property->added_by, 'nombre' => $campaignKey],
            [
                'platform' => $platform,
                'meta_campaign_id' => $lead->utm_campaign ?: null,
                'status' => 'activa',
                'leads_generados' => 0,
            ]
        );

        $exists = CrmCampaignLead::where('campaign_id', $campaign->id)
            ->where('lead_id', $lead->id)
            ->exists();

        if (! $exists) {
            CrmCampaignLead::create([
                'campaign_id' => $campaign->id,
                'lead_id' => $lead->id,
                'conversion_status' => 'lead',
                'respondio' => false,
                'interesado' => false,
                'compro' => false,
                'fecha_lead' => now(),
            ]);

            $campaign->increment('leads_generados');
        }
    }

    /**
     * Envía el evento Lead vía Conversions API usando las credenciales del
     * agente dueño de la propiedad (capturadas desde el frontend).
     */
    public static function trackLeadToMeta(Lead $lead, Property $property, Request $request): void
    {
        $integration = AgentAdIntegration::where('agent_id', $property->added_by)
            ->where('is_active', true)
            ->first();

        if (! $integration || ! $integration->pixel_id) {
            return;
        }

        $firstName = preg_replace('/\s+.*$/', '', trim((string) $lead->nombre));
        $nombreParts = preg_split('/\s+/', trim((string) $lead->nombre));
        $lastName = count($nombreParts) > 1 ? end($nombreParts) : null;

        $service = new MetaConversionsService();
        $service->track(
            ['pixel_id' => $integration->pixel_id, 'capi_access_token' => $integration->capi_access_token],
            'Lead',
            [
                'email' => $lead->email,
                'phone' => $lead->telefono ?: $lead->whatsapp,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'client_ip_address' => $request->ip(),
                'client_user_agent' => $request->userAgent(),
                'fbclid' => $lead->fbclid,
            ],
            [
                'content_name' => ($property->title ?? $property->name ?? 'Propiedad'),
                'content_id' => (string) $property->id,
                'source_url' => $lead->page_url ?: str_replace('api/user', '', $request->root()),
            ],
            'LEAD_'.$lead->id,
            $integration->capi_test_event_code
        );
    }

    /**
     * Notifica al agente de un nuevo lead: notificación in-app siempre,
     * y WhatsApp en vivo si el agente configuró su integración.
     */
    public static function notifyAgent(Lead $lead, Property $property): void
    {
        $title = 'Nuevo lead';
        $body = 'Nuevo lead de '.$lead->nombre.' para tu propiedad "'.($property->title ?? $property->name ?? '#'.$property->id).'".';

        try {
            Notifications::create([
                'title' => $title,
                'message' => $body,
                'image' => '',
                'type' => '2',
                'send_type' => '0',
                'customers_id' => $property->added_by,
                'propertys_id' => $property->id,
                'role_context' => 'agent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LeadCaptureService: notificación in-app falló', ['error' => $e->getMessage()]);
        }

        try {
            $integration = AgentAdIntegration::where('agent_id', $property->added_by)->first();
            if ($integration && $integration->whatsapp_sender_number) {
                app(WhatsAppService::class)->sendWithConfig(
                    $integration->whatsapp_mode,
                    $integration->whatsapp_token,
                    $integration->whatsapp_phone_id,
                    $integration->whatsapp_sender_number,
                    $body.' ¿Desea contactar?'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('LeadCaptureService: whatsapp falló', ['error' => $e->getMessage()]);
        }
    }
}