<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceHistoryRequest;
use App\Models\PriceHistory;
use App\Models\Property;
use App\Services\ApiResponseService;
use App\Services\ExchangeRateService;
use App\Services\PriceIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PriceIntelligenceController extends Controller
{
    public function __construct(protected PriceIntelligenceService $service) {}

    /**
     * 1. GET /api/price-intelligence/suggestions/{propertyId}?force_refresh=false
     */
    public function getSuggestion($propertyId)
    {
        try {
            $property = Property::with('category')->find($propertyId);

            if (! $property) {
                return ApiResponseService::errorResponse('Property not found', '', null, 404);
            }

            $forceRefresh = filter_var(
                request('force_refresh', false),
                FILTER_VALIDATE_BOOLEAN
            );

            return ApiResponseService::successResponse(
                'Price suggestion generated',
                $this->service->generatePriceSuggestion($property, $forceRefresh)
            );
        } catch (Throwable $e) {
            if ($e->getCode() == 422) {
                return ApiResponseService::errorResponse(
                    'Could not generate price suggestion. Not enough data.',
                    '',
                    ['comparable_properties_found' => 0, 'historical_data_available' => false],
                    422
                );
            }

            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::getSuggestion error');
        }
    }

    /**
     * 2. GET /api/price-intelligence/analysis/{propertyId}
     */
    public function getAnalysis($propertyId)
    {
        try {
            $property = Property::with('category')->find($propertyId);

            if (! $property) {
                return ApiResponseService::errorResponse('Property not found', '', null, 404);
            }

            $metrics = $this->service->getPropertyMetrics($property);
            $comparables = $this->service->findComparableProperties($property, 18);

            $suggestion = $this->service->generatePriceSuggestion($property);
            $transactionType = in_array((int) $property->getRawOriginal('propery_type'), [1, 3]) ? 'rental' : 'sale';
            $marketAnalysis = $this->service->getMarketAnalysis(
                $property->state ?: $property->city,
                $this->service->propertyTypeOf($property),
                $transactionType
            );

            $data = [
                'property' => [
                    'id' => $property->id,
                    'title' => $property->title,
                    'price' => $property->price,
                    'currency' => $property->currency ?: 'DOP',
                    'area' => $metrics['area'],
                    'bedrooms' => $metrics['bedrooms'],
                    'bathrooms' => $metrics['bathrooms'],
                    'location' => $property->city,
                    'province' => $property->state,
                    'property_type' => $property->propery_type,
                ],
                'suggestion' => $suggestion,
                'market_analysis' => $marketAnalysis,
                'comparable_properties' => $comparables->map(fn ($comparable) => [
                    'id' => $comparable->id,
                    'title' => $comparable->title,
                    'image' => $comparable->title_image ?? null,
                    'price' => $comparable->price,
                    'currency' => $comparable->currency ?: 'DOP',
                    'price_per_sqm' => ! empty($comparable->metrics['area']) ? $comparable->price / $comparable->metrics['area'] : null,
                    'area' => $comparable->metrics['area'] ?? null,
                    'bedrooms' => $comparable->metrics['bedrooms'] ?? null,
                    'bathrooms' => $comparable->metrics['bathrooms'] ?? null,
                    'location' => $comparable->city,
                    'similarity' => $this->similarityLabel($comparable->similarity_score),
                ]),
                'price_history' => PriceHistory::getHistoryForProperty($property->id),
            ];

            return ApiResponseService::successResponse('Price analysis generated', $data);
        } catch (Throwable $e) {
            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::getAnalysis error');
        }
    }

    /**
     * 3. POST /api/price-intelligence/market-analysis
     */
    public function getMarketAnalysis(Request $request)
    {
        try {
            $location = $request->input('location');
            $propertyType = $request->get('property_type');
            $transactionType = in_array($request->input('transaction_type', 'sale'), ['sale', 'rental']) ? $request->input('transaction_type') : 'sale';

            if (empty($location)) {
                return ApiResponseService::validationError('The location field is required.');
            }

            $analysis = $this->service->getMarketAnalysis($location, $propertyType, $transactionType);

            if (! $analysis) {
                return ApiResponseService::errorResponse('No market data available for this location.', '', null, 422);
            }

            $data = [
                'location' => $location,
                'property_type' => $propertyType,
                'transaction_type' => $transactionType,
                'statistics' => [
                    'average_price' => $analysis->average_price,
                    'median_price' => $analysis->median_price,
                    'price_per_sqm' => $analysis->price_per_sqm,
                    'price_range' => $analysis->calculatePriceRange(),
                    'std_deviation' => $analysis->std_deviation,
                    'sample_count' => $analysis->sample_count,
                ],
                'market_condition' => $analysis->getMarketCondition(),
                'demand' => $analysis->market_demand,
                'avg_days_on_market' => $analysis->avg_days_on_market,
                'price_trend' => $analysis->price_trend,
                'last_updated' => $analysis->created_at,
            ];

            return ApiResponseService::successResponse('Market analysis generated', $data);
        } catch (Throwable $e) {
            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::getMarketAnalysis error');
        }
    }

    /**
     * 4. POST /api/price-intelligence/price-history
     */
    public function recordPriceHistory(StorePriceHistoryRequest $request)
    {
        try {
            $property = Property::find($request->input('property_id'));

            if (! $property) {
                return ApiResponseService::errorResponse('Property not found', '', null, 404);
            }

            $metrics = $this->service->getPropertyMetrics($property);
            $pricePerSqm = $request->input('price_per_sqm');
            if (empty($pricePerSqm) && $metrics['area'] > 0) {
                $pricePerSqm = round($request->input('price') / $metrics['area'], 2);
            }

            $history = PriceHistory::create([
                'property_id' => $property->id,
                'price' => $request->input('price'),
                'price_per_sqm' => $pricePerSqm,
                'status' => $request->input('status', 'listed'),
                'transaction_type' => $request->input('transaction_type', 'sale'),
                'days_on_market' => $request->input('days_on_market'),
                'notes' => $request->input('notes'),
                'recorded_by' => auth('sanctum')->user() ? auth('sanctum')->id() : null,
            ]);

            $refreshed = null;
            try {
                if (in_array($request->input('status', 'listed'), ['price_changed', 'listed'])) {
                    $refreshed = $this->service->generatePriceSuggestion($property, true);
                }
            } catch (Throwable $ignored) {
                // Sin datos suficientes: la sugerencia se regenera cuando haya más datos.
            }

            return ApiResponseService::successResponse(
                'Historico de precio registrado exitosamente',
                array_filter([
                    'id' => $history->id,
                    'property_id' => $history->property_id,
                    'price' => $history->price,
                    'price_per_sqm' => $history->price_per_sqm,
                    'status' => $history->status,
                    'transaction_type' => $history->transaction_type,
                    'created_at' => $history->created_at,
                ]),
                ['suggestion' => $refreshed]
            );
        } catch (Throwable $e) {
            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::recordPriceHistory error');
        }
    }

    /**
     * 5. POST /api/price-intelligence/bulk-suggestions
     */
    public function getBulkSuggestions(Request $request)
    {
        try {
            $propertyIds = $request->input('property_ids', []);

            if (! is_array($propertyIds) || empty($propertyIds)) {
                return ApiResponseService::validationError('The property_ids field is required and must be an array.');
            }

            $properties = Property::with('category')->whereIn('id', $propertyIds)->get();

            $suggestions = [];
            foreach ($properties as $property) {
                try {
                    $suggestion = $this->service->generatePriceSuggestion($property);
                    $suggestions[] = [
                        'property_id' => $property->id,
                        'suggested_price' => $suggestion->suggested_price,
                        'confidence_score' => $suggestion->confidence_score,
                        'recommendation' => $suggestion->recommendation,
                    ];
                } catch (Throwable $ignored) {
                    $suggestions[] = [
                        'property_id' => $property->id,
                        'suggested_price' => null,
                        'confidence_score' => null,
                        'recommendation' => 'review_required',
                    ];
                }
            }

            return ApiResponseService::successResponse(
                'Bulk suggestions generated',
                $suggestions,
                ['count' => count($suggestions)]
            );
        } catch (Throwable $e) {
            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::getBulkSuggestions error');
        }
    }

    /**
     * 6. GET /api/price-intelligence/comparables/{propertyId}
     */
    public function getComparables($propertyId)
    {
        try {
            $property = Property::find($propertyId);

            if (! $property) {
                return ApiResponseService::errorResponse('Property not found', '', null, 404);
            }

            $comparables = $this->service->findComparableProperties($property, 18);

            $data = $comparables->map(fn ($comparable) => [
                'id' => $comparable->id,
                'title' => $comparable->title,
                'image' => $comparable->title_image ?? null,
                'price' => $comparable->price,
                'currency' => $comparable->currency ?: 'DOP',
                'price_per_sqm' => ! empty($comparable->metrics['area']) ? $comparable->price / $comparable->metrics['area'] : null,
                'area' => $comparable->metrics['area'] ?? null,
                'bedrooms' => $comparable->metrics['bedrooms'] ?? null,
                'bathrooms' => $comparable->metrics['bathrooms'] ?? null,
                'location' => $comparable->city,
                'state' => $comparable->state,
                'similarity' => $this->similarityLabel($comparable->similarity_score),
                'similarity_score' => $comparable->similarity_score,
            ])->values();

            return ApiResponseService::successResponse(
                'Comparable properties found',
                $data,
                ['count' => $data->count()]
            );
        } catch (Throwable $e) {
            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::getComparables error');
        }
    }

    /**
     * 7. GET /api/price-intelligence/trends?location=&days=
     */
    public function getTrends(Request $request)
    {
        try {
            $location = $request->get('location');
            $days = min(365, max(1, (int) $request->get('days', 30)));
            $since = now()->subDays($days);

            $query = DB::table('price_history')
                ->join('propertys', 'propertys.id', '=', 'price_history.property_id')
                ->where('price_history.created_at', '>=', $since)
                ->whereNull('price_history.deleted_at');

            if (! empty($location)) {
                $query->where(function ($sub) use ($location) {
                    $sub->where('propertys.state', $location)->orWhere('propertys.city', $location);
                });
            }

            $rows = $query->get(['price_history.created_at', 'price_history.price', 'propertys.currency']);

            $byDay = [];
            foreach ($rows as $row) {
                $base = $this->service->toBaseCurrency($row->price, $row->currency ?: 'DOP');
                if ($base <= 0) {
                    continue;
                }
                $date = (new \Carbon\Carbon($row->created_at))->toDateString();
                if (! isset($byDay[$date])) {
                    $byDay[$date] = ['sum' => 0, 'count' => 0, 'min' => $base, 'max' => $base];
                }
                $byDay[$date]['sum'] += $base;
                $byDay[$date]['count']++;
                $byDay[$date]['min'] = min($byDay[$date]['min'], $base);
                $byDay[$date]['max'] = max($byDay[$date]['max'], $base);
            }

            ksort($byDay);

            $trends = [];
            foreach ($byDay as $date => $data) {
                $trends[] = [
                    'date' => $date,
                    'average_price' => round($data['sum'] / $data['count'], 2),
                    'min_price' => round($data['min'], 2),
                    'max_price' => round($data['max'], 2),
                    'count' => $data['count'],
                ];
            }

            $priceChangePercentage = 0.0;
            if (count($trends) >= 2) {
                $first = $trends[0]['average_price'];
                $last = $trends[count($trends) - 1]['average_price'];
                if ($first > 0) {
                    $priceChangePercentage = round((($last - $first) / $first) * 100, 2);
                }
            }

            return ApiResponseService::successResponse('Price trends generated', [
                'location' => $location,
                'period_days' => $days,
                'price_change_percentage' => $priceChangePercentage,
                'trends' => $trends,
            ]);
        } catch (Throwable $e) {
            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::getTrends error');
        }
    }

    protected function similarityLabel($score)
    {
        if ($score >= 0.8) {
            return 'high';
        }
        if ($score >= 0.5) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * 8. GET /api/price-intelligence/exchange-rates
     * Tasas de cambio vigentes (día vigente / BCRD) usadas por el motor.
     */
    public function exchangeRates(ExchangeRateService $service)
    {
        try {
            return ApiResponseService::successResponse(
                'Exchange rates retrieved successfully.',
                $service->ratesWithMeta()
            );
        } catch (Throwable $e) {
            return ApiResponseService::logErrorResponse($e, 'PriceIntelligenceController::exchangeRates error');
        }
    }
}