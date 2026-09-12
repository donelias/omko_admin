<?php

namespace App\Services;

use App\Models\AssignParameters;
use App\Models\PriceAnalytic;
use App\Models\PriceHistory;
use App\Models\PriceSuggestion;
use App\Models\Property;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceIntelligenceService
{
    const ALGORITHM_VERSION = '1.0';

    // Pesos de los algoritmos (suma = 1.0)
    const WEIGHT_PRICE_PER_SQM = 0.35;
    const WEIGHT_FEATURE_REGRESSION = 0.35;
    const WEIGHT_HISTORY = 0.20;
    const WEIGHT_MARKET = 0.10;

    /**
     * ---------------------------------------------------------------------
     * CONVERSIÓN DE PRECIOS (moneda base + tasas de cambio)
     * ---------------------------------------------------------------------
     */

    public function baseCurrency()
    {
        return strtoupper(config('global.PRICE_BASE_CURRENCY', 'DOP'));
    }

    public function exchangeRates()
    {
        return app(ExchangeRateService::class)->rates();
    }

    public function rateFor($currency)
    {
        $currency = strtoupper((string) ($currency ?: 'DOP'));
        $rates = $this->exchangeRates();

        return (float) ($rates[$currency] ?? 1.0);
    }

    /**
     * Convierte un precio de su moneda a la moneda base (DOP).
     */
    public function toBaseCurrency($price, $currency = 'DOP')
    {
        if (empty($price) || (float) $price <= 0) {
            return 0.0;
        }

        return round((float) $price * $this->rateFor($currency), 2);
    }

    /**
     * Convierte un importe en moneda base a la moneda destino.
     */
    public function fromBaseCurrency($baseAmount, $currency = 'DOP')
    {
        $rate = $this->rateFor($currency);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        return round((float) $baseAmount / $rate, 2);
    }

    /**
     * ---------------------------------------------------------------------
     * MÉTRICAS DE LA PROPIEDAD (área, dormitorios, baños)
     * ---------------------------------------------------------------------
     */

    protected function parameterValues($property, array $names)
    {
        $result = [];

        AssignParameters::query()
            ->join('parameters', 'parameters.id', '=', 'assign_parameters.parameter_id')
            ->where('assign_parameters.modal_id', $property->id)
            ->where('assign_parameters.modal_type', Property::class)
            ->whereIn('parameters.name', $names)
            ->select('parameters.name', 'assign_parameters.value')
            ->get()
            ->each(function ($row) use (&$result) {
                $result[$row->name] = $row->value;
            });

        return $result;
    }

    public function getPropertyMetrics($property)
    {
        $values = $this->parameterValues($property, ['Build Area', 'Land Area', 'Bedrooms', 'Bathrooms']);

        $buildArea = $this->toFloat($values['Build Area'] ?? 0);
        $landArea = $this->toFloat($values['Land Area'] ?? 0);
        $area = $buildArea > 0 ? $buildArea : $landArea;

        return [
            'area' => round($area, 2),
            'bedrooms' => $this->toFloat($values['Bedrooms'] ?? 0),
            'bathrooms' => $this->toFloat($values['Bathrooms'] ?? 0),
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * PROPIEDADES COMPARABLES
     * ---------------------------------------------------------------------
     */

    public function findComparableProperties($property, $limit = 20)
    {
        if ($limit < 1) {
            $limit = 20;
        }

        $base = Property::query()
            ->where('id', '!=', $property->id)
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('price', '>', 0)
            ->where('propery_type', (int) $property->getRawOriginal('propery_type'))
            ->where(function ($query) use ($property) {
                $query->where('state', $property->state)
                    ->orWhere('city', $property->city)
                    ->orWhere('country', $property->country);
            })
            ->get();

        $targetMetrics = $this->getPropertyMetrics($property);
        if ($targetMetrics['area'] <= 0) {
            return collect();
        }

        return $base
            ->map(function ($candidate) use ($targetMetrics, $property) {
                $metrics = $this->getPropertyMetrics($candidate);
                $candidate->metrics = $metrics;
                $candidate->similarity_score = $this->calculateSimilarity($targetMetrics, $metrics, $property, $candidate);

                return $candidate;
            })
            ->filter(fn ($candidate) => $candidate->similarity_score > 0)
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();
    }

    /**
     * Similitud 0-1 entre la propiedad objetivo y un comparable.
     */
    protected function calculateSimilarity(array $target, array $candidate, $targetProperty, $candidateProperty)
    {
        $score = 0.0;
        $weightTotal = 0.0;

        // Área: 80-120% = máxima similitud
        if ($target['area'] > 0 && $candidate['area'] > 0) {
            $ratio = $candidate['area'] / $target['area'];
            if ($ratio >= 0.8 && $ratio <= 1.2) {
                $score += 1.0 * 0.5;
            } elseif ($ratio >= 0.5 && $ratio <= 1.5) {
                $score += 0.6 * 0.5;
            } else {
                $score += 0.2 * 0.5;
            }
            $weightTotal += 0.5;
        }

        // Dormitorios: ±1 = bueno
        if ($target['bedrooms'] > 0 && $candidate['bedrooms'] > 0) {
            $diff = abs($target['bedrooms'] - $candidate['bedrooms']);
            $score += ($diff <= 1 ? 1.0 : ($diff <= 2 ? 0.5 : 0.2)) * 0.25;
            $weightTotal += 0.25;
        }

        // Baños: ±1 = bueno
        if ($target['bathrooms'] > 0 && $candidate['bathrooms'] > 0) {
            $diff = abs($target['bathrooms'] - $candidate['bathrooms']);
            $score += ($diff <= 1 ? 1.0 : ($diff <= 2 ? 0.5 : 0.2)) * 0.25;
            $weightTotal += 0.25;
        }

        $similarity = $weightTotal > 0 ? ($score / $weightTotal) : 0;

        // Bonus por misma categoría y misma ciudad
        if ($candidateProperty->category_id && $targetProperty->category_id && $candidateProperty->category_id == $targetProperty->category_id) {
            $similarity = min(1.0, $similarity + 0.05);
        }
        if ($candidateProperty->city && $targetProperty->city && mb_strtolower(trim($candidateProperty->city)) === mb_strtolower(trim($targetProperty->city))) {
            $similarity = min(1.0, $similarity + 0.05);
        }

        return round($similarity, 3);
    }

    /**
     * ---------------------------------------------------------------------
     * SUGERENCIA DE PRECIO
     * ---------------------------------------------------------------------
     */

    public function generatePriceSuggestion($property, $forceRefresh = false)
    {
        $existing = PriceSuggestion::where('property_id', $property->id)->first();

        if (! $forceRefresh && $existing && ! $existing->is_expired) {
            return $existing;
        }

        $comparables = $this->findComparableProperties($property, 20);
        $targetMetrics = $this->getPropertyMetrics($property);

        if ($comparables->isEmpty() || $targetMetrics['area'] <= 0) {
            throw new Exception('Not enough comparable data to generate a price suggestion.', 422);
        }

        $transactionType = $this->transactionTypeFor($property);
        $marketAnalysis = $this->getMarketAnalysis(
            $property->state ?: $property->city,
            $this->propertyTypeOf($property),
            $transactionType
        );

        $calculation = $this->calculateSuggestedPrice($property, $comparables, $targetMetrics, $marketAnalysis);

        $confidence = $this->calculateConfidenceScore(
            $calculation['comparables_used'],
            $calculation['historical_data'],
            $calculation['algorithms_count']
        );
        $salesProbability = $this->estimateSalesProbability($property, $calculation['suggested_price'], $marketAnalysis);
        $recommendation = $this->recommendationFor($property->price, $calculation['suggested_price'], $confidence);
        $reasoning = $this->buildReasoning($calculation, $comparables->count());

        $suggestion = PriceSuggestion::updateOrCreate(
            ['property_id' => $property->id],
            [
                'property_id' => $property->id,
                'suggested_price' => round($calculation['suggested_price'], 2),
                'suggested_price_per_sqm' => $targetMetrics['area'] > 0 ? round($calculation['suggested_price'] / $targetMetrics['area'], 2) : null,
                'minimum_price' => round($calculation['minimum_price'], 2),
                'maximum_price' => round($calculation['maximum_price'], 2),
                'current_price' => $property->price,
                'price_difference' => round($calculation['suggested_price'] - $property->price, 2),
                'confidence_score' => round($confidence, 2),
                'recommendation' => $recommendation,
                'reasoning' => $reasoning,
                'comparable_properties' => array_map('intval', $comparables->pluck('id')->toArray()),
                'market_trend' => $marketAnalysis ? ($marketAnalysis->getMarketCondition() ?: 'balanced') : 'balanced',
                'estimated_sales_probability' => round($salesProbability, 2),
                'is_ai_generated' => true,
                'algorithm_version' => self::ALGORITHM_VERSION,
                'generated_by' => $this->currentUserId(),
                'expires_at' => now()->addDays((int) config('global.PRICE_SUGGESTION_VALID_DAYS', 30)),
            ]
        );

        return $suggestion;
    }

    /**
     * Combina los 4 algoritmos ponderados.
     */
    protected function calculateSuggestedPrice($property, $comparables, array $targetMetrics, $marketAnalysis)
    {
        $currency = $property->currency ?: 'DOP';

        $algo1 = $this->pricePerSquareMeterAnalysis($property, $comparables, $targetMetrics);
        $algo2 = $this->featureRegressionAnalysis($property, $comparables, $targetMetrics);
        $algo3 = $this->propertyHistoryAnalysis($property, $currency);
        $algo4 = $this->marketAdjustment($property, $marketAnalysis);

        $weightedBase = 0.0;
        $weightTotal = 0.0;

        if ($algo1 !== null) {
            $weightedBase += $this->toBaseCurrency($algo1['price'], $currency) * self::WEIGHT_PRICE_PER_SQM;
            $weightTotal += self::WEIGHT_PRICE_PER_SQM;
        }
        if ($algo2 !== null) {
            $weightedBase += $this->toBaseCurrency($algo2['price'], $currency) * self::WEIGHT_FEATURE_REGRESSION;
            $weightTotal += self::WEIGHT_FEATURE_REGRESSION;
        }
        if ($algo3 !== null) {
            $weightedBase += $this->toBaseCurrency($algo3['price'], $currency) * self::WEIGHT_HISTORY;
            $weightTotal += self::WEIGHT_HISTORY;
        }
        if ($algo4 !== null) {
            $weightedBase += $this->toBaseCurrency($algo4['price'], $currency) * self::WEIGHT_MARKET;
            $weightTotal += self::WEIGHT_MARKET;
        }

        if ($weightTotal <= 0) {
            throw new Exception('Not enough comparable data to generate a price suggestion.', 422);
        }

        $suggestedBase = $weightedBase / $weightTotal;
        $suggestedPrice = $this->fromBaseCurrency($suggestedBase, $currency);

        // Rango ±1 desviación estándar del algoritmo de m²
        $stdDevPrice = 0.0;
        if ($algo1 !== null) {
            $stdDevPrice = $this->fromBaseCurrency($algo1['std_deviation'] * $targetMetrics['area'], $currency);
        }

        return [
            'suggested_price' => $suggestedPrice,
            'minimum_price' => max(0, $suggestedPrice - abs($stdDevPrice)),
            'maximum_price' => $suggestedPrice + abs($stdDevPrice),
            'algorithms_count' => collect([$algo1, $algo2, $algo3, $algo4])->filter()->count(),
            'comparables_used' => $comparables->count(),
            'historical_data' => ($algo3 !== null),
            'algorithms' => [
                'price_per_sqm' => $algo1,
                'feature_regression' => $algo2,
                'history' => $algo3,
                'market' => $algo4,
            ],
        ];
    }

    /**
     * Algoritmo 1: Precio por m² de comparables (35%)
     */
    protected function pricePerSquareMeterAnalysis($property, $comparables, array $targetMetrics)
    {
        if ($targetMetrics['area'] <= 0) {
            return null;
        }

        $currency = $property->currency ?: 'DOP';
        $perSqm = [];

        foreach ($comparables as $comparable) {
            $area = $comparable->metrics['area'] ?? 0;
            if ($area <= 0) {
                continue;
            }
            $perSqm[] = $this->toBaseCurrency($comparable->price, $comparable->currency ?: 'DOP') / $area;
        }

        if (empty($perSqm)) {
            return null;
        }

        $average = array_sum($perSqm) / count($perSqm);
        $stdDeviation = $this->stddev($perSqm);

        $price = $this->fromBaseCurrency($average * $targetMetrics['area'], $currency);

        return [
            'price' => round($price, 2),
            'price_per_sqm' => $targetMetrics['area'] > 0 ? round($price / $targetMetrics['area'], 2) : null,
            'std_deviation' => round($stdDeviation, 2),
            'comparables_used' => count($perSqm),
        ];
    }

    /**
     * Algoritmo 2: Regresión de características (35%)
     */
    protected function featureRegressionAnalysis($property, $comparables, array $targetMetrics)
    {
        if ($comparables->isEmpty()) {
            return null;
        }

        $currency = $property->currency ?: 'DOP';
        $weightedSum = 0.0;
        $weightSum = 0.0;

        foreach ($comparables as $comparable) {
            $similarity = $comparable->similarity_score;
            if ($similarity <= 0) {
                continue;
            }

            $comparableArea = $comparable->metrics['area'] ?? 0;
            $areaRatio = ($comparableArea > 0 && $targetMetrics['area'] > 0) ? $targetMetrics['area'] / $comparableArea : 1;

            $adjustedBase = $this->toBaseCurrency($comparable->price, $comparable->currency ?: 'DOP') * $areaRatio;

            $weightedSum += $adjustedBase * $similarity;
            $weightSum += $similarity;
        }

        if ($weightSum <= 0) {
            return null;
        }

        $price = $this->fromBaseCurrency($weightedSum / $weightSum, $currency);

        return [
            'price' => round($price, 2),
            'comparables_used' => $comparables->count(),
        ];
    }

    /**
     * Algoritmo 3: Histórico de la propiedad (20%)
     */
    protected function propertyHistoryAnalysis($property, $currency = 'DOP')
    {
        $history = PriceHistory::getHistoryForProperty($property->id, 12);
        if ($history->isEmpty()) {
            return null;
        }

        $basePrices = $history->map(fn ($row) => $this->toBaseCurrency($row->price, $currency))->filter(fn ($p) => $p > 0)->values();

        if ($basePrices->isEmpty()) {
            return null;
        }

        $averageBase = $basePrices->avg();
        $trend = PriceHistory::getPriceTrendForProperty($property->id);
        $trendFactor = ($trend['percentage'] / 100) * 0.5; // ±50% de la tendencia detectada
        $trendFactor = max(-0.075, min(0.075, $trendFactor)); // tope ±7.5%

        $price = $this->fromBaseCurrency($averageBase * (1 + $trendFactor), $currency);

        return [
            'price' => round($price, 2),
            'average_price' => round($this->fromBaseCurrency($averageBase, $currency), 2),
            'trend_percentage' => $trend['percentage'],
            'trend_direction' => $trend['direction'],
            'history_count' => $history->count(),
        ];
    }

    /**
     * Algoritmo 4: Ajuste de mercado (10%)
     */
    protected function marketAdjustment($property, $marketAnalysis = null)
    {
        $currency = $property->currency ?: 'DOP';
        $base = $this->toBaseCurrency($property->price, $currency);
        $multiplier = 1.0;
        $reasons = [];

        $demand = $marketAnalysis ? (float) $marketAnalysis->market_demand : 60.0;
        if ($demand >= 80) {
            $multiplier += 0.08;
            $reasons[] = 'hot_market';
        } elseif ($demand >= 60) {
            $multiplier += 0.04;
            $reasons[] = 'active_market';
        } elseif ($demand < 40) {
            $multiplier -= 0.05;
            $reasons[] = 'slow_market';
        }

        $month = (int) now()->month;
        if (in_array($month, [6, 7, 8, 12])) {
            $multiplier += 0.03;
            $reasons[] = 'seasonal';
        }

        $daysOnMarket = $property->created_at ? (int) $property->created_at->diffInDays(now()) : 0;
        if ($daysOnMarket > 90) {
            $multiplier -= 0.10;
            $reasons[] = 'aged_listing';
        }

        $price = $this->fromBaseCurrency($base * max(0.5, $multiplier), $currency);

        return [
            'price' => round($price, 2),
            'multiplier' => round($multiplier, 4),
            'factors' => $reasons,
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * CONFIANZA, PROBABILIDAD Y RECOMENDACIÓN
     * ---------------------------------------------------------------------
     */

    public function calculateConfidenceScore($comparablesCount, $hasHistory, $algorithmsCount)
    {
        $score = 50.0;

        // +1 punto por comparable (máx +25)
        $score += min(25, max(0, $comparablesCount)) * 1;

        // +10 si hay datos históricos
        if ($hasHistory) {
            $score += 10;
        }

        // +5 si convergen ≥ 3 algoritmos
        if ($algorithmsCount >= 3) {
            $score += 5;
        }

        return max(0, round(min(100, $score), 2));
    }

    public function estimateSalesProbability($property, $suggestedPrice, $marketAnalysis)
    {
        $probability = 50.0;

        $current = (float) $property->price;
        $suggested = (float) $suggestedPrice;

        if ($current > 0 && $suggested > 0) {
            $difference = (($suggested - $current) / $current) * 100;
            if (abs($difference) <= 20) {
                $probability += 20;
            } elseif ($difference < -20) {
                $probability += 5;
            } else {
                $probability -= 10;
            }
        }

        $demand = $marketAnalysis ? (float) $marketAnalysis->market_demand : 60.0;
        if ($demand >= 80) {
            $probability += 25;
        } elseif ($demand >= 60) {
            $probability += 10;
        } elseif ($demand < 40) {
            $probability -= 15;
        }

        $daysOnMarket = $property->created_at ? (int) $property->created_at->diffInDays(now()) : 0;
        if ($daysOnMarket > 90) {
            $probability -= 10;
        }

        return max(10, min(95, $probability));
    }

    public function recommendationFor($currentPrice, $suggestedPrice, $confidence)
    {
        if ($confidence < 50) {
            return 'review_required';
        }

        if ($currentPrice > 0 && $suggestedPrice > 0) {
            $difference = (($suggestedPrice - $currentPrice) / $currentPrice) * 100;

            if ($difference > 5) {
                return 'increase';
            }
            if ($difference < -5) {
                return 'decrease';
            }
        }

        return 'maintain';
    }

    protected function buildReasoning(array $calculation, $comparableCount)
    {
        $algorithms = $calculation['algorithms'];
        $parts = [];

        if (! empty($algorithms['price_per_sqm'])) {
            $parts[] = 'Precio por m² de '.$algorithms['price_per_sqm']['comparables_used'].' comparables: '.number_format($algorithms['price_per_sqm']['price'], 2).'.';
        }
        if (! empty($algorithms['feature_regression'])) {
            $parts[] = 'Regresión por características ('.number_format($algorithms['feature_regression']['price'], 2).') ponderada por similitud.';
        }
        if (! empty($algorithms['history'])) {
            $parts[] = 'Histórico: promedio '.number_format($algorithms['history']['average_price'], 2).', tendencia '.$algorithms['history']['trend_direction'].' ('.$algorithms['history']['trend_percentage'].'%).';
        }
        if (! empty($algorithms['market'])) {
            $parts[] = 'Ajuste de mercado x'.number_format($algorithms['market']['multiplier'], 3).' ('.implode(', ', $algorithms['market']['factors']).').';
        }

        return 'Sugerencia generada con '.$comparableCount.' propiedades comparables.'.PHP_EOL.implode(PHP_EOL, $parts);
    }

    /**
     * ---------------------------------------------------------------------
     * ANÁLISIS DE MERCADO
     * ---------------------------------------------------------------------
     */

    public function getMarketAnalysis($location = null, $propertyType = null, $transactionType = 'sale', $forceRefresh = false)
    {
        if (empty($location)) {
            return null;
        }

        $cached = PriceAnalytic::getLatestForLocation($location, $propertyType, $transactionType);
        if (! $forceRefresh && $cached && $cached->created_at >= now()->subHour()) {
            return $cached;
        }

        $properties = Property::query()
            ->where('status', 1)
            ->where('request_status', 'approved')
            ->where('price', '>', 0)
            ->whereIn('propery_type', $transactionType === 'rental' ? [1, 3] : [0, 2])
            ->where(function ($query) use ($location) {
                $query->where('state', $location)->orWhere('city', $location);
            })
            ->when($propertyType && $propertyType !== 'other', function ($query) use ($propertyType) {
                return $query->whereHas('category', function ($category) use ($propertyType) {
                    return $category->where('slug_id', $propertyType)->orWhere('category', $propertyType);
                });
            })
            ->when($propertyType === 'other', function ($query) {
                return $query->doesntHave('category');
            })
            ->limit(200)
            ->get();

        if ($properties->isEmpty()) {
            return $cached;
        }

        $basePrices = $properties
            ->map(fn ($property) => $this->toBaseCurrency($property->price, $property->currency ?: 'DOP'))
            ->filter(fn ($price) => $price > 0)
            ->values();

        $perSqmValues = [];
        foreach ($properties as $property) {
            $metrics = $this->getPropertyMetrics($property);
            if ($metrics['area'] > 0) {
                $perSqmValues[] = $this->toBaseCurrency($property->price, $property->currency ?: 'DOP') / $metrics['area'];
            }
        }

        $prices = $basePrices->toArray();
        $sorted = $basePrices->sort()->values();

        $average = $basePrices->avg();
        $median = $sorted->get(intdiv($sorted->count(), 2)) ?? $average;
        $stdDeviation = $this->stddev($prices);

        $priceChange = $this->marketPriceTrend($location, $transactionType);

        $data = [
            'metric_type' => 'market_avg',
            'location' => $location,
            'property_type' => $propertyType,
            'transaction_type' => $transactionType,
            'average_price' => round($average, 2),
            'median_price' => round($median, 2),
            'price_per_sqm' => round(empty($perSqmValues) ? 0 : array_sum($perSqmValues) / count($perSqmValues), 2),
            'std_deviation' => round($stdDeviation, 2),
            'sample_count' => $properties->count(),
            'price_trend' => $priceChange['percentage'],
            'avg_days_on_market' => 0,
            'market_demand' => $this->estimateMarketDemand($properties),
            'price_distribution' => $this->priceDistribution($prices),
            'top_amenities' => [],
            'analysis_period_start' => now()->subDays(30)->format('Y-m-d H:i:s'),
            'analysis_period_end' => now()->format('Y-m-d H:i:s'),
        ];

        return PriceAnalytic::updateOrCreate(
            [
                'location' => $location,
                'property_type' => $propertyType,
                'transaction_type' => $transactionType,
                'metric_type' => 'market_avg',
            ],
            $data
        );
    }

    protected function marketPriceTrend($location, $transactionType)
    {
        $now = Carbon::now();
        $recent = $this->activePricesBetween($location, $now->copy()->subDays(30), $now);
        $previous = $this->activePricesBetween($location, $now->copy()->subDays(60), $now->copy()->subDays(30));

        if (empty($recent) || empty($previous)) {
            return ['percentage' => 0.0];
        }

        $recentAvg = array_sum($recent) / count($recent);
        $previousAvg = array_sum($previous) / count($previous);

        return ['percentage' => round((($recentAvg - $previousAvg) / $previousAvg) * 100, 2)];
    }

    protected function activePricesBetween($location, $from, $until)
    {
        $rows = DB::table('price_history')
            ->join('propertys', 'propertys.id', '=', 'price_history.property_id')
            ->where(function ($query) use ($location) {
                $query->where('propertys.state', $location)->orWhere('propertys.city', $location);
            })
            ->whereBetween('price_history.created_at', [$from, $until])
            ->get(['price_history.price', 'propertys.currency']);

        return $rows
            ->map(fn ($row) => $this->toBaseCurrency($row->price, $row->currency ?: 'DOP'))
            ->filter(fn ($price) => $price > 0)
            ->values()
            ->toArray();
    }

    protected function estimateMarketDemand($properties)
    {
        if ($properties->isEmpty()) {
            return 50.0;
        }

        // Heurística simple: demanda basada en cantidad de propiedades activas recientes + favoritos
        $propertyIds = $properties->pluck('id')->toArray();
        $favourites = \App\Models\Favourite::whereIn('property_id', $propertyIds)->count();

        $demand = 40 + (min(2, \App\Models\PropertyView::whereIn('property_id', $propertyIds)->count() / max(1, count($propertyIds))) * 20);
        $demand = $demand + min(20, $favourites >= 1 ? 10 : 0);

        return max(0, round(min(100, $demand), 2));
    }

    protected function priceDistribution(array $prices)
    {
        $prices = array_values(array_filter($prices, fn ($p) => $p > 0));
        if (empty($prices)) {
            return [];
        }

        $buckets = [];
        $min = min($prices);
        $max = max($prices);
        $bucketCount = max(5, min(10, intdiv((int) $max, 100000) + 1));
        $bucketSize = ($max - $min) / $bucketCount;

        if ($bucketSize <= 0) {
            return ['count' => count($prices)];
        }

        foreach ($prices as $price) {
            $index = (int) min($bucketCount - 1, floor(($price - $min) / $bucketSize));
            $bucket = $min + ($index * $bucketSize);
            $buckets[$bucket] = ($buckets[$bucket] ?? 0) + 1;
        }

        return $buckets;
    }

    /**
     * ---------------------------------------------------------------------
     * HELPERS
     * ---------------------------------------------------------------------
     */

    protected function transactionTypeFor($property)
    {
        return in_array((int) $property->getRawOriginal('propery_type'), [1, 3]) ? 'rental' : 'sale';
    }

    /**
     * Tipo de propiedad para analytics basado en su categoría (slug).
     */
    public function propertyTypeOf($property)
    {
        $category = $property->category;

        return $category ? ($category->slug_id ?: $category->category) : 'other';
    }

    protected function currentUserId()
    {
        if (auth('sanctum')->check()) {
            return auth('sanctum')->id();
        }

        return auth()->check() ? auth()->id() : null;
    }

    protected function toFloat($value)
    {
        $float = (float) $value;

        return $float < 0 ? 0 : $float;
    }

    protected function stddev(array $values)
    {
        $values = array_values(array_filter($values, fn ($value) => is_numeric($value)));
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += pow((float) $value - $mean, 2);
        }

        return sqrt($variance / ($count - 1));
    }
}