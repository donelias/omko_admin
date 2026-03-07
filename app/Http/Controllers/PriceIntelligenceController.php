<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PriceHistory;
use App\Models\PriceSuggestion;
use App\Models\PriceAnalytic;
use App\Services\PriceIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PriceIntelligenceController extends Controller
{
    /**
     * Get price suggestion for a property
     */
    public function getSuggestion($propertyId, Request $request)
    {
        if (!has_permissions('read', 'properties')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $property = Property::find($propertyId);
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        $forceRefresh = $request->get('force_refresh', false);

        try {
            $suggestion = PriceIntelligenceService::generatePriceSuggestion($property, $forceRefresh);

            if (!$suggestion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not generate price suggestion. Not enough data.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $suggestion->id,
                    'property_id' => $suggestion->property_id,
                    'current_price' => $suggestion->current_price,
                    'suggested_price' => $suggestion->suggested_price,
                    'suggested_price_per_sqm' => $suggestion->suggested_price_per_sqm,
                    'minimum_price' => $suggestion->minimum_price,
                    'maximum_price' => $suggestion->maximum_price,
                    'price_change_percentage' => $suggestion->price_change_percentage,
                    'confidence_score' => $suggestion->confidence_score,
                    'recommendation' => $suggestion->recommendation,
                    'reasoning' => $suggestion->reasoning,
                    'market_trend' => $suggestion->market_trend,
                    'estimated_sales_probability' => $suggestion->estimated_sales_probability,
                    'comparable_properties_count' => count($suggestion->comparable_properties ?? []),
                    'is_expired' => $suggestion->is_expired,
                    'is_valid' => $suggestion->is_valid,
                    'generated_at' => $suggestion->created_at,
                    'expires_at' => $suggestion->expires_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting price suggestion: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error generating price suggestion',
            ], 500);
        }
    }

    /**
     * Get detailed price analysis for a property
     */
    public function getAnalysis($propertyId, Request $request)
    {
        if (!has_permissions('read', 'properties')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $property = Property::find($propertyId);
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        try {
            // Obtener sugerencia
            $suggestion = PriceSuggestion::where('property_id', $propertyId)
                ->valid()
                ->first();

            if (!$suggestion) {
                $suggestion = PriceIntelligenceService::generatePriceSuggestion($property);
            }

            // Obtener historial de precios
            $priceHistory = PriceHistory::forProperty($propertyId)
                ->limit(12)
                ->get()
                ->map(fn($h) => [
                    'price' => $h->price,
                    'date' => $h->created_at,
                    'status' => $h->status,
                ]);

            // Obtener propiedades comparables
            $comparables = [];
            if ($suggestion && $suggestion->comparable_properties) {
                $comparables = Property::whereIn('id', $suggestion->comparable_properties)
                    ->select('id', 'title', 'price', 'area', 'bedrooms', 'bathrooms', 'location')
                    ->get()
                    ->toArray();
            }

            // Obtener análisis de mercado
            $marketAnalysis = PriceIntelligenceService::getMarketAnalysis($property);

            return response()->json([
                'success' => true,
                'data' => [
                    'property' => [
                        'id' => $property->id,
                        'title' => $property->title,
                        'price' => $property->price,
                        'area' => $property->area,
                        'location' => $property->location,
                        'province' => $property->province,
                    ],
                    'suggestion' => $suggestion ? [
                        'suggested_price' => $suggestion->suggested_price,
                        'confidence_score' => $suggestion->confidence_score,
                        'recommendation' => $suggestion->recommendation,
                        'reasoning' => $suggestion->reasoning,
                    ] : null,
                    'market_analysis' => $marketAnalysis,
                    'comparable_properties' => $comparables,
                    'price_history' => $priceHistory,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting price analysis: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error analyzing price',
            ], 500);
        }
    }

    /**
     * Get market analysis for a location
     */
    public function getMarketAnalysis(Request $request)
    {
        if (!has_permissions('read', 'properties')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'location' => 'required|string',
            'property_type' => 'nullable|string|in:house,apartment,land,commercial',
            'transaction_type' => 'nullable|string|in:sale,rental',
        ]);

        try {
            $location = $validated['location'];
            $propertyType = $validated['property_type'] ?? null;
            $transactionType = $validated['transaction_type'] ?? 'sale';

            $analytics = PriceAnalytic::byLocation($location, $propertyType, $transactionType)
                ->latest()
                ->first();

            if (!$analytics) {
                // Generar análisis si no existe
                $properties = \App\Models\Property::where('province', $location)
                    ->when($propertyType, fn($q) => $q->where('type', $propertyType))
                    ->where('status', 'listed')
                    ->whereNotNull('price')
                    ->limit(100)
                    ->get();

                if ($properties->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No data available for this location',
                    ], 404);
                }

                $prices = $properties->pluck('price');
                $avgPrice = $prices->avg();
                $medianPrice = $prices->median();
                $pricesPerSqm = $properties->filter(fn($p) => $p->area > 0)
                    ->map(fn($p) => $p->price / $p->area);
            } else {
                $avgPrice = $analytics->average_price;
                $medianPrice = $analytics->median_price;
                $pricesPerSqm = collect([$analytics->price_per_sqm]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'location' => $location,
                    'property_type' => $propertyType,
                    'transaction_type' => $transactionType,
                    'statistics' => [
                        'average_price' => $avgPrice,
                        'median_price' => $medianPrice,
                        'price_per_sqm' => $pricesPerSqm->avg(),
                        'price_range' => [
                            'min' => $pricesPerSqm->min(),
                            'max' => $pricesPerSqm->max(),
                        ],
                    ],
                    'market_condition' => $analytics?->getMarketCondition() ?? 'balanced',
                    'last_updated' => $analytics?->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting market analysis: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error analyzing market',
            ], 500);
        }
    }

    /**
     * Record a price history entry
     */
    public function recordPriceHistory(Request $request)
    {
        if (!has_permissions('create', 'properties')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'price' => 'required|numeric|min:0',
            'status' => 'required|string|in:listed,sold,rented,price_changed,delisted',
            'transaction_type' => 'required|string|in:sale,rental',
            'days_on_market' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $property = Property::find($validated['property_id']);

            $pricePerSqm = $property->area > 0
                ? $validated['price'] / $property->area
                : null;

            $history = PriceHistory::create([
                'property_id' => $validated['property_id'],
                'price' => $validated['price'],
                'price_per_sqm' => $pricePerSqm,
                'status' => $validated['status'],
                'transaction_type' => $validated['transaction_type'],
                'days_on_market' => $validated['days_on_market'],
                'notes' => $validated['notes'],
                'recorded_by' => auth()->id(),
            ]);

            Log::info("Price history recorded", [
                'property_id' => $validated['property_id'],
                'price' => $validated['price'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Price history recorded',
                'data' => $history,
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error recording price history: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error recording price history',
            ], 500);
        }
    }

    /**
     * Get price suggestions for multiple properties
     */
    public function getBulkSuggestions(Request $request)
    {
        if (!has_permissions('read', 'properties')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'property_ids' => 'required|array|min:1|max:50',
            'property_ids.*' => 'integer|exists:properties,id',
        ]);

        try {
            $suggestions = [];

            foreach ($validated['property_ids'] as $propertyId) {
                $property = Property::find($propertyId);
                $suggestion = PriceSuggestion::where('property_id', $propertyId)
                    ->valid()
                    ->first();

                if (!$suggestion) {
                    $suggestion = PriceIntelligenceService::generatePriceSuggestion($property);
                }

                if ($suggestion) {
                    $suggestions[] = [
                        'property_id' => $propertyId,
                        'suggested_price' => $suggestion->suggested_price,
                        'confidence_score' => $suggestion->confidence_score,
                        'recommendation' => $suggestion->recommendation,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'count' => count($suggestions),
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting bulk suggestions: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error getting suggestions',
            ], 500);
        }
    }

    /**
     * Get comparable properties for a property
     */
    public function getComparables($propertyId, Request $request)
    {
        if (!has_permissions('read', 'properties')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $property = Property::find($propertyId);
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }

        try {
            $comparables = PriceIntelligenceService::findComparableProperties($property);

            $data = $comparables->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'price' => $p->price,
                'price_per_sqm' => $p->area > 0 ? round($p->price / $p->area, 2) : 0,
                'area' => $p->area,
                'bedrooms' => $p->bedrooms,
                'bathrooms' => $p->bathrooms,
                'location' => $p->location,
                'similarity' => 'high',
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $comparables->count(),
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting comparables: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error getting comparable properties',
            ], 500);
        }
    }

    /**
     * Get price trends
     */
    public function getTrends(Request $request)
    {
        if (!has_permissions('read', 'properties')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'location' => 'required|string',
            'days' => 'nullable|integer|in:7,30,90,180,365',
        ]);

        $days = $validated['days'] ?? 30;
        $location = $validated['location'];

        try {
            $properties = Property::where('province', $location)
                ->where('status', 'listed')
                ->whereNotNull('price')
                ->withoutTrashed()
                ->pluck('id');

            $history = PriceHistory::whereIn('property_id', $properties)
                ->whereDate('created_at', '>=', now()->subDays($days))
                ->orderBy('created_at')
                ->get()
                ->groupBy(fn($h) => $h->created_at->format('Y-m-d'))
                ->map(fn($group) => [
                    'date' => $group->first()->created_at->format('Y-m-d'),
                    'average_price' => round($group->avg('price'), 2),
                    'count' => $group->count(),
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'location' => $location,
                    'period_days' => $days,
                    'trends' => $history,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting trends: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error getting price trends',
            ], 500);
        }
    }
}
