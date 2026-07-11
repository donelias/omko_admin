<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bank extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = [];

    protected $fillable = [
        'name',
        'slug',
        'interest_rate',
        'currency',
        'email',
        'phone',
        'website',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Bank $bank) {
            if (empty($bank->slug)) {
                $bank->slug = Str::slug($bank->name);
            }
        });
    }

    public function financialAdvisors()
    {
        return $this->morphMany(BankFinancialAdvisor::class, 'entity');
    }

    public function primaryAdvisor()
    {
        return $this->morphOne(BankFinancialAdvisor::class, 'entity')->where('is_primary', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
