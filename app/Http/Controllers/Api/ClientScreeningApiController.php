<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentScreeningDecisionRequest;
use App\Http\Requests\StoreClientScreeningRequest;
use App\Models\ClientScreening;
use App\Models\ClientScreeningQuestion;
use App\Models\Notifications;
use App\Models\Property;
use App\Services\ClientQualificationService;
use App\Services\ClientScreeningNotificationService;
use App\Services\HelperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClientScreeningApiController extends Controller
{
    /**
     * Preguntas activas del formulario de depuración (público).
     * Se usa para renderizar el cuestionario en el frontend.
     */
    public function formQuestions(Request $request)
    {
        $questions = ClientScreeningQuestion::query()
            ->where('status', true)
            ->orderBy('rank')
            ->get(['id', 'question_key', 'question_text', 'field_type', 'options', 'placeholder', 'is_required']);

        return response()->json([
            'error' => false,
            'data' => [
                'questions' => $questions,
            ],
        ]);
    }

    /**
     * Envío del formulario de depuración (público).
     *
     * Flujo:
     *  1. Crea screening + respuestas (ClientQualificationService).
     *  2. Encola análisis IA (AnalyzeClientScreeningJob).
     *  3. Notifica al agente (in-app + correo + WhatsApp).
     *  4. Confirma al cliente por correo.
     */
    public function submit(StoreClientScreeningRequest $request)
    {
        $property = Property::find($request->property_id);
        if (! $property) {
            return response()->json(['error' => true, 'message' => 'Propiedad no encontrada.'], 404);
        }

        $agentId = $property->added_by;

        $answers = $request->input('answers');

        $screening = ClientQualificationService::storeSubmission([
            'property_id' => $property->id,
            'agent_id' => $agentId,
            'lead_id' => null,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'origin' => $request->input('origin', 'link'),
        ], $answers);

        // Notificaciones — siempre aisladas en try/catch.
        foreach ([
            'notificarAgente' => static fn () => self::notifyAgentInApp($screening, $property, $agentId),
            'correoAlAgente' => static fn () => ClientScreeningNotificationService::sendToAgent($screening),
            'correoAlCliente' => static fn () => ClientScreeningNotificationService::sendToClient($screening),
            'whatsappAlAgente' => static fn () => self::notifyAgentWhatsApp($screening, $property, $agentId),
        ] as $name => $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                Log::warning('ClientScreeningApiController submit: '.$name.' falló', [
                    'screening_id' => $screening->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'error' => false,
            'message' => 'Formulario enviado correctamente. El agente revisará su solicitud pronto.',
            'data' => [
                'screening_id' => $screening->id,
            ],
        ]);
    }

    /**
     * Listado de screenings del agente autenticado.
     */
    public function myScreenings(Request $request)
    {
        $agentId = Auth::id();

        $query = ClientScreening::query()
            ->with(['property:id,title,price,city,title_image,slug_id'])
            ->where('agent_id', $agentId);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('nivel')) {
            $query->where('nivel', $request->nivel);
        }

        $screenings = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'error' => false,
            'data' => $screenings,
        ]);
    }

    /**
     * Detalle de un screening con respuestas e IA.
     */
    public function show(int $id)
    {
        $screening = ClientScreening::with([
            'responses.question',
            'property:id,title,price,city,title_image,slug_id,bedrooms,bathrooms,build_area',
            'lead:id,nombre,email,telefono,score',
        ])
            ->where('agent_id', Auth::id())
            ->find($id);

        if (! $screening) {
            return response()->json(['error' => true, 'message' => 'Screening no encontrado.'], 404);
        }

        return response()->json([
            'error' => false,
            'data' => $screening,
        ]);
    }

    /**
     * Decisión del agente sobre un screening.
     */
    public function decide(AgentScreeningDecisionRequest $request, int $id)
    {
        $screening = ClientScreening::where('agent_id', Auth::id())->find($id);

        if (! $screening) {
            return response()->json(['error' => true, 'message' => 'Screening no encontrado.'], 404);
        }

        $screening = ClientQualificationService::decide(
            $screening,
            $request->decision,
            $request->input('notas')
        );

        // Correo de decisión al cliente.
        try {
            ClientScreeningNotificationService::sendDecisionToClient($screening);
        } catch (\Throwable $e) {
            Log::warning('ClientScreeningApiController decide: correo de decisión falló', [
                'screening_id' => $screening->id,
                'error' => $e->getMessage(),
            ]);
        }

        $label = $screening->decision_agente === 'aprobado' ? 'aprobado' : 'rechazado';

        return response()->json([
            'error' => false,
            'message' => 'Solicitud '.$label.' correctamente.',
            'data' => [
                'id' => $screening->id,
                'status' => $screening->status,
                'decision' => $screening->decision_agente,
            ],
        ]);
    }

    /**
     * Notificación in-app al agente (patrón LeadCaptureService).
     */
    private static function notifyAgentInApp(ClientScreening $screening, Property $property, $agentId): void
    {
        $propertyName = $property->title ?? $property->name ?? 'la propiedad';

        Notifications::create([
            'title' => 'Nuevo screening de cliente',
            'message' => $screening->customer_name.' completó el formulario de depuración para "'.$propertyName.'".',
            'image' => '',
            'type' => '2',
            'send_type' => '0',
            'customers_id' => $agentId,
            'propertys_id' => $property->id,
            'role_context' => 'agent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * WhatsApp al agente (reutiliza WhatsAppService si configurado).
     */
    private static function notifyAgentWhatsApp(ClientScreening $screening, Property $property, $agentId): void
    {
        $integration = \App\Models\AgentAdIntegration::where('agent_id', $agentId)->first();

        if (! $integration || ! $integration->whatsapp_sender_number) {
            return;
        }

        $propertyName = $property->title ?? $property->name;

        $message = 'Nuevo screening de cliente: '.$screening->customer_name.' - Score: '.$screening->score.'/100 ('.$screening->nivel.') para "'.$propertyName.'". Revisa tu panel.';

        app(\App\Services\WhatsAppService::class)->sendWithConfig(
            $integration->whatsapp_mode,
            $integration->whatsapp_token,
            $integration->whatsapp_phone_id,
            $integration->whatsapp_sender_number,
            $message
        );
    }
}