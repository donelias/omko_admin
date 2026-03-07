<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceHistory extends Model
{
    use HasFactory, SoftDeletes, HasAppTimezone;

    protected $table = 'price_history';

    protected $fillable = [
        'property_id',
        'price',
        'price_per_sqm',
        'status',
        'transaction_type',
        'days_on_market',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_per_sqm' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get property relationship
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get user who recorded the history
     */
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scope: Get history for a specific property
     */
    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId)->orderBy('created_at', 'desc');
    }

    /**
     * Scope: Get sold properties
     */
    public function scopeSold($query)
    {
        return $query->where('status', 'sold');
    }

    /**
     * Scope: Get rented properties
     */
    public function scopeRented($query)
    {
        return $query->where('status', 'rented');
    }

    /**
     * Scope: Get within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get average price for property
     */
    public static function getAveragePriceForProperty($propertyId, $days = 90)
    {
        return self::forProperty($propertyId)
            ->withoutTrashed()
            ->whereDate('created_at', '>=', now()->subDays($days))
            ->avg('price');
    }

    /**
     * Get price trend for property
     */
    public static function getPriceTrendForProperty($propertyId)
    {
        $thirtyDaysAgo = now()->subDays(30)->average(
            self::forProperty($propertyId)->whereDate('created_at', '>=', now()->subDays(60))
        );

        $current = self::forProperty($propertyId)
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->avg('price');

        if (!$thirtyDaysAgo) {
            return 0;
        }

        return (($current - $thirtyDaysAgo) / $thirtyDaysAgo) * 100;
    }
}
