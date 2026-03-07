<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmLeadScoring extends Model
{
    use HasFactory;

    protected $table = 'crm_lead_scoring';

    protected $fillable = [
        'lead_id',
        'score_total',
        'score_engagement',
        'score_interes',
        'score_tiempo',
        'score_comportamiento',
        'score_capacidad',
        'factores',
        'recomendacion',
        'ultima_actualizacion',
    ];

    protected $casts = [
        'factores' => 'array',
        'ultima_actualizacion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELACIONES ==========

    /**
     * El lead asociado
     */
    public function lead()
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    // ========== SCOPES ==========

    /**
     * Leads muy calientes
     */
    public function scopeMuyCalientes($query)
    {
        return $query->where('recomendacion', 'muy_caliente');
    }

    /**
     * Leads calientes
     */
    public function scopeCalientes($query)
    {
        return $query->where('recomendacion', 'caliente');
    }

    /**
     * Leads tibios
     */
    public function scopeTibios($query)
    {
        return $query->where('recomendacion', 'tibio');
    }

    /**
     * Leads fríos
     */
    public function scopeFrios($query)
    {
        return $query->where('recomendacion', 'frio');
    }

    /**
     * Ordenados por score
     */
    public function scopeOrdenadosPorScore($query)
    {
        return $query->orderBy('score_total', 'desc');
    }

    // ========== MÉTODOS ==========

    /**
     * Calcular score basado en interacciones y comportamiento
     */
    public static function calcularScoreDelLead(CrmLead $lead)
    {
        $scoring = $lead->scoring ?? new self();

        // Score de engagement (interacciones)
        $countInteracciones = $lead->interactions()->count();
        $scoreEngagement = min(100, $countInteracciones * 10);

        // Score de interés (status y cambios)
        $scoreInteres = match ($lead->status) {
            'calificado' => 100,
            'interesado' => 75,
            'contactado' => 50,
            'nuevo' => 25,
            'ganado' => 100,
            'perdido' => 0,
            'descartado' => 0,
            default => 20,
        };

        // Score de tiempo (recencia de contacto)
        $diasDesdeContacto = $lead->diasDesdeContacto() ?? 999;
        $scoreTiempo = $diasDesdeContacto <= 3 ? 100 : 
                        ($diasDesdeContacto <= 7 ? 75 : 
                        ($diasDesdeContacto <= 30 ? 50 : 25));

        // Score de comportamiento (respuestas rápidas)
        $ultimaInteraccion = $lead->ultimaInteraccion();
        $scoreComportamiento = $ultimaInteraccion ? 50 : 0;

        // Score de capacidad (está buscando property específica)
        $scoreCapacidad = $lead->property_id ? 75 : 25;

        // Calcular score total (promedio ponderado)
        $scoreTotal = (
            $scoreEngagement * 0.25 +
            $scoreInteres * 0.35 +
            $scoreTiempo * 0.20 +
            $scoreComportamiento * 0.10 +
            $scoreCapacidad * 0.10
        );

        // Determinar recomendación
        $recomendacion = match (true) {
            $scoreTotal >= 85 => 'muy_caliente',
            $scoreTotal >= 70 => 'caliente',
            $scoreTotal >= 50 => 'tibio',
            $scoreTotal >= 25 => 'frio',
            default => 'descartado',
        };

        // Actualizar scoring
        $scoring->fill([
            'lead_id' => $lead->id,
            'score_total' => round($scoreTotal, 2),
            'score_engagement' => $scoreEngagement,
            'score_interes' => $scoreInteres,
            'score_tiempo' => $scoreTiempo,
            'score_comportamiento' => $scoreComportamiento,
            'score_capacidad' => $scoreCapacidad,
            'recomendacion' => $recomendacion,
            'ultima_actualizacion' => now(),
            'factores' => [
                'engagement' => $scoreEngagement,
                'interes' => $scoreInteres,
                'tiempo' => $scoreTiempo,
                'comportamiento' => $scoreComportamiento,
                'capacidad' => $scoreCapacidad,
            ],
        ])->save();

        // Actualizar score en el lead
        $lead->update(['score' => round($scoreTotal, 2)]);

        return $scoring;
    }

    /**
     * Obtener descripción de recomendación
     */
    public function obtenerDescripcionRecomendacion()
    {
        return match ($this->recomendacion) {
            'muy_caliente' => '🔴 Muy caliente - Contactar inmediatamente',
            'caliente' => '🟠 Caliente - Prioridad alta',
            'tibio' => '🟡 Tibio - Seguimiento regular',
            'frio' => '🔵 Frío - Seguimiento esporádico',
            'descartado' => '⚫ Descartado - No contactar',
            default => 'Desconocido',
        };
    }

    /**
     * Obtener desglose del score
     */
    public function obtenerDesglose()
    {
        return [
            'total' => $this->score_total,
            'engagement' => $this->score_engagement,
            'interes' => $this->score_interes,
            'tiempo' => $this->score_tiempo,
            'comportamiento' => $this->score_comportamiento,
            'capacidad' => $this->score_capacidad,
        ];
    }
}
