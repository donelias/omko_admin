<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceMarketAnalysis extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'price_market_analyses';

    protected $fillable = [
        'zone_key',
        'latitude',
        'longitude',
        'radius_km',
        'zone_label',
        'property_type',
        'transaction_type',
        'status',
        'error_message',
        'median_price',
        'average_price',
        'price_per_sqm',
        'price_per_sqm_min',
        'price_per_sqm_max',
        'min_price',
        'max_price',
        'market_condition',
        'comparable_count',
        'internal_comparable_count',
        'comparables',
        'sources',
        'summary',
        'confidence_score',
        'confidence_level',
        'model',
        'data_fetched_at',
        'next_refresh_at',
        'is_current',
        'algorithm_version',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_km' => 'decimal:1',
        'median_price' => 'decimal:2',
        'average_price' => 'decimal:2',
        'price_per_sqm' => 'decimal:2',
        'price_per_sqm_min' => 'decimal:2',
        'price_per_sqm_max' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'comparable_count' => 'integer',
        'internal_comparable_count' => 'integer',
        'comparables' => 'array',
        'sources' => 'array',
        'confidence_score' => 'decimal:2',
        'is_current' => 'boolean',
        'data_fetched_at' => 'datetime',
        'next_refresh_at' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeUsable($query)
    {
        return $query->whereIn('status', ['completed', 'partial']);
    }

    public function scopeZone($query, string $zoneKey)
    {
        return $query->where('zone_key', $zoneKey);
    }

    /**
     * Snapshot vigente de una zona.
     */
    public static function currentForZone(string $zoneKey, ?string $propertyType = null, string $transactionType = 'sale')
    {
        return self::query()
            ->zone($zoneKey)
            ->usable()
            ->current()
            ->when($propertyType, fn ($query) => $query->where('property_type', $propertyType))
            ->where('transaction_type', $transactionType)
            ->orderByDesc('data_fetched_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * ¿Los datos de mercado ya tienen la antigüedad máxima configurada?
     */
    public function isStale(int $maxAgeDays = 30): bool
    {
        if (! $this->data_fetched_at) {
            return true;
        }

        return $this->data_fetched_at->lt(now()->subDays($maxAgeDays));
    }

    /**
     * Antigüedad legible de los datos.
     */
    public function getFreshnessLabelAttribute(): string
    {
        if (! $this->data_fetched_at) {
            return 'sin datos';
        }

        $days = (int) $this->data_fetched_at->diffInDays(now());

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

    /**
     * Fuentes web para el frontend.
     */
    public function getSourcesListAttribute(): array
    {
        $list = [];
        $sources = is_array($this->sources) ? $this->sources : [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $uri = $source['uri'] ?? ($source['url'] ?? null);
            $domain = $source['domain'] ?? null;

            if (! $domain && $uri) {
                $domain = parse_url($uri, PHP_URL_HOST);
            }

            $list[] = [
                'title' => $source['title'] ?? $domain ?? $uri,
                'uri' => $uri,
                'url' => $uri,
                'domain' => $domain,
            ];
        }

        return $list;
    }

    /**
     * Contador de fuentes distintas.
     */
    public function getSourceCountAttribute(): int
    {
        if (isset($this->attributes['source_count'])) {
            return (int) $this->attributes['source_count'];
        }

        return count($this->sources_list ?? []);
    }

    /**
     * Comparables web listos para el frontend.
     */
    public function getComparablesListAttribute(): array
    {
        $list = [];

        foreach ((array) $this->comparables as $comparable) {
            if (! is_array($comparable)) {
                continue;
            }

            $list[] = [
                'title' => $comparable['title'] ?? null,
                'price' => isset($comparable['price']) ? (float) $comparable['price'] : null,
                'currency' => $comparable['currency'] ?? 'DOP',
                'area_sqm' => isset($comparable['area_sqm']) ? (float) $comparable['area_sqm'] : null,
                'bedrooms' => $comparable['bedrooms'] ?? null,
                'bathrooms' => $comparable['bathrooms'] ?? null,
                'url' => $comparable['url'] ?? null,
                'source' => $comparable['source'] ?? null,
                'price_per_sqm' => (! empty($comparable['price']) && ! empty($comparable['area_sqm']))
                    ? round($comparable['price'] / $comparable['area_sqm'], 2)
                    : null,
            ];
        }

        return $list;
    }
}
