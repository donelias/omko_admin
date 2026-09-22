<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientScreening extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'client_screenings';

    protected $fillable = [
        'property_id',
        'agent_id',
        'lead_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'origin',
        'status',
        'score',
        'nivel',
        'recomendacion_ia',
        'ia_metadata',
        'decision_agente',
        'notas_agente',
        'responded_at',
        'decided_at',
    ];

    protected $casts = [
        'ia_metadata' => 'array',
        'score' => 'integer',
    ];

    protected $dates = [
        'responded_at',
        'decided_at',
        'created_at',
        'updated_at',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function responses()
    {
        return $this->hasMany(ClientScreeningResponse::class, 'client_screening_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pendiente', 'en_evaluacion']);
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }
}