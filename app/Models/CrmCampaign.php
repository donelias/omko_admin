<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'descripcion',
        'agent_id',
        'platform',
        'status',
        'meta_campaign_id',
        'meta_adset_id',
        'meta_ad_id',
        'presupuesto',
        'gastado',
        'impresiones',
        'clics',
        'leads_generados',
        'conversiones',
        'cpc',
        'cpl',
        'ctr',
        'audiencia_target',
        'exclusiones',
        'property_ids',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'presupuesto' => 'decimal:2',
        'gastado' => 'decimal:2',
        'cpc' => 'decimal:2',
        'cpl' => 'decimal:2',
        'ctr' => 'decimal:2',
        'audiencia_target' => 'array',
        'exclusiones' => 'array',
        'property_ids' => 'array',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELACIONES ==========

    /**
     * El agente propietario de la campaña
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Leads de esta campaña
     */
    public function leads()
    {
        return $this->belongsToMany(CrmLead::class, 'crm_campaign_leads')
            ->withPivot('conversion_status', 'respondio', 'interesado', 'compro')
            ->withTimestamps();
    }

    /**
     * Propiedades asociadas
     */
    public function properties()
    {
        $ids = $this->property_ids ?? [];
        return Property::whereIn('id', $ids)->get();
    }

    // ========== SCOPES ==========

    /**
     * Campañas activas
     */
    public function scopeActivas($query)
    {
        return $query->where('status', 'activa');
    }

    /**
     * Campañas de un agente específico
     */
    public function scopeDelAgente($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Campañas de una plataforma específica
     */
    public function scopeDelPlataforma($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Campañas en progreso (fechas activas)
     */
    public function scopeEnProgreso($query)
    {
        return $query->where('status', 'activa')
            ->where('fecha_inicio', '<=', now())
            ->where(function ($q) {
                $q->whereNull('fecha_fin')
                  ->orWhere('fecha_fin', '>=', now());
            });
    }

    /**
     * Campañas completadas
     */
    public function scopeCompletadas($query)
    {
        return $query->whereIn('status', ['completada', 'cancelada']);
    }

    // ========== MÉTODOS ==========

    /**
     * Obtener ROI de la campaña
     */
    public function obtenerROI()
    {
        if ($this->gastado == 0) {
            return null;
        }

        $ganancia = ($this->conversiones * 100000) - $this->gastado; // Asumiendo RD$100k por conversión
        return ($ganancia / $this->gastado) * 100;
    }

    /**
     * Obtener tasa de conversión
     */
    public function obtenerTasaConversion()
    {
        if ($this->leads_generados == 0) {
            return 0;
        }

        return ($this->conversiones / $this->leads_generados) * 100;
    }

    /**
     * Actualizar métricas desde Meta (si existe meta_campaign_id)
     */
    public function actualizarMetricasDesMeta()
    {
        if (!$this->meta_campaign_id) {
            return false;
        }

        // Aquí irá la lógica de conexión con Meta API
        // Por ahora solo es un placeholder
        return true;
    }

    /**
     * Agregar lead a la campaña
     */
    public function agregarLead(CrmLead $lead, $statusConversion = 'lead')
    {
        return $this->leads()->attach($lead->id, [
            'conversion_status' => $statusConversion,
            'fecha_lead' => now(),
        ]);
    }

    /**
     * Obtener leads respondidos
     */
    public function leadsRespondidos()
    {
        return $this->leads()
            ->wherePivot('respondio', true)
            ->get();
    }

    /**
     * Obtener leads convertidos
     */
    public function leadsConvertidos()
    {
        return $this->leads()
            ->wherePivot('compro', true)
            ->get();
    }

    /**
     * Obtener resumen de la campaña
     */
    public function obtenerResumen()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'platform' => $this->platform,
            'status' => $this->status,
            'presupuesto' => $this->presupuesto,
            'gastado' => $this->gastado,
            'presupuesto_disponible' => $this->presupuesto - $this->gastado,
            'impresiones' => $this->impresiones,
            'clics' => $this->clics,
            'ctr' => $this->ctr . '%',
            'leads' => $this->leads_generados,
            'conversiones' => $this->conversiones,
            'tasa_conversion' => round($this->obtenerTasaConversion(), 2) . '%',
            'roi' => round($this->obtenerROI(), 2) . '%',
            'cpl' => $this->cpl ? 'RD$' . number_format($this->cpl, 2) : 'N/A',
        ];
    }
}
