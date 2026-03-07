<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceAnalytic extends Model
{
    use HasFactory;

    protected $table = 'price_analytics';

    protected $fillable = [
        'metric_type',
        'location',
        'property_type',
        'transaction_type',
        'average_price',
        'median_price',
        'price_per_sqm',
        'std_deviation',
        'sample_count',
        'price_trend',
        'avg_days_on_market',
        'market_demand',
        'price_distribution',
        'top_amenities',
        'analysis_period_start',
        'analysis_period_end',
    ];

    protected $casts = [
        'average_price' => 'decimal:2',
        'median_price' => 'decimal:2',
        'price_per_sqm' => 'decimal:2',
        'std_deviation' => 'decimal:2',
        'price_trend' => 'decimal:2',
        'market_demand' => 'decimal:2',
        'price_distribution' => 'array',
        'top_amenities' => 'array',
        'analysis_period_start' => 'datetime',
        'analysis_period_end' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope: Get by location and property type
     */
    public function scopeByLocation($query, $location, $propertyType = null, $transactionType = 'sale')
    {
        $query->where('location', $location)
            ->where('transaction_type', $transactionType);

        if ($propertyType) {
            $query->where('property_type', $propertyType);
        }

        return $query;
    }

    /**
     * Scope: Get latest analysis
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get latest market analysis for location
     */
    public static function getLatestForLocation($location, $propertyType = null)
    {
        return self::byLocation($location, $propertyType)
            ->latest()
            ->first();
    }

    /**
     * Calculate price range based on analytics
     */
    public function calculatePriceRange($stdDevMultiplier = 1.5)
    {
        $range = $this->std_deviation * $stdDevMultiplier;

        return [
            'minimum' => $this->median_price - $range,
            'maximum' => $this->median_price + $range,
            'average' => $this->average_price,
        ];
    }

    /**
     * Get market condition
     */
    public function getMarketCondition(): string
    {
        if ($this->market_demand >= 75) {
            return 'hot_market';
        } elseif ($this->market_demand >= 50) {
            return 'balanced';
        } else {
            return 'slow_market';
        }
    }
}
