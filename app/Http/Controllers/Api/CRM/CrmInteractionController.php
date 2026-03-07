<?php

namespace App\Http\Controllers\Api\CRM;

use App\Models\CrmInteraction;
use App\Models\CrmLead;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmInteractionController extends Controller
{
    /**
     * Listar interacciones de un lead
     */
    public function index($leadId)
    {
        $lead = CrmLead::findOrFail($leadId);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $interactions = $lead->interactions()
            ->with(['agent'])
            ->latest('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $interactions->items(),
            'pagination' => [
                'total' => $interactions->total(),
                'per_page' => $interactions->perPage(),
            ]
        ]);
    }

    /**
     * Crear nueva interacción
     */
    public function store(Request $request, $leadId)
    {
        $lead = CrmLead::findOrFail($leadId);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:llamada,mensaje_texto,email,whatsapp,facebook,instagram,visita_personal,video_llamada,nota_interna',
            'contenido' => 'required|string',
            'resultado' => 'nullable|string',
            'duracion_segundos' => 'nullable|integer|min:0',
            'status_nuevo' => 'nullable|in:nuevo,contactado,interesado,calificado,ganado,perdido,descartado',
            'proxima_accion' => 'nullable|string',
            'fecha_proxima_accion' => 'nullable|date',
        ]);

        $interaction = $lead->registrarInteraccion(
            $validated['type'],
            $validated['contenido'],
            $validated['resultado'],
            $validated
        );

        return response()->json([
            'message' => 'Interacción registrada exitosamente',
            'data' => $interaction
        ], 201);
    }

    /**
     * Obtener detalles de una interacción
     */
    public function show($leadId, $interactionId)
    {
        $lead = CrmLead::findOrFail($leadId);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $interaction = CrmInteraction::where('lead_id', $leadId)
            ->findOrFail($interactionId);

        return response()->json([
            'data' => $interaction->load(['agent'])
        ]);
    }

    /**
     * Actualizar interacción
     */
    public function update(Request $request, $leadId, $interactionId)
    {
        $lead = CrmLead::findOrFail($leadId);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $interaction = CrmInteraction::where('lead_id', $leadId)
            ->findOrFail($interactionId);

        $validated = $request->validate([
            'contenido' => 'sometimes|string',
            'resultado' => 'nullable|string',
            'proxima_accion' => 'nullable|string',
            'fecha_proxima_accion' => 'nullable|date',
        ]);

        $interaction->update($validated);

        return response()->json([
            'message' => 'Interacción actualizada exitosamente',
            'data' => $interaction
        ]);
    }

    /**
     * Obtener todas las interacciones de un agente
     */
    public function interaccionesDelAgente()
    {
        $user = Auth::user();

        $interactions = CrmInteraction::where('agent_id', $user->id)
            ->with(['lead', 'agent'])
            ->latest('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $interactions->items(),
            'pagination' => [
                'total' => $interactions->total(),
                'per_page' => $interactions->perPage(),
            ]
        ]);
    }

    /**
     * Obtener próximas acciones pendientes
     */
    public function proximasAcciones()
    {
        $user = Auth::user();
        $query = CrmInteraction::query();

        // Solo si es admin o sus propias interacciones
        if ($user->role !== 'admin') {
            $query->where('agent_id', $user->id);
        }

        $acciones = $query->conProximasAcciones()
            ->with(['lead', 'agent'])
            ->orderBy('fecha_proxima_accion', 'asc')
            ->get();

        return response()->json([
            'data' => $acciones,
            'total' => count($acciones)
        ]);
    }

    /**
     * Marcar próxima acción como completada
     */
    public function marcarCompletada($leadId, $interactionId)
    {
        $lead = CrmLead::findOrFail($leadId);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $interaction = CrmInteraction::where('lead_id', $leadId)
            ->findOrFail($interactionId);

        $interaction->marcarCompleta();

        return response()->json([
            'message' => 'Próxima acción marcada como completada',
            'data' => $interaction
        ]);
    }

    /**
     * Eliminar interacción
     */
    public function destroy($leadId, $interactionId)
    {
        $lead = CrmLead::findOrFail($leadId);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $interaction = CrmInteraction::where('lead_id', $leadId)
            ->findOrFail($interactionId);

        $interaction->delete();

        return response()->json([
            'message' => 'Interacción eliminada exitosamente'
        ]);
    }

    /**
     * Obtener resumen de interacciones por tipo
     */
    public function resumenPorTipo($leadId)
    {
        $lead = CrmLead::findOrFail($leadId);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $tipos = ['llamada', 'mensaje_texto', 'email', 'whatsapp', 'facebook', 'instagram', 'visita_personal', 'video_llamada', 'nota_interna'];

        $resumen = [];
        foreach ($tipos as $tipo) {
            $count = $lead->interactions()->where('type', $tipo)->count();
            if ($count > 0) {
                $resumen[$tipo] = $count;
            }
        }

        return response()->json(['data' => $resumen]);
    }
}
