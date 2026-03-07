<?php

namespace App\Http\Controllers\Api\CRM;

use App\Models\CrmLead;
use App\Models\CrmLeadScoring;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmLeadController extends Controller
{
    /**
     * Listar leads con filtros
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = CrmLead::query();

        // Scoping: Solo agentes ven sus leads, admin ve todos
        if ($user->role !== 'admin') {
            $query->where('agent_id', $user->id);
        }

        // Filtros
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('origin')) {
            $query->where('origin', $request->origin);
        }

        if ($request->has('score_min')) {
            $query->where('score', '>=', $request->score_min);
        }

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->has('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('email', 'like', "%{$buscar}%")
                  ->orWhere('telefono', 'like', "%{$buscar}%")
                  ->orWhere('whatsapp', 'like', "%{$buscar}%");
            });
        }

        // Ordenamiento
        $ordenar = $request->get('ordenar', 'created_at');
        $direccion = $request->get('direccion', 'desc');
        $query->orderBy($ordenar, $direccion);

        // Paginación
        $leads = $query->with(['agent', 'property', 'scoring'])
            ->paginate($request->get('por_pagina', 15));

        return response()->json([
            'data' => $leads->items(),
            'pagination' => [
                'total' => $leads->total(),
                'per_page' => $leads->perPage(),
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'total_pages' => $leads->lastPage(),
            ]
        ]);
    }

    /**
     * Crear nuevo lead
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'property_id' => 'nullable|exists:properties,id',
            'origin' => 'required|in:meta,whatsapp,formulario,manual,otro',
            'notas' => 'nullable|string',
            'meta_lead_id' => 'nullable|string|unique:crm_leads',
            'meta_campaign_id' => 'nullable|string',
        ]);

        $validated['agent_id'] = Auth::id();
        $validated['status'] = 'nuevo';
        $validated['score'] = 0;

        $lead = CrmLead::create($validated);

        // Calcular score inicial
        CrmLeadScoring::calcularScoreDelLead($lead);

        return response()->json([
            'message' => 'Lead creado exitosamente',
            'data' => $lead->load(['agent', 'property', 'scoring'])
        ], 201);
    }

    /**
     * Obtener detalles de un lead
     */
    public function show($id)
    {
        $lead = CrmLead::findOrFail($id);

        // Autorización: agente ve sus leads, admin ve todos
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => $lead->load([
                'agent',
                'property',
                'interactions' => function ($q) {
                    $q->latest('created_at')->limit(10);
                },
                'campaigns',
                'scoring'
            ])
        ]);
    }

    /**
     * Actualizar lead
     */
    public function update(Request $request, $id)
    {
        $lead = CrmLead::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'property_id' => 'nullable|exists:properties,id',
            'notas' => 'nullable|string',
            'status' => 'sometimes|in:nuevo,contactado,interesado,calificado,ganado,perdido,descartado',
        ]);

        $lead->update($validated);

        // Recalcular score si cambió algo relevante
        CrmLeadScoring::calcularScoreDelLead($lead);

        return response()->json([
            'message' => 'Lead actualizado exitosamente',
            'data' => $lead->load(['agent', 'property', 'scoring'])
        ]);
    }

    /**
     * Cambiar estado de un lead
     */
    public function cambiarStatus(Request $request, $id)
    {
        $lead = CrmLead::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:nuevo,contactado,interesado,calificado,ganado,perdido,descartado',
            'razon' => 'nullable|string',
        ]);

        $lead->cambiarStatus($validated['status'], $validated['razon']);

        return response()->json([
            'message' => "Status cambió a {$validated['status']}",
            'data' => $lead
        ]);
    }

    /**
     * Obtener leads por agente (para admin)
     */
    public function leadsDelAgente($agentId)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $leads = CrmLead::where('agent_id', $agentId)
            ->with(['agent', 'property', 'scoring'])
            ->get();

        return response()->json(['data' => $leads]);
    }

    /**
     * Obtener leads calientes (score alto)
     */
    public function leadsCalientes()
    {
        $user = Auth::user();
        $query = CrmLead::calientes();

        if ($user->role !== 'admin') {
            $query->where('agent_id', $user->id);
        }

        $leads = $query->with(['agent', 'property', 'scoring'])->get();

        return response()->json(['data' => $leads]);
    }

    /**
     * Obtener dashboard stats
     */
    public function dashboard()
    {
        $user = Auth::user();
        $query = CrmLead::query();

        if ($user->role !== 'admin') {
            $query->where('agent_id', $user->id);
        }

        return response()->json([
            'total_leads' => $query->count(),
            'leads_nuevos' => (clone $query)->where('status', 'nuevo')->count(),
            'leads_calientes' => (clone $query)->where('score', '>=', 75)->count(),
            'leads_tiernos' => (clone $query)->whereBetween('score', [40, 74])->count(),
            'leads_frios' => (clone $query)->where('score', '<', 40)->count(),
            'leads_ganados' => (clone $query)->where('status', 'ganado')->count(),
            'leads_perdidos' => (clone $query)->where('status', 'perdido')->count(),
            'tasa_conversion' => $query->where('status', 'ganado')->count() > 0
                ? round(($query->where('status', 'ganado')->count() / $query->count()) * 100, 2)
                : 0,
        ]);
    }

    /**
     * Eliminar lead (soft delete)
     */
    public function destroy($id)
    {
        $lead = CrmLead::findOrFail($id);

        // Autorización
        if (Auth::user()->role !== 'admin' && $lead->agent_id !== Auth::id()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $lead->delete();

        return response()->json([
            'message' => 'Lead eliminado exitosamente'
        ]);
    }
}
