<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmInteraction extends Model
{
    use HasAppTimezone, HasFactory, SoftDeletes;

    protected $table = 'crm_interactions';

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
        'duracion_segundos' => 'integer',
    ];

    protected $dates = [
        'fecha_proxima_accion',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }
}
