<?php

namespace App\Services;

use App\Models\CrmCampaign;
use App\Models\CrmLead;
use Illuminate\Support\Collection;

class CrmCampaignService
{
    /**
     * Crear campaña Meta
     */
    public static function crearCampañaMeta($data)
    {
        return CrmCampaign::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'agent_id' => $data['agent_id'],
            'platform' => 'meta',
            'status' => 'borrador',
            'presupuesto' => $data['presupuesto'],
            'meta_campaign_id' => $data['meta_campaign_id'] ?? null,
            'meta_adset_id' => $data['meta_adset_id'] ?? null,
            'meta_ad_id' => $data['meta_ad_id'] ?? null,
            'audiencia_target' => $data['audiencia_target'] ?? [],
            'exclusiones' => $data['exclusiones'] ?? [],
            'property_ids' => $data['property_ids'] ?? [],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'] ?? null,
        ]);
    }

    /**
     * Crear campaña WhatsApp
     */
    public static function crearCampañaWhatsApp($data)
    {
        return CrmCampaign::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'agent_id' => $data['agent_id'],
            'platform' => 'whatsapp',
            'status' => 'borrador',
            'presupuesto' => $data['presupuesto'] ?? 0,
            'property_ids' => $data['property_ids'] ?? [],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'] ?? null,
        ]);
    }

    /**
     * Calcular ROI de campaña
     */
    public static function calcularROI(CrmCampaign $campaign)
    {
        if ($campaign->gastado == 0) {
            return null;
        }

        // Asumir ganancia de RD$100,000 por conversión
        $ganancia = ($campaign->conversiones * 100000) - $campaign->gastado;
        return ($ganancia / $campaign->gastado) * 100;
    }

    /**
     * Calcular tasa de conversión
     */
    public static function calcularTasaConversion(CrmCampaign $campaign)
    {
        if ($campaign->leads_generados == 0) {
            return 0;
        }

        return ($campaign->conversiones / $campaign->leads_generados) * 100;
    }

    /**
     * Obtener performance metrics
     */
    public static function obtenerMetricsPerformance(CrmCampaign $campaign)
    {
        return [
            'cpc' => $campaign->cpc,
            'cpl' => $campaign->cpl,
            'ctr' => $campaign->ctr,
            'tasa_conversion' => self::calcularTasaConversion($campaign),
            'roi' => self::calcularROI($campaign),
            'presupuesto_disponible' => $campaign->presupuesto - $campaign->gastado,
            'presupuesto_utilizado_pct' => ($campaign->gastado / $campaign->presupuesto) * 100,
        ];
    }

    /**
     * Comparar rendimiento de campañas
     */
    public static function compararCampañas(array $campaignIds)
    {
        $campaigns = CrmCampaign::whereIn('id', $campaignIds)->get();

        return $campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'nombre' => $campaign->nombre,
                'platform' => $campaign->platform,
                'leads' => $campaign->leads_generados,
                'conversiones' => $campaign->conversiones,
                'tasa_conversion' => self::calcularTasaConversion($campaign),
                'cpl' => $campaign->cpl,
                'roi' => self::calcularROI($campaign),
                'presupuesto' => $campaign->presupuesto,
                'gastado' => $campaign->gastado,
            ];
        });
    }

    /**
     * Obtener mejores campañas por ROI
     */
    public static function mejoresCampañasPorROI($agentId = null, $limit = 5)
    {
        $query = CrmCampaign::where('status', 'completada');

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        $campaigns = $query->get()
            ->map(function ($campaign) {
                $campaign->roi = self::calcularROI($campaign);
                return $campaign;
            })
            ->sortByDesc('roi')
            ->take($limit);

        return $campaigns;
    }

    /**
     * Obtener peores campañas por ROI
     */
    public static function peoresCampañasPorROI($agentId = null, $limit = 5)
    {
        $query = CrmCampaign::where('status', 'completada');

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        $campaigns = $query->get()
            ->map(function ($campaign) {
                $campaign->roi = self::calcularROI($campaign);
                return $campaign;
            })
            ->sortBy('roi')
            ->take($limit);

        return $campaigns;
    }

    /**
     * Obtener estadísticas de campaña por plataforma
     */
    public static function estadisticasPorPlataforma($agentId = null)
    {
        $query = CrmCampaign::class;

        if ($agentId) {
            $query = $query::where('agent_id', $agentId);
        } else {
            $query = $query::query();
        }

        $platforms = ['meta', 'whatsapp', 'email', 'manual'];

        return collect($platforms)->mapWithKeys(function ($platform) use ($query) {
            $campaigns = (clone $query)->where('platform', $platform)->get();

            return [
                $platform => [
                    'total_campañas' => $campaigns->count(),
                    'total_leads' => $campaigns->sum('leads_generados'),
                    'total_conversiones' => $campaigns->sum('conversiones'),
                    'presupuesto_total' => $campaigns->sum('presupuesto'),
                    'gastado_total' => $campaigns->sum('gastado'),
                    'tasa_conversion_promedio' => $campaigns->avg(function ($c) {
                        return self::calcularTasaConversion($c);
                    }),
                ]
            ];
        });
    }

    /**
     * Obtener campañas activas próximas a vencer presupuesto
     */
    public static function campañasProximasAVencerPresupuesto($agentId = null, $porcentajeUmbral = 80)
    {
        $query = CrmCampaign::activas();

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        return $query->get()->filter(function ($campaign) use ($porcentajeUmbral) {
            $porcentajeUtilizado = ($campaign->gastado / $campaign->presupuesto) * 100;
            return $porcentajeUtilizado >= $porcentajeUmbral;
        })->values();
    }

    /**
     * Obtener campañas próximas a fecha de fin
     */
    public static function campañasProximasAFechaFin($agentId = null, $diasAnticipo = 3)
    {
        $fecha = now()->addDays($diasAnticipo);

        $query = CrmCampaign::activas()
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<=', $fecha);

        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        return $query->get();
    }

    /**
     * Generar reporte de campaña
     */
    public static function generarReporteCampaña(CrmCampaign $campaign)
    {
        $leads = $campaign->leads;
        $leadsConvertidos = $campaign->leadsConvertidos();

        return [
            'campaña' => [
                'id' => $campaign->id,
                'nombre' => $campaign->nombre,
                'platform' => $campaign->platform,
                'status' => $campaign->status,
                'fecha_inicio' => $campaign->fecha_inicio,
                'fecha_fin' => $campaign->fecha_fin,
            ],
            'presupuesto' => [
                'total' => $campaign->presupuesto,
                'gastado' => $campaign->gastado,
                'disponible' => $campaign->presupuesto - $campaign->gastado,
                'porcentaje_utilizado' => ($campaign->gastado / $campaign->presupuesto) * 100,
            ],
            'metricas' => [
                'impresiones' => $campaign->impresiones,
                'clics' => $campaign->clics,
                'ctr' => $campaign->ctr . '%',
                'cpc' => 'RD$' . number_format($campaign->cpc, 2),
                'leads' => $campaign->leads_generados,
                'conversiones' => $campaign->conversiones,
                'tasa_conversion' => round(self::calcularTasaConversion($campaign), 2) . '%',
                'cpl' => 'RD$' . number_format($campaign->cpl, 2),
                'roi' => round(self::calcularROI($campaign), 2) . '%',
            ],
            'leads' => [
                'total' => $leads->count(),
                'convertidos' => $leadsConvertidos->count(),
                'respondidos' => $campaign->leadsRespondidos()->count(),
            ],
        ];
    }

    /**
     * Migrar leads de campaña antigua a nueva
     */
    public static function migrarLeadsDeCampaña(CrmCampaign $campañaAntigua, CrmCampaign $campañaNueva)
    {
        $leads = $campañaAntigua->leads;

        foreach ($leads as $lead) {
            $campañaNueva->agregarLead($lead, 'lead');
        }

        return $campañaNueva;
    }
}
