<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmCampaignLead extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'crm_campaign_leads';

    protected $fillable = [
        'campaign_id',
        'lead_id',
        'conversion_status',
        'respondio',
        'interesado',
        'compro',
        'fecha_lead',
        'fecha_respuesta',
        'fecha_conversion',
    ];

    protected $casts = [
        'respondio' => 'boolean',
        'interesado' => 'boolean',
        'compro' => 'boolean',
    ];

    protected $dates = [
        'fecha_lead',
        'fecha_respuesta',
        'fecha_conversion',
        'created_at',
        'updated_at',
    ];

    public function campaign()
    {
        return $this->belongsTo(CrmCampaign::class, 'campaign_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
