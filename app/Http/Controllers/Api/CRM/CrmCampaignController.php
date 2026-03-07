<?php

namespace App\Http\Controllers\Api\CRM;

use App\Models\CrmCampaign;
use App\Models\CrmLead;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmCampaignController extends Controller
{
    /**
     * Listar campañas
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = CrmCampaign::query();

        // Scoping: Solo agentes ven sus campañas, admin ve todas
        if ($user->role !== 'admin') {
            $query->where('agent_id', $user->id);
        }

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('platform')) {
            $query->where('platform', $request->platform);
        }

        if ($request->has('activas')) {
            if ($request->activas == true) {
                $query->activas();
            }
        }

        // Ordenamiento
        $ordenar = $request->get('ordenar', 'created_at');
        $direccion = $request->get('direccion', 'desc');
        $query->orderBy($ordenar, $direccion);

        // Paginación
        $campaigns = $query->with(['agent'])
            ->paginate($request->get('por_pagina', 15));

        return response()->json([
            'data' => $campaigns->items(),
            'pagination' => [
                'total' => $campaigns->total(),
                'per_page' => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
            ]
        ]);
    }

    /**
     * Crear nueva campaña
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'platform' => 'required|in:meta,whatsapp,email,manual',
            'presupuesto' => 'required|numeric|min:0',
            'property_ids' => 'nullable|array',
            'property_ids.*' => 'exists:properties,id',
            'audiencia_target' => 'nullable|array',
            'exclusiones' => 'nullable|array',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after:fecha_inicio',
        ]);

        $validated['agent_id'] = Auth::id();
        $validated['status'] = 'borrador';
        $validated['gastado'] = 0;
        $validated['impresiones'] = 0;
        $validated['clics'] = 0;
        $validated['leads_generados'] = 0;
        $validated['conversiones'] = 0;

        $campaign = CrmCampaign::create($validated);

        return response()->json([
            'message' => 'Campaña creada exitosamente',
            'data' => $campaign
        ], 201);
    }

    /**
     * Obtener detalles de una campaña
     */
    public function show($id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => $campaign->load('agent'),
            'resumen' => $campaign->obtenerResumen()
        ]);
    }

    /**
     * Actualizar campaña
     */
    public function update(Request $request, $id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'status' => 'sometimes|in:borrador,activa,pausada,completada,cancelada',
            'presupuesto' => 'sometimes|numeric|min:0',
            'property_ids' => 'nullable|array',
            'property_ids.*' => 'exists:properties,id',
            'audiencia_target' => 'nullable|array',
            'exclusiones' => 'nullable|array',
            'fecha_fin' => 'nullable|date',
        ]);

        $campaign->update($validated);

        return response()->json([
            'message' => 'Campaña actualizada exitosamente',
            'data' => $campaign
        ]);
    }

    /**
     * Actualizar métricas de la campaña
     */
    public function actualizarMetricas(Request $request, $id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'impresiones' => 'sometimes|integer|min:0',
            'clics' => 'sometimes|integer|min:0',
            'leads_generados' => 'sometimes|integer|min:0',
            'conversiones' => 'sometimes|integer|min:0',
            'gastado' => 'sometimes|numeric|min:0',
        ]);

        // Calcular CPC y CPL
        if (isset($validated['gastado']) && isset($validated['clics']) && $validated['clics'] > 0) {
            $validated['cpc'] = $validated['gastado'] / $validated['clics'];
        }

        if (isset($validated['gastado']) && isset($validated['leads_generados']) && $validated['leads_generados'] > 0) {
            $validated['cpl'] = $validated['gastado'] / $validated['leads_generados'];
        }

        // Calcular CTR
        if (isset($validated['impresiones']) && isset($validated['clics']) && $validated['impresiones'] > 0) {
            $validated['ctr'] = ($validated['clics'] / $validated['impresiones']) * 100;
        }

        $campaign->update($validated);

        return response()->json([
            'message' => 'Métricas actualizadas exitosamente',
            'data' => $campaign,
            'resumen' => $campaign->obtenerResumen()
        ]);
    }

    /**
     * Agregar leads a la campaña
     */
    public function agregarLeads(Request $request, $id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'exists:crm_leads,id',
        ]);

        $leadsAgregados = 0;
        foreach ($validated['lead_ids'] as $leadId) {
            $lead = CrmLead::findOrFail($leadId);

            // Solo agregar si pertenece al mismo agente o es admin
            if (Auth::user()->role === 'admin' || $lead->agent_id === Auth::id()) {
                $campaign->agregarLead($lead, 'lead');
                $leadsAgregados++;
            }
        }

        return response()->json([
            'message' => "{$leadsAgregados} leads agregados a la campaña",
            'leads_agregados' => $leadsAgregados
        ]);
    }

    /**
     * Obtener leads de una campaña
     */
    public function leadsDelaCampaña($id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $leads = $campaign->leads()
            ->with(['agent', 'property', 'scoring'])
            ->paginate(20);

        return response()->json([
            'data' => $leads->items(),
            'pagination' => [
                'total' => $leads->total(),
                'per_page' => $leads->perPage(),
            ]
        ]);
    }

    /**
     * Obtener leads convertidos de una campaña
     */
    public function leadsConvertidos($id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $leads = $campaign->leadsConvertidos();

        return response()->json([
            'data' => $leads,
            'total' => count($leads)
        ]);
    }

    /**
     * Cambiar estado de la campaña
     */
    public function cambiarStatus(Request $request, $id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:borrador,activa,pausada,completada,cancelada',
        ]);

        $campaign->update($validated);

        return response()->json([
            'message' => "Campaña {$validated['status']}",
            'data' => $campaign
        ]);
    }

    /**
     * Eliminar campaña
     */
    public function destroy($id)
    {
        $campaign = CrmCampaign::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $campaign->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $campaign->delete();

        return response()->json([
            'message' => 'Campaña eliminada exitosamente'
        ]);
    }

    /**
     * Dashboard de campañas
     */
    public function dashboard()
    {
        $user = Auth::user();
        $query = CrmCampaign::query();

        if ($user->role !== 'admin') {
            $query->where('agent_id', $user->id);
        }

        $activas = (clone $query)->activas()->count();
        $leads = (clone $query)->sum('leads_generados');
        $conversiones = (clone $query)->sum('conversiones');
        $gastado = (clone $query)->sum('gastado');

        return response()->json([
            'total_campañas' => $query->count(),
            'campañas_activas' => $activas,
            'total_leads' => $leads,
            'total_conversiones' => $conversiones,
            'presupuesto_gastado' => $gastado,
            'roi_promedio' => $query->avg('cpl'),
        ]);
    }
}
