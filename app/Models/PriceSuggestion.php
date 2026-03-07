<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceSuggestion extends Model
{
    use HasFactory, SoftDeletes, HasAppTimezone;

    protected $table = 'price_suggestions';

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
        'expires_at',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = [
        'price_change_percentage',
        'is_expired',
        'is_valid',
    ];

    /**
     * Get property relationship
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get user who generated the suggestion
     */
    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Calculate price change percentage
     */
    public function getPriceChangePercentageAttribute(): float
    {
        if ($this->current_price == 0) {
            return 0;
        }
        return (($this->suggested_price - $this->current_price) / $this->current_price) * 100;
    }

    /**
     * Check if suggestion is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    /**
     * Check if suggestion is still valid
     */
    public function getIsValidAttribute(): bool
    {
        return !$this->is_expired && $this->confidence_score >= 50;
    }

    /**
     * Get comparable properties
     */
    public function getComparablePropertiesData()
    {
        if (!$this->comparable_properties) {
            return collect();
        }

        return Property::whereIn('id', $this->comparable_properties)
            ->select('id', 'title', 'price', 'location', 'area', 'bedrooms', 'bathrooms')
            ->get();
    }

    /**
     * Scope: Get valid suggestions
     */
    public function scopeValid($query)
    {
        return $query->where('confidence_score', '>=', 50)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: Get by recommendation
     */
    public function scopeByRecommendation($query, $recommendation)
    {
        return $query->where('recommendation', $recommendation);
    }

    /**
     * Scope: Get high confidence
     */
    public function scopeHighConfidence($query, $minScore = 75)
    {
        return $query->where('confidence_score', '>=', $minScore);
    }

    /**
     * Mark suggestion as reviewed
     */
    public function markAsReviewed()
    {
        return $this->update([
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Refresh suggestion
     */
    public function refresh()
    {
        $this->delete();
        return true;
    }
}
