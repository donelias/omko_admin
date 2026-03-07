<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmLead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'whatsapp',
        'property_id',
        'agent_id',
        'status',
        'origin',
        'meta_lead_id',
        'meta_campaign_id',
        'whatsapp_number_id',
        'notas',
        'metadata',
        'score',
        'fecha_primer_contacto',
        'fecha_ultima_interaccion',
    ];

    protected $casts = [
        'metadata' => 'array',
        'fecha_primer_contacto' => 'datetime',
        'fecha_ultima_interaccion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELACIONES ==========

    /**
     * El agente que gestiona este lead
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * La propiedad asociada al lead (si existe)
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Interacciones con este lead
     */
    public function interactions()
    {
        return $this->hasMany(CrmInteraction::class, 'lead_id');
    }

    /**
     * Campañas en las que participa este lead
     */
    public function campaigns()
    {
        return $this->belongsToMany(CrmCampaign::class, 'crm_campaign_leads')
            ->withPivot('conversion_status', 'respondio', 'interesado', 'compro')
            ->withTimestamps();
    }

    /**
     * Scoring del lead
     */
    public function scoring()
    {
        return $this->hasOne(CrmLeadScoring::class, 'lead_id');
    }

    // ========== SCOPES ==========

    /**
     * Leads activos
     */
    public function scopeActivos($query)
    {
        return $query->whereNotIn('status', ['ganado', 'perdido', 'descartado']);
    }

    /**
     * Leads de un agente específico
     */
    public function scopeDelAgente($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Leads de un estado específico
     */
    public function scopeConStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Leads calientes (score alto)
     */
    public function scopeCalientes($query)
    {
        return $query->where('score', '>=', 75);
    }

    /**
     * Leads tiernos (score medio)
     */
    public function scopeTiernos($query)
    {
        return $query->whereBetween('score', [40, 74]);
    }

    /**
     * Leads fríos (score bajo)
     */
    public function scopeFrios($query)
    {
        return $query->where('score', '<', 40);
    }

    /**
     * Leads de una propiedad específica
     */
    public function scopeDelPropiedad($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Leads que necesitan seguimiento (próxima acción vencida)
     */
    public function scopeNecesitanSeguimiento($query)
    {
        return $query->whereHas('interactions', function ($q) {
            $q->where('fecha_proxima_accion', '<=', now())
              ->whereNull('completado');
        });
    }

    // ========== MÉTODOS ==========

    /**
     * Obtener la última interacción
     */
    public function ultimaInteraccion()
    {
        return $this->interactions()
            ->latest('created_at')
            ->first();
    }

    /**
     * Registrar nueva interacción
     */
    public function registrarInteraccion($type, $contenido, $resultado = null, array $datos = [])
    {
        $statusAnterior = $this->status;

        $interaction = $this->interactions()->create([
            'agent_id' => auth()->id(),
            'type' => $type,
            'contenido' => $contenido,
            'resultado' => $resultado,
            'status_anterior' => $statusAnterior,
            'status_nuevo' => $datos['status_nuevo'] ?? null,
            'duracion_segundos' => $datos['duracion_segundos'] ?? null,
            'proxima_accion' => $datos['proxima_accion'] ?? null,
            'fecha_proxima_accion' => $datos['fecha_proxima_accion'] ?? null,
        ]);

        // Actualizar fecha de última interacción
        $this->update([
            'fecha_ultima_interaccion' => now(),
            'status' => $datos['status_nuevo'] ?? $statusAnterior,
        ]);

        // Marcar primer contacto si es la primera interacción
        if (!$this->fecha_primer_contacto) {
            $this->update(['fecha_primer_contacto' => now()]);
        }

        return $interaction;
    }

    /**
     * Cambiar estado del lead
     */
    public function cambiarStatus($nuevoStatus, $razon = null)
    {
        $this->registrarInteraccion('nota_interna', $razon ?? "Estado cambiado a {$nuevoStatus}", null, [
            'status_nuevo' => $nuevoStatus,
        ]);

        return $this;
    }

    /**
     * Obtener próxima acción pendiente
     */
    public function obtenerProximaAccion()
    {
        return $this->interactions()
            ->whereNotNull('fecha_proxima_accion')
            ->where('fecha_proxima_accion', '>', now())
            ->orderBy('fecha_proxima_accion', 'asc')
            ->first();
    }

    /**
     * Calcular días desde primer contacto
     */
    public function diasDesdeContacto()
    {
        if (!$this->fecha_primer_contacto) {
            return null;
        }

        return $this->fecha_primer_contacto->diffInDays(now());
    }

    /**
     * Obtener resumen del lead
     */
    public function obtenerResumen()
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'status' => $this->status,
            'score' => $this->score,
            'agent' => $this->agent->name,
            'property' => $this->property?->title,
            'dias_contacto' => $this->diasDesdeContacto(),
            'ultima_interaccion' => $this->ultimaInteraccion()?->created_at,
            'proxima_accion' => $this->obtenerProximaAccion(),
        ];
    }
}
