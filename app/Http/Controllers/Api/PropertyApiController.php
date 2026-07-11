<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Advertisement;
use App\Models\AgentVerification;
use App\Models\AssignedOutdoorFacilities;
use App\Models\AssignParameters;
use App\Models\Category;
use App\Models\CityImage;
use App\Models\InterestedUser;
use App\Models\OutdoorFacilities;
use App\Models\parameter;
use App\Models\PaymentTransaction;
use App\Models\Projects;
use App\Models\PropertiesDocument;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Models\user_reports;
use App\Models\UserInterest;
use App\Models\VerifyCustomer;
use App\Rules\VideoUrlRule;
use App\Services\ApiResponseService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PropertyApiController extends Controller
{
    /**
     * Helper interno para obtener la tasa de cambio de manera eficiente.
     * Intenta leer la caché que genera tu otro controlador o hace un fetch rápido.
     */
    private function getExchangeRate()
{
    return Cache::remember('usd_to_dop_rate', 3600, function () {
        // Si estás en localhost, evita el Http::get para no congelar Artisan Serve
        if (request()->getHost() == '127.0.0.1' || request()->getHost() == 'localhost') {
            return 58.5; 
        }

        try {
            $response = Http::timeout(2)->get('http://127.0.0.1:8000/api/exchange-rate');
            if ($response->successful()) {
                return $response->json()['rate'] ?? 58.5;
            }
        } catch (\Exception $e) {
            Log::error("Error obteniendo tasa: " . $e->getMessage());
        }
        return 58.5;
    });
}

   public function get_property(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:200',
            'id' => 'nullable|integer',
            'slug_id' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:categories,id',
            'search' => 'nullable|string',
            'max_price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'property_type' => 'nullable|in:0,1,2,3',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'parameter_id' => 'nullable|integer',
            'users_promoted' => 'nullable|integer',
            'top_rated' => 'nullable|in:0,1',
            'most_liked' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        $offset = isset($request->offset) ? $request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        if (collect(Auth::guard('sanctum')->user())->isNotEmpty()) {
            $current_user = Auth::guard('sanctum')->user()->id;
        } else {
            $current_user = null;
        }

        $property = Property::with(['customer' => function ($query) {
            $query->withCount([
                'projects' => function ($query) { $query->onlyActive(); },
                'property' => function ($query) { $query->onlyActive(); },
            ]);
        }, 'user', 'category' => function ($categoryQuery) {
            $categoryQuery->select('id', 'category', 'image', 'slug_id')->with('translations');
        }, 'parameters', 'favourite', 'interested_users', 'translations'])->onlyActive();

        // Filtros aplicados...
        if (isset($request->max_price) || isset($request->min_price)) {
            $min_price = $request->min_price ?? 0;
            $max_price = $request->max_price ?? Property::max('price');
            $rate = $this->getExchangeRate();
            $property = $property->where(function ($query) use ($min_price, $max_price, $rate) {
                $query->where(function ($q) use ($min_price, $max_price) {
                    $q->where('currency', 'USD')->whereBetween('price', [$min_price, $max_price]);
                })->orWhere(function ($q) use ($min_price, $max_price, $rate) {
                    $q->where('currency', 'DOP')->whereBetween('price', [$min_price * $rate, $max_price * $rate]);
                });
            });
        }

        // Filtros básicos de detalle/listado
        if ($request->has('id') && ! empty($request->id)) {
            $property = $property->where('id', $request->id);
        }
        if ($request->has('slug_id') && ! empty($request->slug_id)) {
            $property = $property->where('slug_id', $request->slug_id);
        }
        if ($request->has('category_id') && ! empty($request->category_id)) {
            $property = $property->where('category_id', $request->category_id);
        }
        if ($request->has('property_type') && $request->property_type !== null && $request->property_type !== '') {
            $property = $property->where('propery_type', $request->property_type);
        }
        if ($request->has('city') && ! empty($request->city)) {
            $property = $property->where('city', 'like', '%'.$request->city.'%');
        }
        if ($request->has('state') && ! empty($request->state)) {
            $property = $property->where('state', 'like', '%'.$request->state.'%');
        }
        if ($request->has('country') && ! empty($request->country)) {
            $property = $property->where('country', 'like', '%'.$request->country.'%');
        }
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $property = $property->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('address', 'like', '%'.$search.'%')
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('category', 'like', '%'.$search.'%');
                    })
                    ->orWhere(function ($translationQuery) use ($search) {
                        $translationQuery->searchInAnyTranslation($search);
                    });
            });
        }
        
        $total = $property->count();
        $result = $property->orderBy('id', 'DESC')->skip($offset)->take($limit)->get()->map(function ($item) {
            $item->currency = strtoupper($item->currency ?? 'USD');
            return $item;
        });

        if (! $result->isEmpty()) {
            $property_details = get_property_details($result, $current_user, true);

            // Inject currency into all returned properties
            foreach ($property_details as $key => $details) {
                $originalModel = $result->firstWhere('id', $details['id']);
                if ($originalModel) {
                    $property_details[$key]['currency'] = strtoupper($originalModel->currency ?? 'USD');
                }
            }
            
            // --- INYECCIÓN MANUAL DE MONEDA ---
            foreach ($property_details as $key => $details) {
                $originalModel = $result->firstWhere('id', $details['id']);
                $property_details[$key]['currency'] = strtoupper($originalModel->currency ?? 'USD');
            }

            $id = $request->id;
            $propertyAddedAs = $property_details[0]['role_context'] ?? 'user';
            $categoryId = $property_details[0]['category']['id'] ?? null;
            
            $similarPropertyQuery = Property::onlyActive()
                ->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'role_context', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'currency', 'created_at', 'title_image', 'request_status', 'is_premium')
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                ->where('role_context', $propertyAddedAs)
                ->inRandomOrder()
                ->limit(10);

            if (isset($request->id) && !empty($request->id)) {
                $similarPropertyQuery->where('id', '!=', $request->id);
            }
            if (isset($request->slug_id) && !empty($request->slug_id)) {
                $similarPropertyQuery->where('slug_id', '!=', $request->slug_id);
            }

            $getSimilarProperties = [];
            if ((isset($id) && !empty($id)) || (isset($request->slug_id) && !empty($request->slug_id))) {
                $getSimilarPropertiesQueryData = $similarPropertyQuery->get()->map(function($item) {
                    $item->currency = strtoupper($item->currency ?? 'USD');
                    return $item;
                });
                $getSimilarProperties = get_property_details($getSimilarPropertiesQueryData, $current_user, true);

                // --- INYECCIÓN MANUAL EN SIMILARES ---
                foreach ($getSimilarProperties as $key => $simProp) {
                    $originalSimModel = $getSimilarPropertiesQueryData->firstWhere('id', $simProp['id']);
                    $getSimilarProperties[$key]['currency'] = strtoupper($originalSimModel->currency ?? 'USD');
                }
            }

            $response['error'] = false;
            $response['data'] = $property_details;
            $response['similar_properties'] = $getSimilarProperties;
            $response['total'] = $total;
        } else {
            $response = ['error' => false, 'message' => trans('No Data Found'), 'data' => []];
        }

        return $response;
    }

    public function getPropertyList(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|numeric',
                'limit' => 'nullable|numeric',
                'get_all_premium_properties' => 'nullable|in:1',
                'filters' => 'nullable|string',
                'ai_search_prompt' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            $filters = $request->filters;
            if (! empty($filters)) {
                $filters = base64_decode($filters);
                if (json_validate($filters)) {
                    $filters = json_decode($filters, true);
                } else {
                    $filters = [];
                }
            } else {
                $filters = [];
            }

            $isAiEnabled = 0;
            $search = $filters['search'] ?? null;

            $filterValidator = Validator::make(
                collect($filters)->toArray(),
                [
                    'property_type' => 'nullable|in:0,1',
                    'category_id' => 'nullable|exists:categories,id',
                    'category_slug_id' => 'nullable|exists:categories,slug_id',
                    'location.country' => 'nullable',
                    'location.state' => 'nullable',
                    'location.city' => 'nullable',
                    'location.place_id' => 'nullable',
                    'location.latitude' => 'nullable',
                    'location.longitude' => 'nullable',
                    'location.range' => 'nullable',
                    'price.min_price' => 'nullable|numeric',
                    'price.max_price' => 'nullable|numeric',
                    'posted_since' => 'nullable|in:0,1,2,3,4',
                    'flags.promoted' => 'nullable',
                    'flags.most_viewed' => 'nullable',
                    'flags.most_liked' => 'nullable',
                    'flags.get_all_premium_properties' => 'nullable',
                    'parameters' => 'nullable|array',
                    'parameters.*.id' => 'nullable|exists:parameters,id',
                    'parameters.*.value' => 'nullable',
                    'nearby_places' => 'nullable|array',
                    'nearby_places.*.id' => 'nullable|exists:outdoor_facilities,id',
                    'nearby_places.*.value' => 'nullable|integer',
                    'role_context' => 'nullable|in:user,agent',
                ]
            );
            if ($filterValidator->fails()) {
                ApiResponseService::validationError($filterValidator->errors()->first());
            }

            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            $propertyType = isset($filters['property_type']) ? $filters['property_type'] : null;
            $categoryId = isset($filters['category_id']) ? $filters['category_id'] : null;
            $minPrice = isset($filters['price']['min_price']) ? $filters['price']['min_price'] : null;
            $maxPrice = isset($filters['price']['max_price']) ? $filters['price']['max_price'] : null;
            $postedSince = $filters['posted_since'] ?? null;
            $addedAs = isset($filters['role_context']) ? $filters['role_context'] : null;

            $propertyQuery = Property::whereIn('propery_type', [0, 1])->where(function ($query) {
                return $query->onlyActive();
            })->when($addedAs, function ($query) use ($addedAs) {
                return $query->where('role_context', $addedAs);
            });

            if (isset($propertyType) && (! empty($propertyType) || $propertyType == 0)) {
                $propertyQuery = $propertyQuery->where('propery_type', $propertyType);
            }

            if (isset($categoryId) && ! empty($categoryId)) {
                $propertyQuery = $propertyQuery->where('category_id', $categoryId);
            }

            if ($isAiEnabled == 0 && isset($filters['search']) && $filters['search'] !== '') {
                $propertyQuery = $propertyQuery->where(function ($whereCondition) use ($search) {
                    $whereCondition->where('title', 'like', '%'.$search.'%')
                        ->orWhere('address', 'like', '%'.$search.'%')
                        ->orWhereHas('category', function ($query) use ($search) {
                            $query->where('category', 'like', '%'.$search.'%');
                        })
                        ->orWhere(function ($query) use ($search) {
                            $query->searchInAnyTranslation($search);
                        });
                });
            }

            // FILTRADO DE PRECIOS COMPUESTO SEGÚN LA MONEDA DE LA PROPIEDAD
            if (!empty($minPrice) || !empty($maxPrice)) {
                $minP = $minPrice ?? 0;
                $maxP = $maxPrice ?? Property::max('price');
                $rate = $this->getExchangeRate();

                $propertyQuery = $propertyQuery->where(function ($query) use ($minP, $maxP, $rate) {
                    $query->where(function ($q) use ($minP, $maxP) {
                        $q->where('currency', 'USD')
                          ->whereBetween('price', [$minP, $maxP]);
                    })->orWhere(function ($q) use ($minP, $maxP, $rate) {
                        $q->where('currency', 'DOP')
                          ->whereBetween('price', [$minP * $rate, $maxP * $rate]);
                    });
                });
            }

            $parameters = isset($filters['parameters']) ? $filters['parameters'] : null;
            if (isset($parameters) && ! empty($parameters)) {
                foreach ($parameters as $parameter) {
                    $parameterId = $parameter['id'];
                    $propertyQuery = $propertyQuery->whereHas('assignParameter', function ($query) use ($parameterId) {
                        $query->where('parameter_id', $parameterId)
                            ->where(function ($q) {
                                $q->whereNotNull('value')
                                    ->orWhere('value', '!=', '')
                                    ->orWhere('value', '!=', 'null');
                            });
                    });
                }
            }

            $total = $propertyQuery->count();
            $result = $propertyQuery->orderBy('id', 'DESC')->skip($offset)->take($limit)->get()->map(function ($item) {
                $item->currency = strtoupper($item->currency ?? 'USD');
                return $item;
            });

            return response()->json([
                'error' => false,
                'total' => $total,
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAddedProperties(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|numeric',
                'limit' => 'nullable|numeric',
                'slug_id' => 'nullable|string',
                'is_promoted' => 'nullable|in:0,1',
                'request_status' => 'nullable|string',
                'property_type' => 'nullable|string',
                'added_as' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            $user = Auth::user();
            if (! $user) {
                ApiResponseService::errorResponse('User is not authenticated');
            }

            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;

            $propertyQuery = Property::where('added_by', $user->id)
                ->whereIn('propery_type', [0, 1]);

            if (! empty($request->slug_id)) {
                $propertyQuery = $propertyQuery->where('slug_id', $request->slug_id);
            }

            if (isset($request->is_promoted) && $request->is_promoted !== '') {
                $propertyQuery = $propertyQuery->where('is_promoted', $request->is_promoted);
            }

            if (! empty($request->request_status)) {
                $propertyQuery = $propertyQuery->where('request_status', $request->request_status);
            }

            if (isset($request->property_type) && $request->property_type !== '' && $request->property_type !== ' ') {
                $propertyQuery = $propertyQuery->where('propery_type', $request->property_type);
            }

            if (! empty($request->added_as)) {
                $propertyQuery = $propertyQuery->where('role_context', $request->added_as);
            }

            $total = $propertyQuery->count();
            $result = $propertyQuery->orderBy('id', 'DESC')
                ->skip($offset)
                ->take($limit)
                ->select('id', 'slug_id', 'category_id', 'title', 'added_by', 'role_context', 'address', 'city', 'country', 'state', 'propery_type', 'price', 'currency', 'created_at', 'title_image', 'request_status', 'is_premium')
                ->get()
                ->map(function ($item) {
                    $item->currency = strtoupper($item->currency ?? 'USD');
                    return $item;
                });

            return response()->json([
                'error' => false,
                'total' => $total,
                'data' => $result,
                'message' => 'Data Fetched Successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'details' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    // Get property advance filter data
    public function propertyAdvanceFilterData()
    {
        try {
            $cacheKey = 'propertyAdvanceFilterData';
            $cachedData = Cache::get($cacheKey);
            if (! is_null($cachedData)) {
                ApiResponseService::successResponse('Data Fetched Successfully', $cachedData);
            }

            // Get property ids
            $propertyQuery = Property::where(['status' => 1, 'request_status' => 'approved'])
                ->whereIn('propery_type', [0, 1]);
            $propertyIds = $propertyQuery->pluck('id');

            // Get nearby facilities ids
            $nearbyFacilitiesId = AssignedOutdoorFacilities::whereIn('property_id', $propertyIds)->pluck('facility_id');

            // Get nearby facilities
            $nearbyFacilities = OutdoorFacilities::whereIn('id', $nearbyFacilitiesId)
                ->with('translations')
                ->get()
                ->map(function ($nearbyFacility) {
                    $nearbyFacility->translated_name = $nearbyFacility->translated_name;

                    return $nearbyFacility;
                });

            // Get parameter ids of all categories
            $categoryIds = $propertyQuery->pluck('category_id');
            $facilititesIds = [];
            $facilitiesOfCategory = Category::whereIn('id', $categoryIds)
                ->where('status', 1)
                ->get()
                ->pluck('parameter_types');

            foreach ($facilitiesOfCategory as $facility) {
                $facility = explode(',', $facility);
                $facility = array_filter($facility);
                $facility = array_unique($facility);
                $facility = array_values($facility);
                $facilititesIds = array_merge($facilititesIds, $facility);
            }

            // Get parameters
            $parameters = parameter::whereIn('id', $facilititesIds)
                ->with('translations')
                ->get()
                ->map(function ($parameter) {
                    $parameter->translated_name = $parameter->translated_name;
                    $parameter->translated_option_value = $parameter->translated_option_value;

                    return $parameter;
                });

            // Array of parameters and nearby facilities
            $propertyAdvanceFilterData = [
                'parameters' => $parameters,
                'nearby_facilities' => $nearbyFacilities,
            ];

            // Cache the data
            Cache::put($cacheKey, $propertyAdvanceFilterData, now()->addMinute(10));
            ApiResponseService::successResponse('Data Fetched Successfully', $propertyAdvanceFilterData);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }
}