<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetaNotification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'agent_id',
        'lead_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $hidden = ['deleted_at'];

    // Enums for notification types
    const TYPE_NEW_LEAD = 'new_lead';
    const TYPE_MESSAGE = 'message_received';
    const TYPE_CAMPAIGN = 'campaign_response';
    const TYPE_APPOINTMENT = 'appointment_request';
    const TYPE_LEAD_UPDATE = 'lead_update';

    public static $types = [
        self::TYPE_NEW_LEAD,
        self::TYPE_MESSAGE,
        self::TYPE_CAMPAIGN,
        self::TYPE_APPOINTMENT,
        self::TYPE_LEAD_UPDATE,
    ];

    /**
     * Relación con el agente (User)
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * Relación con el lead
     */
    public function lead()
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    /**
     * Scope para notificaciones no leídas
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope para notificaciones del agente
     */
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope por tipo
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Marcar como leída
     */
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
        return $this;
    }

    /**
     * Crear notificación
     */
    public static function createNotification(
        int $agentId,
        ?int $leadId,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ) {
        return self::create([
            'agent_id' => $agentId,
            'lead_id' => $leadId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);
    }
}
