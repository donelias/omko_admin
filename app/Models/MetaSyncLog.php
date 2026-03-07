<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaSyncLog extends Model
{
    use HasFactory;

    protected $table = 'meta_sync_logs';

    protected $fillable = [
        'agent_id',
        'tipo',
        'datos',
    ];

    protected $casts = [
        'datos' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELACIONES ==========

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // ========== SCOPES ==========

    public function scopeDelAgente($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeDelTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeRecientes($query, $dias = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    public function scopeExitosos($query)
    {
        return $query->whereNotIn('tipo', ['error', 'webhook_error', 'sync_error', 'campaign_creation_error', 'lead_sync_error', 'sync_exception', 'campaign_creation_exception']);
    }

    public function scopeConErrores($query)
    {
        return $query->whereIn('tipo', ['error', 'webhook_error', 'sync_error', 'campaign_creation_error', 'lead_sync_error', 'sync_exception', 'campaign_creation_exception']);
    }
}
