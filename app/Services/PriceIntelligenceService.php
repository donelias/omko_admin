<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PriceHistory;
use App\Models\PriceSuggestion;
use App\Models\PriceAnalytic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class PriceIntelligenceService
{
    /**
     * Generate price suggestion for a property
     */
    public static function generatePriceSuggestion(Property $property, $forceRefresh = false): ?PriceSuggestion
    {
        try {
            // Verificar si ya existe sugerencia válida
            if (!$forceRefresh) {
                $existing = PriceSuggestion::where('property_id', $property->id)
                    ->valid()
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            // Obtener datos para análisis
            $comparableProperties = self::findComparableProperties($property);

            if ($comparableProperties->isEmpty()) {
                Log::warning("No comparable properties found for property {$property->id}");
                return null;
            }

            // Obtener análisis de mercado
            $marketAnalysis = self::getMarketAnalysis($property);

            // Calcular sugerencia de precio
            $priceData = self::calculateSuggestedPrice(
                $property,
                $comparableProperties,
                $marketAnalysis
            );

            // Generar recomendación
            $recommendation = self::generateRecommendation(
                $property->price,
                $priceData['suggested_price'],
                $priceData['confidence_score']
            );

            // Crear o actualizar sugerencia
            $existing = PriceSuggestion::where('property_id', $property->id)->first();

            if ($existing) {
                $existing->delete();
            }

            $suggestion = PriceSuggestion::create([
                'property_id' => $property->id,
                'suggested_price' => $priceData['suggested_price'],
                'suggested_price_per_sqm' => $priceData['suggested_price_per_sqm'],
                'minimum_price' => $priceData['minimum_price'],
                'maximum_price' => $priceData['maximum_price'],
                'current_price' => $property->price,
                'price_difference' => $priceData['suggested_price'] - $property->price,
                'confidence_score' => $priceData['confidence_score'],
                'recommendation' => $recommendation,
                'reasoning' => $priceData['reasoning'],
                'comparable_properties' => $comparableProperties->pluck('id')->toArray(),
                'market_trend' => $marketAnalysis['trend'],
                'estimated_sales_probability' => $priceData['sales_probability'],
                'is_ai_generated' => true,
                'algorithm_version' => '1.0',
                'generated_by' => auth()->id(),
                'expires_at' => now()->addDays(30),
            ]);

            Log::info("Price suggestion generated for property {$property->id}", [
                'suggested_price' => $priceData['suggested_price'],
                'confidence_score' => $priceData['confidence_score'],
            ]);

            return $suggestion;

        } catch (\Exception $e) {
            Log::error("Error generating price suggestion: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Find comparable properties based on location, type, and characteristics
     */
    public static function findComparableProperties(Property $property, $limit = 20): Collection
    {
        $query = Property::where('id', '!=', $property->id)
            ->where('type', $property->type)
            ->where('province', $property->province)
            ->where('status', 'listed')
            ->withoutTrashed();

        // Filtrar por similitud de características
        $query->whereBetween('area', [
            $property->area * 0.8,
            $property->area * 1.2,
        ]);

        if ($property->bedrooms) {
            $query->whereBetween('bedrooms', [
                max(1, $property->bedrooms - 1),
                $property->bedrooms + 1,
            ]);
        }

        // Dar prioridad a propiedades con historial de precios
        $comparables = $query->with('priceHistory')
            ->orderByRaw('ABS(area - ?) ASC', [$property->area])
            ->limit($limit)
            ->get()
            ->filter(fn($p) => $p->priceHistory->count() > 0 || $p->price > 0);

        return $comparables;
    }

    /**
     * Calculate suggested price using multiple algorithms
     */
    public static function calculateSuggestedPrice(
        Property $property,
        Collection $comparables,
        array $marketAnalysis
    ): array {
        // Algoritmo 1: Precio por m²
        $pricePerSqmAnalysis = self::pricePerSquareMeterAnalysis($property, $comparables);

        // Algoritmo 2: Regresión de características
        $featureRegressionAnalysis = self::featureRegressionAnalysis($property, $comparables);

        // Algoritmo 3: Histórico de la propiedad
        $historyAnalysis = self::propertyHistoryAnalysis($property);

        // Algoritmo 4: Análisis de mercado
        $marketAdjustment = self::marketAdjustment($property, $marketAnalysis);

        // Ponderar resultados
        $weights = [
            'price_per_sqm' => 0.35,
            'feature_regression' => 0.35,
            'history' => 0.20,
            'market' => 0.10,
        ];

        $suggestedPrice = (
            ($pricePerSqmAnalysis['estimated_price'] * $weights['price_per_sqm']) +
            ($featureRegressionAnalysis['estimated_price'] * $weights['feature_regression']) +
            ($historyAnalysis['estimated_price'] * $weights['history']) +
            ($marketAdjustment['adjusted_price'] * $weights['market'])
        );

        // Calcular confianza
        $confidenceScore = self::calculateConfidenceScore(
            $pricePerSqmAnalysis,
            $featureRegressionAnalysis,
            $historyAnalysis,
            $comparables->count()
        );

        // Calcular rango de precios
        $variance = $pricePerSqmAnalysis['std_deviation'] ?? ($suggestedPrice * 0.15);
        $minPrice = $suggestedPrice - $variance;
        $maxPrice = $suggestedPrice + $variance;

        // Estimación de probabilidad de venta
        $salesProbability = self::estimateSalesProbability(
            $property,
            $suggestedPrice,
            $marketAnalysis
        );

        // Construir reasoning
        $reasoning = self::buildReasoning([
            'pricePerSqm' => $pricePerSqmAnalysis,
            'featureRegression' => $featureRegressionAnalysis,
            'history' => $historyAnalysis,
            'market' => $marketAnalysis,
            'comparablesCount' => $comparables->count(),
        ]);

        return [
            'suggested_price' => round($suggestedPrice, 2),
            'suggested_price_per_sqm' => round($suggestedPrice / $property->area, 2),
            'minimum_price' => round($minPrice, 2),
            'maximum_price' => round($maxPrice, 2),
            'confidence_score' => round($confidenceScore, 2),
            'sales_probability' => round($salesProbability, 2),
            'reasoning' => $reasoning,
        ];
    }

    /**
     * Análisis: Precio por m²
     */
    private static function pricePerSquareMeterAnalysis(Property $property, Collection $comparables): array
    {
        $pricesPerSqm = $comparables
            ->filter(fn($p) => $p->area > 0)
            ->map(fn($p) => $p->price / $p->area);

        if ($pricesPerSqm->isEmpty()) {
            return [
                'estimated_price' => $property->price,
                'price_per_sqm' => 0,
                'std_deviation' => 0,
                'confidence' => 0,
            ];
        }

        $avgPricePerSqm = $pricesPerSqm->avg();
        $stdDeviation = self::calculateStdDeviation($pricesPerSqm);
        $estimatedPrice = $avgPricePerSqm * $property->area;

        return [
            'estimated_price' => $estimatedPrice,
            'price_per_sqm' => $avgPricePerSqm,
            'std_deviation' => $stdDeviation * $property->area,
            'confidence' => min(100, ($pricesPerSqm->count() / 20) * 100),
        ];
    }

    /**
     * Análisis: Regresión de características
     */
    private static function featureRegressionAnalysis(Property $property, Collection $comparables): array
    {
        if ($comparables->isEmpty()) {
            return [
                'estimated_price' => $property->price,
                'confidence' => 0,
            ];
        }

        $totalPrice = 0;
        $weights = 0;

        foreach ($comparables as $comparable) {
            // Calcular similitud
            $similarity = self::calculatePropertySimilarity($property, $comparable);

            // Ajustar por tamaño
            $areaRatio = $comparable->area > 0 ? $property->area / $comparable->area : 1;

            // Precio ajustado
            $adjustedPrice = $comparable->price * $areaRatio * $similarity;

            $totalPrice += $adjustedPrice * $similarity;
            $weights += $similarity;
        }

        $estimatedPrice = $weights > 0 ? $totalPrice / $weights : $property->price;

        return [
            'estimated_price' => $estimatedPrice,
            'confidence' => min(100, ($comparables->count() / 15) * 100),
        ];
    }

    /**
     * Análisis: Histórico de la propiedad
     */
    private static function propertyHistoryAnalysis(Property $property): array
    {
        $history = PriceHistory::forProperty($property->id)
            ->orderBy('created_at', 'desc')
            ->limit(12)
            ->get();

        if ($history->isEmpty()) {
            return [
                'estimated_price' => $property->price,
                'trend' => 'stable',
                'confidence' => 0,
            ];
        }

        // Precio promedio histórico
        $avgHistoricalPrice = $history->avg('price');

        // Tendencia de precio
        $recentPrices = $history->take(3)->pluck('price')->avg();
        $oldPrices = $history->skip(3)->take(3)->pluck('price')->avg();

        $priceTrend = $oldPrices > 0
            ? (($recentPrices - $oldPrices) / $oldPrices) * 100
            : 0;

        return [
            'estimated_price' => $avgHistoricalPrice,
            'trend' => $priceTrend > 5 ? 'increasing' : ($priceTrend < -5 ? 'decreasing' : 'stable'),
            'confidence' => min(100, ($history->count() / 12) * 100),
        ];
    }

    /**
     * Ajuste por condiciones de mercado
     */
    private static function marketAdjustment(Property $property, array $marketAnalysis): array
    {
        $marketMultiplier = 1.0;

        // Ajustar por demanda de mercado
        if ($marketAnalysis['demand'] >= 80) {
            $marketMultiplier += 0.08; // Aumentar 8%
        } elseif ($marketAnalysis['demand'] >= 60) {
            $marketMultiplier += 0.04; // Aumentar 4%
        } elseif ($marketAnalysis['demand'] < 40) {
            $marketMultiplier -= 0.05; // Disminuir 5%
        }

        // Ajustar por estacionalidad
        $month = now()->month;
        if (in_array($month, [6, 7, 8, 12])) { // Verano y Navidad
            $marketMultiplier += 0.03;
        }

        $adjustedPrice = $property->price * $marketMultiplier;

        return [
            'adjusted_price' => $adjustedPrice,
            'multiplier' => $marketMultiplier,
        ];
    }

    /**
     * Obtener análisis de mercado para la ubicación
     */
    public static function getMarketAnalysis(Property $property): array
    {
        $latest = PriceAnalytic::getLatestForLocation($property->province, $property->type);

        if (!$latest) {
            return self::generateMarketAnalysis($property);
        }

        return [
            'average_price' => $latest->average_price,
            'median_price' => $latest->median_price,
            'price_per_sqm' => $latest->price_per_sqm,
            'trend' => $latest->getMarketCondition(),
            'demand' => $latest->market_demand ?? 50,
            'days_on_market' => $latest->avg_days_on_market ?? 30,
        ];
    }

    /**
     * Generar análisis de mercado
     */
    private static function generateMarketAnalysis(Property $property): array
    {
        $properties = Property::where('province', $property->province)
            ->where('type', $property->type)
            ->where('status', 'listed')
            ->whereNotNull('price')
            ->withoutTrashed()
            ->limit(100)
            ->get();

        if ($properties->isEmpty()) {
            return [
                'average_price' => $property->price,
                'median_price' => $property->price,
                'price_per_sqm' => $property->area > 0 ? $property->price / $property->area : 0,
                'trend' => 'balanced',
                'demand' => 50,
                'days_on_market' => 30,
            ];
        }

        $prices = $properties->pluck('price');

        return [
            'average_price' => $prices->avg(),
            'median_price' => $prices->median(),
            'price_per_sqm' => $properties->filter(fn($p) => $p->area > 0)
                ->avg(fn($p) => $p->price / $p->area),
            'trend' => 'balanced',
            'demand' => 50,
            'days_on_market' => 30,
        ];
    }

    /**
     * Calcular similitud entre propiedades (0-1)
     */
    private static function calculatePropertySimilarity(Property $property1, Property $property2): float
    {
        $similarity = 1.0;

        // Similitud por área (80-120% = máxima similitud)
        if ($property2->area > 0) {
            $areaRatio = $property1->area / $property2->area;
            if ($areaRatio >= 0.8 && $areaRatio <= 1.2) {
                $similarity *= 1.0;
            } else {
                $similarity *= 1 - abs($areaRatio - 1) * 0.3;
            }
        }

        // Similitud por dormitorios
        if ($property1->bedrooms && $property2->bedrooms) {
            $bedroomDiff = abs($property1->bedrooms - $property2->bedrooms);
            $similarity *= max(0.7, 1 - ($bedroomDiff * 0.15));
        }

        // Similitud por baños
        if ($property1->bathrooms && $property2->bathrooms) {
            $bathroomDiff = abs($property1->bathrooms - $property2->bathrooms);
            $similarity *= max(0.7, 1 - ($bathroomDiff * 0.10));
        }

        return max(0.3, min(1.0, $similarity));
    }

    /**
     * Calcular desviación estándar
     */
    private static function calculateStdDeviation(Collection $values): float
    {
        if ($values->count() < 2) {
            return 0;
        }

        $mean = $values->avg();
        $variance = $values
            ->map(fn($v) => pow($v - $mean, 2))
            ->avg();

        return sqrt($variance);
    }

    /**
     * Calcular puntuación de confianza (0-100)
     */
    private static function calculateConfidenceScore(
        array $pricePerSqm,
        array $featureRegression,
        array $history,
        int $comparablesCount
    ): float {
        $baseScore = 50;

        // Bonus por número de propiedades comparables
        $comparablesBonus = min(25, $comparablesCount * 2);
        $baseScore += $comparablesBonus;

        // Bonus por datos históricos
        if (isset($history['confidence'])) {
            $baseScore += ($history['confidence'] / 100) * 10;
        }

        // Bonus por confianza de métodos
        if (isset($pricePerSqm['confidence'])) {
            $baseScore += ($pricePerSqm['confidence'] / 100) * 5;
        }

        return min(100, $baseScore);
    }

    /**
     * Generar recomendación basada en precio actual vs sugerido
     */
    private static function generateRecommendation(float $current, float $suggested, float $confidence): string
    {
        if ($confidence < 50) {
            return 'review_required';
        }

        $percentageDiff = (($suggested - $current) / $current) * 100;

        if ($percentageDiff > 5) {
            return 'increase';
        } elseif ($percentageDiff < -5) {
            return 'decrease';
        } else {
            return 'maintain';
        }
    }

    /**
     * Estimar probabilidad de venta
     */
    private static function estimateSalesProbability(Property $property, float $suggestedPrice, array $market): float
    {
        $baseProbability = 50;

        // Ajustar por diferencia de precio
        $priceDiff = abs($property->price - $suggestedPrice) / $suggestedPrice;
        if ($priceDiff < 0.05) {
            $baseProbability += 20;
        } elseif ($priceDiff < 0.10) {
            $baseProbability += 10;
        } elseif ($priceDiff > 0.20) {
            $baseProbability -= 15;
        }

        // Ajustar por demanda de mercado
        $baseProbability += ($market['demand'] / 100) * 25;

        // Ajustar por edad de la propiedad
        if ($property->created_at->diffInDays() > 90) {
            $baseProbability -= 10;
        }

        return max(10, min(95, $baseProbability));
    }

    /**
     * Construir explicación del análisis
     */
    private static function buildReasoning(array $analysis): string
    {
        $parts = [];

        $comparablesCount = $analysis['comparablesCount'] ?? 0;
        $parts[] = "Basado en análisis de $comparablesCount propiedades comparables.";

        if (isset($analysis['pricePerSqm']['price_per_sqm'])) {
            $parts[] = "Precio medio por m²: RD$ " . number_format($analysis['pricePerSqm']['price_per_sqm'], 2);
        }

        if (isset($analysis['history']['trend'])) {
            $trend = $analysis['history']['trend'];
            $parts[] = "Tendencia histórica: " . ucfirst($trend);
        }

        if (isset($analysis['market'])) {
            $demand = $analysis['market']['demand'] ?? 50;
            if ($demand >= 75) {
                $parts[] = "Mercado muy activo, demanda alta.";
            } elseif ($demand >= 50) {
                $parts[] = "Mercado equilibrado.";
            } else {
                $parts[] = "Mercado lento, baja demanda.";
            }
        }

        return implode(' ', $parts);
    }
}
