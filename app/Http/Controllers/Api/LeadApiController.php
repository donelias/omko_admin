<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AgentBookingPreference;
use App\Models\CrmInteraction;
use App\Models\CrmLeadScoring;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Services\HelperService;
use App\Services\LeadCaptureService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LeadApiController extends Controller
{
    /**
     * Registra un lead de un visitante NO autenticado (guest-to-lead) para una
     * propiedad concreta, sin forzarle a crearse una cuenta o iniciar sesión.
     *
     * Delega todo el pipeline (crear lead + campaña + CAPI + notificación) en
     * LeadCaptureService para compartirlo con las citas de invitados.
     */
    public function storeGuest(Request $request)
    {
        try {
            $lead = LeadCaptureService::createFromRequest($request, $request->input('origin', 'formulario'));
        } catch (ValidationException $e) {
            return response()->json([
                'error' => true,
                'message' => $e->errors() ? collect($e->errors())->flatten()->first() : trans('Datos inválidos'),
            ], 422);
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
     * Solicitud de cita de un visitante SIN cuenta (guest-to-appointment).
     *
     * Crea primero el lead (LeadCaptureService) y luego la cita en appointments
     * con user_id NULL + datos del visitante (guest_*) + lead_id asociado, de
     * modo que el agente la vea junto al resto de sus citas. El slot se valida
     * contra preferencias de horario del agente y traslapes con otras citas.
     */
    public function createGuestAppointment(Request $request)
    {
        $adminTimezone = HelperService::getSettingData('timezone') ?: 'UTC';
        $currentDate = Carbon::now()->setTimezone($adminTimezone)->toDateString();

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer|exists:propertys,id',
            'nombre' => 'required|string|max:191',
            'email' => 'nullable|email|max:191',
            'telefono' => 'nullable|string|max:191',
            'meeting_type' => 'required|in:phone,virtual,in_person',
            'date' => 'required|date_format:Y-m-d|after_or_equal:'.$currentDate,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        if (! $request->filled('email') && ! $request->filled('telefono')) {
            return response()->json([
                'error' => true,
                'message' => trans('Se requiere al menos un medio de contacto (email o teléfono)'),
            ], 422);
        }

        $property = Property::with('customer')->find($request->property_id);
        if (! $property) {
            return response()->json(['error' => true, 'message' => trans('Propiedad no encontrada')], 404);
        }

        if ($property->expiry_date && Carbon::parse($property->expiry_date)->lt(Carbon::now()->startOfDay())) {
            return response()->json([
                'error' => true,
                'message' => trans('Esta propiedad ha expirado y no puede recibir citas'),
            ], 422);
        }

        $isAdminAgent = $property->added_by == 0;
        if ($isAdminAgent) {
            $agentId = User::where('type', 0)->first()->id ?? 0;
        } else {
            $agentId = $property->added_by;
        }

        $bookingPref = $isAdminAgent
            ? AgentBookingPreference::where(['is_admin_data' => 1, 'admin_id' => $agentId])->first()
            : AgentBookingPreference::where('agent_id', $agentId)->first();

        $agentTimezone = $bookingPref && $bookingPref->timezone
            ? $bookingPref->timezone
            : (config('app.timezone') ?? 'UTC');

        $slotStartAgent = Carbon::parse($request->date.' '.$request->start_time, $adminTimezone)->setTimezone($agentTimezone);
        $slotEndAgent = Carbon::parse($request->date.' '.$request->end_time, $adminTimezone)->setTimezone($agentTimezone);
        $startUtc = (clone $slotStartAgent)->setTimezone('UTC')->toDateTimeString();
        $endUtc = (clone $slotEndAgent)->setTimezone('UTC')->toDateTimeString();

        if ($slotEndAgent <= $slotStartAgent) {
            return response()->json(['error' => true, 'message' => trans('La hora de fin debe ser mayor que la de inicio')], 422);
        }

        $meetingDurationMinutes = (int) ($bookingPref->meeting_duration_minutes ?? 0);
        if ($meetingDurationMinutes > 0 && $slotStartAgent->diffInMinutes($slotEndAgent) !== $meetingDurationMinutes) {
            return response()->json([
                'error' => true,
                'message' => trans('La duración del turno no corresponde a la configurada por el agente'),
            ], 422);
        }

        // Traslape con citas existentes del agente (pending/confirmed/rescheduled).
        $overlap = Appointment::where(function ($q) use ($agentId, $isAdminAgent) {
            if ($isAdminAgent) {
                $q->where('admin_id', $agentId)->where('is_admin_appointment', 1);
            } else {
                $q->where('agent_id', $agentId);
            }
        })
            ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
            ->where('start_at', '<', $endUtc)
            ->where('end_at', '>', $startUtc)
            ->exists();

        if ($overlap) {
            return response()->json(['error' => true, 'message' => trans('El horario seleccionado no está disponible')], 422);
        }

        $lead = null;
        try {
            $lead = LeadCaptureService::createFromRequest($request); // origin='formulario'
        } catch (ValidationException $e) {
            return response()->json([
                'error' => true,
                'message' => $e->errors() ? collect($e->errors())->flatten()->first() : trans('Datos inválidos'),
            ], 422);
        }

        $autoConfirm = (bool) ($bookingPref->auto_confirm ?? false);

        $appointment = Appointment::create([
            'is_admin_appointment' => $isAdminAgent ? 1 : 0,
            'admin_id' => $isAdminAgent ? $agentId : null,
            'agent_id' => $isAdminAgent ? null : $agentId,
            'user_id' => null,
            'guest_name' => $request->nombre,
            'guest_email' => $request->email,
            'guest_phone' => $request->telefono,
            'lead_id' => $lead->id,
            'property_id' => $property->id,
            'meeting_type' => $request->meeting_type,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'status' => $autoConfirm ? 'confirmed' : 'pending',
            'is_auto_confirmed' => $autoConfirm,
            'last_status_updated_by' => $autoConfirm ? 'system' : 'user',
            'notes' => $request->notes,
        ]);

        $appointment->start_at = Carbon::parse($appointment->start_at, 'UTC')->setTimezone($adminTimezone)->format('Y-m-d H:i:s');
        $appointment->end_at = Carbon::parse($appointment->end_at, 'UTC')->setTimezone($adminTimezone)->format('Y-m-d H:i:s');

        return response()->json([
            'error' => false,
            'message' => trans('Cita solicitada correctamente'),
            'data' => [
                'appointment' => $appointment,
                'lead_id' => $lead->id,
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
