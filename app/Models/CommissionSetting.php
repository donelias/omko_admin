<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommissionSetting extends Model
{
    use HasFactory, SoftDeletes, HasAppTimezone;

    protected $table = 'commission_settings';

    protected $fillable = [
        'commission_type',
        'commission_value',
        'currency',
        'description',
        'is_active',
        'apply_to_rentals',
        'apply_to_sales',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'commission_value' => 'decimal:2',
        'is_active' => 'boolean',
        'apply_to_rentals' => 'boolean',
        'apply_to_sales' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get active commission setting
     */
    public static function getActive()
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Calculate commission based on amount and settings
     */
    public function calculateCommission(float $amount): float
    {
        if ($this->commission_type === 'percentage') {
            return ($amount * $this->commission_value) / 100;
        } else {
            return (float)$this->commission_value;
        }
    }

    /**
     * Format commission value for display
     */
    public function getFormattedCommissionAttribute(): string
    {
        if ($this->commission_type === 'percentage') {
            return $this->commission_value . '%';
        } else {
            return $this->currency . ' ' . number_format($this->commission_value, 2);
        }
    }
}
