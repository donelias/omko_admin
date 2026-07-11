<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreQualification extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = [];

    protected $fillable = [
        'customer_id',
        'project_id',
        'property_id',
        'financial_entity_type',
        'financial_entity_id',
        'financial_advisor_id',
        'currency',
        'monthly_income',
        'status',
        'notes',
        'admin_notes',
        'submitted_by',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function project()
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function financialEntity()
    {
        return $this->morphTo('financial_entity');
    }

    public function financialAdvisor()
    {
        return $this->belongsTo(BankFinancialAdvisor::class, 'financial_advisor_id');
    }

    public function documents()
    {
        return $this->hasMany(PreQualificationDocument::class, 'pre_qualification_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(Customer::class, 'submitted_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
