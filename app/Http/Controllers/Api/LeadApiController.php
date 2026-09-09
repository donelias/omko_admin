<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScoreLeadJob;
use App\Models\CrmInteraction;
use App\Models\CrmLeadScoring;
use App\Models\Lead;
use App\Models\Property;
use App\Services\HelperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LeadApiController extends Controller
{
    /**
     * Registra un lead de un visitante NO autenticado (guest-to-lead) para una
     * propiedad concreta, sin forzarle a crearse una cuenta o iniciar sesión.
     * FASE 3 (T4): aumento de conversión — captura de leads anónimos.
     *
     * El agent_id se resuelve automáticamente desde el dueño de la propiedad
     * (propertys.added_by). origin = 'formulario'.
     */
    public function storeGuest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer|exists:propertys,id',
            'nombre' => 'required|string|max:191',
            'email' => 'nullable|email|max:191',
            'telefono' => 'nullable|string|max:191',
            'whatsapp' => 'nullable|string|max:191',
            'notas' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // email y teléfono: al menos uno debe venir (para poder contactar al lead)
        if (! $request->filled('email') && ! $request->filled('telefono') && ! $request->filled('whatsapp')) {
            return response()->json([
                'error' => true,
                'message' => trans('Se requiere al menos un medio de contacto (email o teléfono)'),
            ], 422);
        }

        $property = Property::find($request->property_id);
        if (! $property) {
            return response()->json([
                'error' => true,
                'message' => trans('Propiedad no encontrada'),
            ], 404);
        }

        $lead = Lead::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'whatsapp' => $request->whatsapp ?? $request->telefono,
            'property_id' => $property->id,
            'agent_id' => $property->added_by,
            'status' => 'nuevo',
            'origin' => 'formulario',
            'notas' => $request->notas,
            'metadata' => [
                'page' => 'property-details',
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            ],
            'score' => 0,
            'fecha_primer_contacto' => now(),
        ]);

        // FASE 7 (T1) — Calificación automática por IA en segundo plano.
        // Si el despacho falla (config de cola, etc.), el lead ya quedó creado
        // y solo se loguea — el registro nunca debe fallar por esta operación.
        try {
            ScoreLeadJob::dispatch($lead->id);
        } catch (\Throwable $e) {
            Log::warning('storeGuest: no se pudo encolar calificación IA', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'error' => false,
            'message' => trans('Interest submitted successfully'),
            'data' => [
                'id' => $lead->id,
            ],
        ]);
    }

    /**
     * FASE 5 (T4) — Lista los leads asignados al agente (Customer) autenticado.
     * Filtrables por status y origen, con paginación.
     */
    public function myLeads(Request $request)
    {
        $agentId = Auth::id();

        $query = Lead::query()
            ->with(['property:id,slug_id,title', 'scoring'])
            ->where('agent_id', $agentId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('origin')) {
            $query->where('origin', $request->origin);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('telefono', 'LIKE', "%{$search}%");
            });
        }

        $query->orderByDesc('created_at');

        $perPage = $request->filled('limit') ? (int) $request->limit : 15;
        $leads = $query->paginate($perPage);

        // FASE 8 (T6 restante) — leads pagados: enmascarar contactos si el plan
        // activo del agente no incluye la feature crm_leads_access.
        $access = HelperService::checkLeadAccessLimit($agentId);
        $accessForResponse = [
            'available' => $access['available'],
            'unlimited' => $access['unlimited'],
            'remaining' => $access['remaining'],
        ];

        $items = collect($leads->items())->map(function ($lead) use ($access) {
            $contactHidden = ! $access['available'];

            return $this->decorateLead($lead, $contactHidden);
        });

        return response()->json([
            'error' => false,
            'data' => $items,
            'total' => $leads->total(),
            'current_page' => $leads->currentPage(),
            'last_page' => $leads->lastPage(),
            'contact_access' => $accessForResponse,
        ]);
    }

    /**
     * FASE 8 (T6 restante) — Desbloquea el contacto completo de un lead propio.
     * Consume un crédito de crm_leads_access del plan activo (agent/agencia).
     */
    public function unlock(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $lead = $this->findLeadsForAgent($request->lead_id);
        if (! $lead) {
            return response()->json(['error' => true, 'message' => trans('Lead no encontrado o sin acceso')], 404);
        }

        $access = HelperService::checkLeadAccessLimit(Auth::id());
        if (! $access['available']) {
            return response()->json([
                'error' => true,
                'code' => 'plan_required',
                'message' => trans('Agrega un plan con acceso a contactos de leads para desbloquear este lead'),
                'data' => [
                    'available' => false,
                    'unlimited' => $access['unlimited'],
                    'remaining' => $access['remaining'] ?? 0,
                ],
            ], 402);
        }

        $remaining = HelperService::consumeLeadAccess(Auth::id());

        return response()->json([
            'error' => false,
            'message' => trans('Contacto desbloqueado'),
            'data' => [
                'lead_id' => $lead->id,
                'nombre' => $lead->nombre,
                'email' => $lead->email,
                'telefono' => $lead->telefono,
                'whatsapp' => $lead->whatsapp,
                'remaining' => $remaining,
            ],
        ]);
    }

    /**
     * Decora el lead para salida: oculta el contacto si no hay acceso y agrega
     * flags útiles para el frontend.
     */
    protected function decorateLead(Lead $lead, bool $contactHidden): array
    {
        $hasEmail = ! empty($lead->email);
        $hasTelefono = ! empty($lead->telefono);
        $hasWhatsapp = ! empty($lead->whatsapp);

        return [
            'id' => $lead->id,
            'nombre' => $lead->nombre,
            'email' => $contactHidden && $hasEmail ? $this->maskContact($lead->email) : $lead->email,
            'telefono' => $contactHidden && $hasTelefono ? $this->maskContact($lead->telefono) : $lead->telefono,
            'whatsapp' => $contactHidden && $hasWhatsapp ? $this->maskContact($lead->whatsapp) : $lead->whatsapp,
            'has_email' => $hasEmail,
            'has_telefono' => $hasTelefono,
            'has_whatsapp' => $hasWhatsapp,
            'property' => $lead->property ? ['id' => $lead->property->id, 'title' => $lead->property->title, 'slug_id' => $lead->property->slug_id] : null,
            'status' => $lead->status,
            'origin' => $lead->origin,
            'score' => $lead->score,
            'notas' => $lead->notas,
            'scoring' => $lead->scoring ? $lead->scoring->only(['recomendacion', 'score_total']) : null,
            'created_at' => $lead->created_at,
            'fecha_ultima_interaccion' => $lead->fecha_ultima_interaccion,
            'contact_hidden' => $contactHidden,
        ];
    }

    /**
     * Enmascara un medio de contacto (email o teléfono).
     */
    protected function maskContact(string $value): string
    {
        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return substr($local, 0, 2).'***@'.$domain;
        }

        if (strlen($value) <= 5) {
            return substr($value, 0, 1).'***';
        }

        return substr($value, 0, 3).'***'.substr($value, -2);
    }

    /**
     * FASE 5 (T4) — Cambia el estado del lead, registra la interacción y
     * actualiza la fecha de última interacción. Solo el agente asignado o el
     * admin pueden modificarlo.
     */
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|integer',
            'status' => 'required|in:nuevo,contactado,interesado,calificado,ganado,perdido,descartado',
            'nota' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $lead = $this->findLeadsForAgent($request->lead_id);
        if (! $lead) {
            return response()->json(['error' => true, 'message' => trans('Lead no encontrado o sin acceso')], 404);
        }

        $previousStatus = $lead->status;
        $lead->status = $request->status;
        $lead->fecha_ultima_interaccion = now();

        if ($request->filled('nota')) {
            $lead->notas = trim(($lead->notas ?? '')."\n".$request->nota);
        }

        $lead->save();

        $this->recordInteraction($lead, [
            'type' => 'nota_interna',
            'contenido' => $request->nota ?: "Estado cambiado de {$previousStatus} a {$lead->status}",
            'status_anterior' => $previousStatus,
            'status_nuevo' => $lead->status,
        ]);

        return response()->json([
            'error' => false,
            'message' => trans('Estado actualizado correctamente'),
            'data' => ['id' => $lead->id, 'status' => $lead->status],
        ]);
    }

    /**
     * FASE 5 (T4) — Registra una interacción (llamada, email, whatsapp, etc.)
     * sobre un lead y refresca su fecha de última interacción.
     */
    public function addInteraction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|integer',
            'type' => 'required|in:llamada,mensaje_texto,email,whatsapp,facebook,instagram,visita_personal,video_llamada,nota_interna',
            'contenido' => 'required|string|max:5000',
            'resultado' => 'nullable|string|max:5000',
            'duracion_segundos' => 'nullable|integer',
            'proxima_accion' => 'nullable|string|max:5000',
            'fecha_proxima_accion' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $lead = $this->findLeadsForAgent($request->lead_id);
        if (! $lead) {
            return response()->json(['error' => true, 'message' => trans('Lead no encontrado o sin acceso')], 404);
        }

        $interaction = $this->recordInteraction($lead, $request->only([
            'type', 'contenido', 'resultado', 'duracion_segundos', 'proxima_accion', 'fecha_proxima_accion',
        ]));

        return response()->json([
            'error' => false,
            'message' => trans('Interacción registrada'),
            'data' => ['id' => $interaction->id],
        ]);
    }

    /**
     * FASE 5 (T4) — Reasigna un lead a otro agente (customers.is_agent=1).
     */
    public function reassign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|integer',
            'agent_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $lead = Lead::find($request->lead_id);
        if (! $lead) {
            return response()->json(['error' => true, 'message' => trans('Lead no encontrado')], 404);
        }

        $targetAgent = \App\Models\Customer::where('id', $request->agent_id)->where('is_agent', 1)->exists();

        if (! $targetAgent) {
            return response()->json(['error' => true, 'message' => trans('Agente destino no válido')], 422);
        }

        $lead->agent_id = $request->agent_id;
        $lead->save();

        return response()->json([
            'error' => false,
            'message' => trans('Lead reasignado correctamente'),
            'data' => ['id' => $lead->id, 'agent_id' => $lead->agent_id],
        ]);
    }

    /**
     * FASE 5 (T4) — Registra/actualiza el scoring del lead y espeja score_total
     * sobre la columna score de crm_leads.
     */
    public function registerScore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|integer',
            'score_total' => 'required|integer|between:0,20',
            'score_engagement' => 'nullable|integer|between:0,5',
            'score_interes' => 'nullable|integer|between:0,5',
            'score_tiempo' => 'nullable|integer|between:0,5',
            'score_comportamiento' => 'nullable|integer|between:0,5',
            'score_capacidad' => 'nullable|integer|between:0,5',
            'recomendacion' => 'nullable|in:muy_caliente,caliente,tibio,frio,descartado',
            'factores' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $lead = $this->findLeadsForAgent($request->lead_id);
        if (! $lead) {
            return response()->json(['error' => true, 'message' => trans('Lead no encontrado o sin acceso')], 404);
        }

        $scoring = CrmLeadScoring::firstOrNew(['lead_id' => $lead->id]);
        $scoring->score_total = $request->score_total;
        $scoring->score_engagement = $request->score_engagement ?? 0;
        $scoring->score_interes = $request->score_interes ?? 0;
        $scoring->score_tiempo = $request->score_tiempo ?? 0;
        $scoring->score_comportamiento = $request->score_comportamiento ?? 0;
        $scoring->score_capacidad = $request->score_capacidad ?? 0;
        $scoring->recomendacion = $request->recomendacion ?? $this->recomendationFromScore($request->score_total);
        $scoring->factores = $request->factores;
        $scoring->ultima_actualizacion = now();
        $scoring->save();

        $lead->score = $request->score_total;
        $lead->save();

        return response()->json([
            'error' => false,
            'message' => trans('Scoring actualizado'),
            'data' => ['id' => $scoring->id, 'score_total' => $scoring->score_total],
        ]);
    }

    /**
     * Devuelve el lead si pertenece al agente autenticado.
     */
    protected function findLeadsForAgent(int $leadId): ?Lead
    {
        return Lead::where('id', $leadId)->where('agent_id', Auth::id())->first();
    }

    /**
     * Registra una interacción en crm_interactions y refresca fecha_ultima_interaccion.
     */
    protected function recordInteraction(Lead $lead, array $data): CrmInteraction
    {
        $interaction = CrmInteraction::create(array_merge([
            'lead_id' => $lead->id,
            'agent_id' => Auth::id(),
            'contenido' => $data['contenido'] ?? '',
        ], $data));

        $lead->fecha_ultima_interaccion = now();
        $lead->save();

        return $interaction;
    }

    protected function recomendationFromScore(int $score): string
    {
        return match (true) {
            $score >= 0 && $score <= 4 => 'frio',
            $score <= 8 => 'tibio',
            $score <= 12 => 'caliente',
            $score <= 16 => 'muy_caliente',
            default => 'muy_caliente',
        };
    }
}
