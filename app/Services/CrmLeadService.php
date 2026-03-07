<?php

namespace App\Services;

use App\Models\CrmLead;
use App\Models\CrmLeadScoring;
use App\Models\CrmInteraction;
use App\Models\CrmCampaign;
use Illuminate\Support\Collection;

class CrmLeadService
{
    /**
     * Crear lead desde múltiples fuentes
     */
    public static function crearLeadDesdeOrigen($data)
    {
        $lead = CrmLead::create([
            'nombre' => $data['nombre'],
            'email' => $data['email'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'property_id' => $data['property_id'] ?? null,
            'agent_id' => $data['agent_id'] ?? auth()->id(),
            'origin' => $data['origin'],
            'status' => 'nuevo',
            'score' => 0,
            'notas' => $data['notas'] ?? null,
            'meta_lead_id' => $data['meta_lead_id'] ?? null,
            'meta_campaign_id' => $data['meta_campaign_id'] ?? null,
            'whatsapp_number_id' => $data['whatsapp_number_id'] ?? null,
        ]);

        // Calcular score inicial
        CrmLeadScoring::calcularScoreDelLead($lead);

        // Registrar interacción inicial
        $lead->registrarInteraccion(
            'nota_interna',
            "Lead creado desde {$data['origin']}"
        );

        return $lead;
    }

    /**
     * Calificar lead (cambiar status a calificado)
     */
    public static function calificarLead(CrmLead $lead, $razon = null)
    {
        $lead->cambiarStatus('calificado', $razon ?? 'Lead calificado por agente');
        CrmLeadScoring::calcularScoreDelLead($lead);

        return $lead;
    }

    /**
     * Descartar lead (cambiar status a descartado)
     */
    public static function descartarLead(CrmLead $lead, $razon = null)
    {
        $lead->cambiarStatus('descartado', $razon ?? 'Lead descartado');
        CrmLeadScoring::calcularScoreDelLead($lead);

        return $lead;
    }

    /**
     * Marcar lead como ganado
     */
    public static function marcarComoGanado(CrmLead $lead, $monto = null, $razon = null)
    {
        $lead->cambiarStatus('ganado', $razon ?? "Lead ganado. Monto: RD\${$monto}");

        $lead->update([
            'metadata' => array_merge($lead->metadata ?? [], [
                'monto_venta' => $monto,
                'fecha_venta' => now(),
            ])
        ]);

        CrmLeadScoring::calcularScoreDelLead($lead);

        return $lead;
    }

    /**
     * Obtener leads que necesitan seguimiento inmediato
     */
    public static function leadsParaSeguimiento($agentId = null)
    {
        $query = CrmLead::activos()
            ->where('score', '>=', 75)
            ->whereNull('fecha_ultima_interaccion')
            ->orWhere(function ($q) {
                $q->where('fecha_ultima_interaccion', '<', now()->subDays(2));
            });

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        return $query->orderBy('score', 'desc')->get();
    }

    /**
     * Obtener leads por prioridad (escala caliente -> frío)
     */
    public static function leadsPorPrioridad($agentId = null)
    {
        $muy_calientes = CrmLead::calientes()
            ->where('score', '>=', 85)
            ->when($agentId, fn($q) => $q->where('agent_id', $agentId))
            ->get();

        $calientes = CrmLead::calientes()
            ->whereBetween('score', [70, 84])
            ->when($agentId, fn($q) => $q->where('agent_id', $agentId))
            ->get();

        $tibios = CrmLead::tibios()
            ->whereBetween('score', [40, 69])
            ->when($agentId, fn($q) => $q->where('agent_id', $agentId))
            ->get();

        $frios = CrmLead::frios()
            ->where('score', '<', 40)
            ->when($agentId, fn($q) => $q->where('agent_id', $agentId))
            ->get();

        return [
            'muy_calientes' => $muy_calientes,
            'calientes' => $calientes,
            'tibios' => $tibios,
            'frios' => $frios,
        ];
    }

    /**
     * Obtener leads sin interacción en N días
     */
    public static function leadsSinInteraccionEnDias($dias = 3, $agentId = null)
    {
        $fecha = now()->subDays($dias);

        $query = CrmLead::where(function ($q) use ($fecha) {
            $q->whereNull('fecha_ultima_interaccion')
              ->orWhere('fecha_ultima_interaccion', '<', $fecha);
        })->activos();

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        return $query->orderBy('fecha_ultima_interaccion', 'asc')->get();
    }

    /**
     * Registrar interacción y actualizar lead
     */
    public static function registrarInteraccion(
        CrmLead $lead,
        $tipo,
        $contenido,
        $resultado = null,
        array $datos = []
    ) {
        $interaction = $lead->registrarInteraccion($tipo, $contenido, $resultado, $datos);

        // Recalcular score
        CrmLeadScoring::calcularScoreDelLead($lead);

        return $interaction;
    }

    /**
     * Obtener resumen de lead con análisis completo
     */
    public static function obtenerResumenCompleto(CrmLead $lead)
    {
        $scoring = $lead->scoring;
        $interacciones = $lead->interactions()->count();
        $ultimaInteraccion = $lead->ultimaInteraccion();
        $proximaAccion = $lead->obtenerProximaAccion();

        return [
            'lead' => [
                'id' => $lead->id,
                'nombre' => $lead->nombre,
                'email' => $lead->email,
                'telefono' => $lead->telefono,
                'whatsapp' => $lead->whatsapp,
                'status' => $lead->status,
                'origin' => $lead->origin,
            ],
            'scoring' => [
                'score' => $scoring?->score_total ?? 0,
                'recomendacion' => $scoring?->recomendacion,
                'descripcion' => $scoring?->obtenerDescripcionRecomendacion(),
                'desglose' => $scoring?->obtenerDesglose(),
            ],
            'actividad' => [
                'interacciones_totales' => $interacciones,
                'ultima_interaccion' => $ultimaInteraccion?->created_at,
                'dias_desde_contacto' => $lead->diasDesdeContacto(),
                'proxima_accion' => $proximaAccion,
            ],
            'campañas' => $lead->campaigns()->count(),
            'propiedad' => $lead->property?->title,
        ];
    }

    /**
     * Migrar lead a propiedad
     */
    public static function migrarAPropiedad(CrmLead $lead, $propertyId)
    {
        $lead->update(['property_id' => $propertyId]);

        $lead->registrarInteraccion(
            'nota_interna',
            "Lead migrado a propiedad ID: {$propertyId}"
        );

        return $lead;
    }

    /**
     * Obtener estadísticas del agente
     */
    public static function estadisticasAgente($agentId)
    {
        $leads = CrmLead::where('agent_id', $agentId);

        return [
            'total_leads' => $leads->count(),
            'leads_nuevos' => (clone $leads)->where('status', 'nuevo')->count(),
            'leads_en_seguimiento' => (clone $leads)->whereIn('status', ['contactado', 'interesado'])->count(),
            'leads_calificados' => (clone $leads)->where('status', 'calificado')->count(),
            'leads_ganados' => (clone $leads)->where('status', 'ganado')->count(),
            'leads_perdidos' => (clone $leads)->where('status', 'perdido')->count(),
            'tasa_conversion' => $leads->where('status', 'ganado')->count() > 0
                ? round(($leads->where('status', 'ganado')->count() / $leads->count()) * 100, 2)
                : 0,
            'score_promedio' => round($leads->avg('score'), 2),
            'leads_muy_calientes' => (clone $leads)->where('score', '>=', 85)->count(),
            'leads_calientes' => (clone $leads)->whereBetween('score', [70, 84])->count(),
            'leads_tibios' => (clone $leads)->whereBetween('score', [40, 69])->count(),
            'leads_frios' => (clone $leads)->where('score', '<', 40)->count(),
        ];
    }

    /**
     * Generar reporte de leads
     */
    public static function generarReporte($agentId = null, $fechaInicio = null, $fechaFin = null)
    {
        $query = CrmLead::query();

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
        }

        $leads = $query->get();

        return [
            'total_leads' => $leads->count(),
            'leads_por_origen' => $leads->groupBy('origin')->map->count(),
            'leads_por_status' => $leads->groupBy('status')->map->count(),
            'score_promedio' => round($leads->avg('score'), 2),
            'tasa_conversion' => $leads->where('status', 'ganado')->count() > 0
                ? round(($leads->where('status', 'ganado')->count() / $leads->count()) * 100, 2)
                : 0,
            'interacciones_totales' => CrmInteraction::whereIn('lead_id', $leads->pluck('id'))->count(),
            'leads_sin_seguimiento' => $leads->where('fecha_ultima_interaccion', null)->count(),
        ];
    }

