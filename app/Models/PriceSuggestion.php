<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceSuggestion extends Model
{
    use HasAppTimezone, HasFactory, SoftDeletes;

    protected $table = 'price_suggestions';

    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'expires_at'];

    protected $fillable = [
        'property_id',
        'suggested_price',
        'suggested_price_per_sqm',
        'minimum_price',
        'maximum_price',
        'current_price',
        'price_difference',
        'confidence_score',
        'recommendation',
        'reasoning',
        'comparable_properties',
        'market_trend',
        'estimated_sales_probability',
        'is_ai_generated',
        'algorithm_version',
        'generated_by',
        'market_analysis_id',
        'market_zone_label',
        'market_data_fetched_at',
        'market_comparable_count',
        'market_sources',
        'market_price_per_sqm',
        'expires_at',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'price_change_percentage',
        'is_expired',
        'is_valid',
        'has_market_data',
        'market_freshness',
    ];

    protected $casts = [
        'suggested_price' => 'decimal:2',
        'suggested_price_per_sqm' => 'decimal:2',
        'minimum_price' => 'decimal:2',
        'maximum_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'price_difference' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'estimated_sales_probability' => 'decimal:2',
        'comparable_properties' => 'array',
        'market_sources' => 'array',
        'market_comparable_count' => 'integer',
        'market_price_per_sqm' => 'decimal:2',
        'market_data_fetched_at' => 'datetime',
        'is_ai_generated' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Snapshot del análisis de mercado de zona con el que se calculó.
     */
    public function marketAnalysis()
    {
        return $this->belongsTo(PriceMarketAnalysis::class, 'market_analysis_id');
    }

    /**
     * Scopes
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereNotNull('suggested_price');
    }

    public function scopeByRecommendation($query, $recommendation)
    {
        return $query->where('recommendation', $recommendation);
    }

    public function scopeHighConfidence($query, $minScore = 75)
    {
        return $query->where('confidence_score', '>=', $minScore);
    }

    /**
     * ID de las propiedades comparables
     */
    public function getComparablePropertiesIds()
    {
        $data = $this->comparable_properties;

        return is_array($data) ? array_map('intval', $data) : [];
    }

    public function getComparablePropertiesData()
    {
        $ids = $this->getComparablePropertiesIds();
        if (empty($ids)) {
            return collect();
        }

        return Property::whereIn('id', $ids)
            ->get()
            ->map(fn ($property) => [
                'id' => $property->id,
                'title' => $property->title,
                'price' => $property->price,
                'currency' => $property->currency ?: 'DOP',
                'city' => $property->city,
                'state' => $property->state,
            ]);
    }

    public function markAsReviewed()
    {
        return $this->update(['is_ai_generated' => false]);
    }

    public function refresh()
    {
        if ($this->is_expired) {
            return app(\App\Services\PriceIntelligenceService::class)
                ->generatePriceSuggestion($this->property, true);
        }

        return $this;
    }

    /**
     * Atributos computados
     */
    public function getPriceChangePercentageAttribute()
    {
        if (empty($this->current_price) || empty($this->suggested_price)) {
            return 0.0;
        }

        return round((($this->suggested_price - $this->current_price) / $this->current_price) * 100, 2);
    }

    public function getIsExpiredAttribute()
    {
        return $this->expires_at ? $this->expires_at->isPast() : false;
    }

    public function getIsValidAttribute()
    {
        return ! $this->is_expired && $this->confidence_score >= 50;
    }

    /**
     * ¿La sugerencia se apoya en datos reales de mercado (búsqueda web)?
     */
    public function getHasMarketDataAttribute(): bool
    {
        return (int) $this->market_comparable_count > 0;
    }

    /**
     * Antigüedad de los datos de mercado en texto legible.
     */
    public function getMarketFreshnessAttribute(): ?string
    {
        if (! $this->market_data_fetched_at) {
            return null;
        }

        $days = (int) $this->market_data_fetched_at->diffInDays(now());

        if ($days <= 0) {
            return 'actualizado hoy';
        }
        if ($days === 1) {
            return 'actualizado ayer';
        }
        if ($days < 30) {
            return 'actualizado hace '.$days.' días';
        }

        $months = (int) floor($days / 30);

        return 'actualizado hace '.$months.' '.($months === 1 ? 'mes' : 'meses');
    }
}