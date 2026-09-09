<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use App\Traits\HasTenantFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasAppTimezone, HasFactory, HasTenantFilter, SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'whatsapp',
        'property_id',
        'agent_id',
        'agency_id',
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
        'score' => 'integer',
    ];

    protected $dates = [
        'fecha_primer_contacto',
        'fecha_ultima_interaccion',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }

    public function interactions()
    {
        return $this->hasMany(CrmInteraction::class, 'lead_id');
    }

    public function scoring()
    {
        return $this->hasOne(CrmLeadScoring::class, 'lead_id');
    }

    public function campaignLeads()
    {
        return $this->hasMany(CrmCampaignLead::class, 'lead_id');
    }
}
