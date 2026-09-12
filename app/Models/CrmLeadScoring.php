<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmLeadScoring extends Model
{
    use HasAppTimezone, HasFactory;

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
        'score_total' => 'integer',
        'score_engagement' => 'integer',
        'score_interes' => 'integer',
        'score_tiempo' => 'integer',
        'score_comportamiento' => 'integer',
        'score_capacidad' => 'integer',
        'factores' => 'array',
    ];

    protected $dates = [
        'ultima_actualizacion',
        'created_at',
        'updated_at',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
