<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceAnalytic extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'price_analytics';

    protected $dates = ['created_at', 'updated_at'];

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
        'sample_count' => 'integer',
        'price_trend' => 'decimal:2',
        'avg_days_on_market' => 'integer',
        'market_demand' => 'decimal:2',
        'price_distribution' => 'array',
        'top_amenities' => 'array',
        'analysis_period_start' => 'datetime',
        'analysis_period_end' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopeByLocation($query, $location, $propertyType, $transactionType)
    {
        return $query->where('location', $location)
            ->where('property_type', $propertyType)
            ->where('transaction_type', $transactionType);
    }

    public function scopeMetrik($query, $metricType)
    {
        return $query->where('metric_type', $metricType);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public static function getLatestForLocation($location, $propertyType, $transactionType = 'sale')
    {
        return self::metrik('market_avg')
            ->byLocation($location, $propertyType, $transactionType)
            ->latest()
            ->first();
    }

    /**
     * Rango de precios (promedio ± N desviaciones estándar)
     */
    public function calculatePriceRange($stdDevMultiplier = 1.5)
    {
        $min = $this->average_price - ($this->std_deviation * $stdDevMultiplier);
        $max = $this->average_price + ($this->std_deviation * $stdDevMultiplier);

        return [
            'min' => round(max(0, $min), 2),
            'max' => round($max, 2),
        ];
    }

    /**
     * Condición de mercado basada en demanda.
     */
    public function getMarketCondition()
    {
        if ($this->market_demand === null) {
            return 'balanced';
        }

        if ($this->market_demand >= 80) {
            return 'hot_market';
        }

        if ($this->market_demand <= 40) {
            return 'slow_market';
        }

        return 'balanced';
    }
}