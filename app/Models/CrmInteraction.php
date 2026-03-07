<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmInteraction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lead_id',
        'agent_id',
        'type',
        'contenido',
        'resultado',
        'duracion_segundos',
        'status_anterior',
        'status_nuevo',
        'proxima_accion',
        'fecha_proxima_accion',
        'external_id',
    ];

    protected $casts = [
        'fecha_proxima_accion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELACIONES ==========

    /**
     * El lead asociado
     */
    public function lead()
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    /**
     * El agente que realizó la interacción
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // ========== SCOPES ==========

    /**
     * Interacciones de un lead específico
     */
    public function scopeDelLead($query, $leadId)
    {
        return $query->where('lead_id', $leadId);
    }

    /**
     * Interacciones de un agente específico
     */
    public function scopeDelAgente($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Interacciones de un tipo específico
     */
    public function scopeDelTipo($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Interacciones recientes
     */
    public function scopeRecientes($query, $dias = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    /**
     * Interacciones con próximas acciones pendientes
     */
    public function scopeConProximasAcciones($query)
    {
        return $query->whereNotNull('fecha_proxima_accion')
            ->where('fecha_proxima_accion', '<=', now());
    }

    // ========== MÉTODOS ==========

    /**
     * Obtener duración formateada
     */
    public function obtenerDuracionFormateada()
    {
        if (!$this->duracion_segundos) {
            return null;
        }

        $minutos = intdiv($this->duracion_segundos, 60);
        $segundos = $this->duracion_segundos % 60;

        return "{$minutos}m {$segundos}s";
    }

    /**
     * Obtener descripción legible del tipo
     */
    public function obtenerTipoDescripcion()
    {
        $tipos = [
            'llamada' => 'Llamada',
            'mensaje_texto' => 'SMS',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'visita_personal' => 'Visita Personal',
            'video_llamada' => 'Video Llamada',
            'nota_interna' => 'Nota Interna',
        ];

        return $tipos[$this->type] ?? $this->type;
    }

    /**
     * Obtener cambio de status
     */
    public function obtenerCambioStatus()
    {
        if (!$this->status_anterior || !$this->status_nuevo) {
            return null;
        }

        return "{$this->status_anterior} → {$this->status_nuevo}";
    }

    /**
     * Marcar como completada
     */
    public function marcarCompleta()
    {
        $this->update([
            'fecha_proxima_accion' => null,
        ]);

        return $this;
    }
}