    /**
     * Duplicar lead (para detectar posibles duplicados)
     */
    public static function detectarDuplicados(CrmLead $lead)
    {
        $query = CrmLead::where('id', '!=', $lead->id)
            ->where('agent_id', $lead->agent_id);

        $duplicados = [];

        // Por email
        if ($lead->email) {
            $duplicados[] = (clone $query)->where('email', $lead->email)->first();
        }

        // Por teléfono
        if ($lead->telefono) {
            $duplicados[] = (clone $query)->where('telefono', $lead->telefono)->first();
        }

        // Por whatsapp
        if ($lead->whatsapp) {
            $duplicados[] = (clone $query)->where('whatsapp', $lead->whatsapp)->first();
        }

        return collect($duplicados)->filter()->unique('id')->values();
    }

    /**
     * Fusionar leads duplicados
     */
    public static function fusionarLeads(CrmLead $leadPrincipal, CrmLead $leadSecundario)
    {
        // Transferir interacciones
        $leadSecundario->interactions()
            ->update(['lead_id' => $leadPrincipal->id]);

        // Transferir campañas
        $leadSecundario->campaigns()->sync($leadPrincipal->campaigns()->pluck('id'));

        // Registrar la fusión
        $leadPrincipal->registrarInteraccion(
            'nota_interna',
            "Lead fusionado con ID: {$leadSecundario->id}"
        );

        // Eliminar lead secundario
        $leadSecundario->delete();

        return $leadPrincipal;
    }
}
