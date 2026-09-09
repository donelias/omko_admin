<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmCampaign extends Model
{
    use HasAppTimezone, HasFactory, SoftDeletes;

    protected $table = 'crm_campaigns';

    protected $fillable = [
        'nombre',
        'descripcion',
        'agent_id',
        'platform',
        'status',
        'meta_campaign_id',
        'meta_adset_id',
        'meta_ad_id',
        'presupuesto',
        'gastado',
        'impresiones',
        'clics',
        'leads_generados',
        'conversiones',
        'cpc',
        'cpl',
        'ctr',
        'audiencia_target',
        'exclusiones',
        'property_ids',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'presupuesto' => 'decimal:2',
        'gastado' => 'decimal:2',
        'impresiones' => 'integer',
        'clics' => 'integer',
        'leads_generados' => 'integer',
        'conversiones' => 'integer',
        'cpc' => 'decimal:2',
        'cpl' => 'decimal:2',
        'ctr' => 'decimal:2',
        'audiencia_target' => 'array',
        'exclusiones' => 'array',
        'property_ids' => 'array',
    ];

    protected $dates = [
        'fecha_inicio',
        'fecha_fin',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }

    public function campaignLeads()
    {
        return $this->hasMany(CrmCampaignLead::class, 'campaign_id');
    }
}
