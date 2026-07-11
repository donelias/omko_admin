<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdBanner;
use App\Models\Article;
use App\Models\Category;
use App\Models\CityImage;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\HomepageSection;
use App\Models\Projects;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\User;
use App\Models\UserInterest;
use App\Services\ApiResponseService;
use App\Services\HelperService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class HomepageApiController extends Controller
{
    public function getHomepagePropertiesByCity()
    {
        try {
            $cityImageStyle = Setting::where('type', 'city_image_style')->first();
            $cityImageStyle = $cityImageStyle->data ?? 'style_1';
            $withImage = $cityImageStyle == 'style_1' ? true : false;
            $propertiesByCitiesSection = config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TYPE');
            $homepageSectionData = HomepageSection::where('section_type', $propertiesByCitiesSection)->where('is_active', 1)->first();
            if ($homepageSectionData) {
                $citiesData = CityImage::where('status', 1)->withCount(['property' => function ($query) {
                    $query->whereIn('propery_type', [0, 1])->onlyActive();
                }])->having('property_count', '>', 0)->orderBy('property_count', 'DESC')->limit(12)->get();
                $propertiesByCities = [];
                foreach ($citiesData as $city) {
                    if ($withImage) {
                        if (! empty($city->getRawOriginal('image'))) {
                            $url = $city->image;
                            $relativePath = parse_url($url, PHP_URL_PATH);
                            if (file_exists(public_path().$relativePath)) {
                                array_push($propertiesByCities, ['City' => $city->city, 'Count' => $city->property_count, 'image' => $city->image]);

                                continue;
                            }
                        }
                        $resultArray = $this->getUnsplashData($city);
                        array_push($propertiesByCities, $resultArray);
                    } else {
                        array_push($propertiesByCities, ['City' => $city->city, 'Count' => $city->property_count, 'image' => '']);
                    }
                }
                $data = [
                    'section_id' => $homepageSectionData->id,
                    'section_title' => $homepageSectionData->title,
                    'translated_title' => $homepageSectionData->translated_title,
                    'section_type' => $propertiesByCitiesSection,
                    'with_image' => $withImage,
                    'data' => $propertiesByCities ?? [],
                ];
            }
            ApiResponseService::successResponse(trans('Data Fetched Successfully'), $data ?? []);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getHomepagePropertiesOnMap(Request $request)
    {
        try {
            $latitude = $request->has('latitude') ? $request->latitude : null;
            $longitude = $request->has('longitude') ? $request->longitude : null;
            $radius = $request->has('radius') ? $request->radius : null;
            $locationBasedDataAvailable = false;

            $propertiesOnMapSection = config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TYPE');
            $homepageSectionData = HomepageSection::where('section_type', $propertiesOnMapSection)->where('is_active', 1)->first();

            if ($homepageSectionData) {
                $propertyBaseQuery = Property::select(
                    'id',
                    'slug_id',
                    'category_id',
                    'city',
                    'state',
                    'country',
                    'price',
                    'currency',
                    'propery_type',
                    'title',
                    'title_image',
                    'is_premium',
                    'address',
                    'rentduration',
                    'latitude',
                    'longitude',
                    'added_by',
                    'role_context',
                    'description'
                )
                    ->with(['category:id,slug_id,image,category', 'category.translations', 'translations'])
                    ->onlyActive()
                    ->whereIn('propery_type', [0, 1])
                    ->when($request->filled('role_context'), function ($query) use ($request) {
                        return $query->where('role_context', $request->role_context);
                    });

                $locationBasedPropertyQuery = clone $propertyBaseQuery;
                if ($latitude && $longitude) {
                    if ($radius) {
                        $locationBasedPropertyQuery->selectRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$latitude, $longitude, $latitude])
                            ->where('latitude', '!=', 0)
                            ->where('longitude', '!=', 0)
                            ->having('distance', '<', $radius);
                    } else {
                        $locationBasedPropertyQuery->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    }

                    if ($locationBasedPropertyQuery->exists()) {
                        $locationBasedDataAvailable = true;
                    } else {
                        $locationBasedPropertyQuery = clone $propertyBaseQuery;
                    }
                }
                $propertyMapper = function ($propertyData) {
                    $propertyData->promoted = $propertyData->is_promoted;
                    $propertyData->property_type = $propertyData->propery_type;
                    $propertyData->is_premium = $propertyData->is_premium == 1;
                    $propertyData->currency = strtoupper($propertyData->currency ?? 'USD');
                    $propertyData->parameters = $propertyData->parameters;
                    $propertyData->category->translated_name = $propertyData->category->translated_name;
                    $propertyData->translated_title = $propertyData->translated_title;
                    $propertyData->translated_description = $propertyData->translated_description;

                    return $propertyData;
                };
                $propertiesOnMap = $locationBasedPropertyQuery->clone()->get()->map($propertyMapper);
                $data = [
                    'section_id' => $homepageSectionData->id,
                    'section_type' => $propertiesOnMapSection,
                    'section_title' => $homepageSectionData->title,
                    'translated_title' => $homepageSectionData->translated_title,
                    'data' => $propertiesOnMap ?? [],
                ];
            }
            ApiResponseService::successResponse(trans('Data Fetched Successfully'), $data ?? [], ['location_based_data_available' => $locationBasedDataAvailable]);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAdBanners(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'page' => 'required|in:homepage,property_listing,property_detail',
                'platform' => 'required|in:app,web',
            ],
            [
                'page.required' => trans('The page field is required.'),
                'page.in' => trans('The page field must be a valid page.'),
                'platform.required' => trans('The platform field is required.'),
                'platform.in' => trans('The platform field must be a valid platform.'),
            ]
        );
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $page = $request->page;
            $platform = $request->platform;
            $now = now();
            $now = now();

            $adBanners = AdBanner::where('is_active', 1)
                ->where(['page' => $page, 'platform' => $platform])
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->with('property:id,title,slug_id')
                ->inRandomOrder()
                ->get()
                ->groupBy('placement')
                ->map(fn ($group) => $group->first())
                ->values(); // removes keys, returns flat array

            if (empty($adBanners)) {
                return ApiResponseService::validationError(trans('No ad banners found.'));
            }
            ApiResponseService::successResponse(trans('Data Fetched Successfully'), $adBanners);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepageSectionsData(Request $request)
    {
        try {
            $sections = HomepageSection::where('is_active', 1)
                ->orderBy('sort_order')
                ->with('translations')
                ->get()
                ->map(function ($section) {
                    // dd(gettype($section->sort_order));
                    return [
                        'id' => $section->id,
                        'type' => $section->section_type,
                        'title' => $section->title,
                        'translated_title' => $section->translated_title,
                        'sort_order' => $section->sort_order,
                        'is_active' => $section->is_active,
                    ];
                });

            ApiResponseService::successResponse('Homepage Sections Data Fetched Successfully', $sections);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepagePropertySections(Request $request)
    {
        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10;

            $nearbyPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TYPE');
            $featuredPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TYPE');
            $mostViewedPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TYPE');
            $mostLikedPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TYPE');
            $premiumPropertyHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TYPE');

            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $nearbyPropertyHomepageSection,
                    $featuredPropertyHomepageSection,
                    $mostViewedPropertyHomepageSection,
                    $mostLikedPropertyHomepageSection,
                    $premiumPropertyHomepageSection,
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order,
                        ],
                    ];
                });

            // Build optimized property base query once
            $propertyBaseQuery = Property::select(
                'id',
                'slug_id',
                'category_id',
                'city',
                'state',
                'country',
                'price',
                'currency',
                'propery_type',
                'title',
                'title_image',
                'is_premium',
                'address',
                'rentduration',
                'latitude',
                'longitude',
                'added_by',
                'role_context',
                'description',
                'total_click'
            )
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'translations',
                ])
                ->onlyActive()
                ->whereIn('propery_type', [0, 1])
                ->when($request->filled('role_context'), function ($query) use ($request) {
                    return $query->where('role_context', $request->role_context);
                });

            // Apply location filter once
            $locationBasedPropertyQuery = HelperService::applyLocationFilterToListings($propertyBaseQuery, $latitude, $longitude, $radius);

            $propertyMapper = function ($propertyData) {
                $propertyData->promoted = $propertyData->is_promoted;
                $propertyData->property_type = $propertyData->propery_type;
                $propertyData->is_premium = $propertyData->is_premium == 1;
                $propertyData->currency = strtoupper($propertyData->currency ?? 'USD');
                $propertyData->parameters = $propertyData->parameters;
                if ($propertyData->category) {
                    $propertyData->category->translated_name = $propertyData->category->translated_name;
                }
                $propertyData->translated_title = $propertyData->translated_title;
                $propertyData->translated_description = $propertyData->translated_description;

                return $propertyData;
            };

            $data = [];

            // Nearby Properties
            $data['nearby_properties'] = [
                'section_id' => $homepageData[$nearbyPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->inRandomOrder()->limit(12)->get()->map($propertyMapper),
            ];

            // Featured Properties
            $data['featured_properties'] = [
                'section_id' => $homepageData[$featuredPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->whereHas('advertisement', function ($subQuery) {
                    $subQuery->where(['is_enable' => 1, 'status' => 0])
                        ->whereNot('type', 'Slider');
                })->inRandomOrder()->limit(4)->get()->map($propertyMapper),
            ];

            // Most Viewed Properties
            $data['most_viewed_properties'] = [
                'section_id' => $homepageData[$mostViewedPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->orderBy('total_click', 'DESC')->limit(12)->get()->map($propertyMapper),
            ];

            // Most Liked Properties
            $data['most_liked_properties'] = [
                'section_id' => $homepageData[$mostLikedPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->withCount('favourite')->orderBy('favourite_count', 'DESC')->limit(12)->get()->map($propertyMapper),
            ];

            // Premium Properties
            $data['premium_properties'] = [
                'section_id' => $homepageData[$premiumPropertyHomepageSection]['id'] ?? null,
                'data' => $locationBasedPropertyQuery->clone()->where('is_premium', 1)->inRandomOrder()->limit(12)->get()->map($propertyMapper),
            ];

            // Compute location flag and fallback to global if all property sections are empty for given location
            $locationBasedDataProperties = false;
            if ($latitude && $longitude) {
                $hasNearby = isset($data['nearby_properties']['data']) && $data['nearby_properties']['data']->count() > 0;
                $hasFeatured = isset($data['featured_properties']['data']) && $data['featured_properties']['data']->count() > 0;
                $hasMostViewed = isset($data['most_viewed_properties']['data']) && $data['most_viewed_properties']['data']->count() > 0;
                $hasMostLiked = isset($data['most_liked_properties']['data']) && $data['most_liked_properties']['data']->count() > 0;
                $hasPremium = isset($data['premium_properties']['data']) && $data['premium_properties']['data']->count() > 0;

                if ($hasNearby || $hasFeatured || $hasMostViewed || $hasMostLiked || $hasPremium) {
                    $locationBasedDataProperties = true;
                } else {
                    // Rebuild all property sections from global (no location filter)
                    $globalPropertyQuery = $propertyBaseQuery->clone();

                    $data['nearby_properties'] = [
                        'section_id' => $homepageData[$nearbyPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->inRandomOrder()->limit(12)->get()->map($propertyMapper),
                    ];

                    $data['featured_properties'] = [
                        'section_id' => $homepageData[$featuredPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->whereHas('advertisement', function ($subQuery) {
                            $subQuery->where(['is_enable' => 1, 'status' => 0])
                                ->whereNot('type', 'Slider');
                        })->inRandomOrder()->limit(4)->get()->map($propertyMapper),
                    ];

                    $data['most_viewed_properties'] = [
                        'section_id' => $homepageData[$mostViewedPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->orderBy('total_click', 'DESC')->limit(12)->get()->map($propertyMapper),
                    ];

                    $data['most_liked_properties'] = [
                        'section_id' => $homepageData[$mostLikedPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->withCount('favourite')->orderBy('favourite_count', 'DESC')->limit(12)->get()->map($propertyMapper),
                    ];

                    $data['premium_properties'] = [
                        'section_id' => $homepageData[$premiumPropertyHomepageSection]['id'] ?? null,
                        'data' => $globalPropertyQuery->clone()->where('is_premium', 1)->inRandomOrder()->limit(12)->get()->map($propertyMapper),
                    ];
                }
            }

            $data['location_based_data'] = $locationBasedDataProperties;

            ApiResponseService::successResponse('Property Sections Fetched Successfully', $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepageProjectSections(Request $request)
    {
        try {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10;

            $projectsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TYPE');
            $featuredProjectsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TYPE');
            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $projectsHomepageSection,
                    $featuredProjectsHomepageSection,
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order,
                        ],
                    ];
                });

            // Build optimized projects base query once
            $projectsBaseQuery = Projects::select(
                'id',
                'slug_id',
                'city',
                'state',
                'country',
                'title',
                'type',
                'image',
                'location',
                'category_id',
                'added_by',
                'role_context',
                'latitude',
                'longitude'
            )
                ->onlyActive()
                ->when($request->filled('role_context'), function ($query) use ($request) {
                    return $query->where('role_context', $request->role_context);
                })
                ->with([
                    'category:id,slug_id,image,category',
                    'category.translations',
                    'gallary_images:id,project_id,name',
                    'customer:id,name,profile,email,mobile',
                    'translations',
                ]);

            // Apply location filter once
            $locationBasedProjectsQuery = HelperService::applyLocationFilterToListings($projectsBaseQuery, $latitude, $longitude, $radius);

            $projectMapper = function ($item) {
                if ($item->category) {
                    $item->category->translated_name = $item->category->translated_name;
                }
                $item->translated_title = $item->translated_title;
                $item->translated_description = $item->translated_description;

                return $item;
            };

            $data = [];

            // Regular Projects
            $data['projects'] = [
                'section_id' => $homepageData[$projectsHomepageSection]['id'] ?? null,
                'data' => $locationBasedProjectsQuery->clone()->inRandomOrder()->limit(12)->get()->map($projectMapper),
            ];

            // Featured Projects
            $data['featured_projects'] = [
                'section_id' => $homepageData[$featuredProjectsHomepageSection]['id'] ?? null,
                'data' => $locationBasedProjectsQuery->clone()
                    ->whereHas('advertisement', function ($query) {
                        $query->where(['is_enable' => 1, 'status' => 0]);
                    })->inRandomOrder()->limit(12)->get()->map($projectMapper),
            ];

            // Compute location flag and fallback to global if all project sections are empty for given location
            $locationBasedDataProjects = false;
            if ($latitude && $longitude) {
                $hasProjects = isset($data['projects']['data']) && $data['projects']['data']->count() > 0;
                $hasFeaturedProjects = isset($data['featured_projects']['data']) && $data['featured_projects']['data']->count() > 0;

                if ($hasProjects || $hasFeaturedProjects) {
                    $locationBasedDataProjects = true;
                } else {
                    // Rebuild projects sections from global (no location filter)
                    $globalProjectsQuery = $projectsBaseQuery->clone();

                    $data['projects'] = [
                        'section_id' => $homepageData[$projectsHomepageSection]['id'] ?? null,
                        'data' => $globalProjectsQuery->clone()->inRandomOrder()->limit(12)->get()->map($projectMapper),
                    ];

                    $data['featured_projects'] = [
                        'section_id' => $homepageData[$featuredProjectsHomepageSection]['id'] ?? null,
                        'data' => $globalProjectsQuery->clone()->whereHas('advertisement', function ($query) {
                            $query->where(['is_enable' => 1, 'status' => 0]);
                        })->inRandomOrder()->limit(12)->get()->map($projectMapper),
                    ];
                }
            }

            $data['location_based_data'] = $locationBasedDataProjects;

            ApiResponseService::successResponse('Project Sections Fetched Successfully', $data);
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getHomepageOtherSections(Request $request)
    {
        $latitude = $request->latitude != 'null' ? $request->latitude : null;
        $longitude = $request->longitude != 'null' ? $request->longitude : null;
        $radius = $request->radius != 'null' ? $request->radius : null;
        $locationBasedData = false;

        $categoriesHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TYPE') ?? 'categories';
        $agentsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TYPE') ?? 'agents';
        $articlesHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TYPE') ?? 'articles';
        $userRecommendationsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TYPE') ?? 'recommendations';
        $faqsHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TYPE') ?? 'faqs';
        $sliderHomepageSection = config('constants.HOMEPAGE_SECTION_TYPES.SLIDER_SECTION.TYPE') ?? 'slider';
        
        // Evitamos fallos si la tabla de secciones está vacía o no coincide
        try {
            $homepageData = HomepageSection::where('is_active', 1)
                ->whereIn('section_type', [
                    $categoriesHomepageSection,
                    $agentsHomepageSection,
                    $articlesHomepageSection,
                    $userRecommendationsHomepageSection,
                    $faqsHomepageSection,
                    $sliderHomepageSection,
                ])
                ->get()
                ->mapWithKeys(function ($item) {
                    return [
                        $item->section_type => [
                            'id' => $item->id,
                            'sort_order' => $item->sort_order,
                        ],
                    ];
                });
        } catch (\Exception $e) {
            $homepageData = collect([]);
        }

        $data = [];

        // ==========================================
        // 1. CATEGORÍAS (Super Blindadas)
        // ==========================================
        try {
            $categoriesQuery = Category::select('id', 'category', 'image', 'slug_id')->where('status', 1);

            if ($latitude && $longitude) {
                $locationBasedData = true;
                $validCategoryIds = DB::table('propertys')
                    ->select('category_id')
                    ->where(['status' => 1, 'request_status' => 'approved'])
                    ->where(function ($q) {
                        $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                    })
                    ->where('latitude', '!=', 0)
                    ->where('longitude', '!=', 0)
                    ->when($radius, function ($q) use ($latitude, $longitude, $radius) {
                        return $q->whereRaw('(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?', [$latitude, $longitude, $latitude, $radius]);
                    }, function ($q) use ($latitude, $longitude) {
                        return $q->where(['latitude' => $latitude, 'longitude' => $longitude]);
                    })
                    ->distinct()
                    ->pluck('category_id')
                    ->toArray();

                $categoriesQuery->whereIn('id', $validCategoryIds);

                if ($radius) {
                    $categoriesQuery->selectRaw('(SELECT COUNT(*) FROM propertys WHERE categories.id = propertys.category_id AND status = 1 AND request_status = "approved" AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as properties_count', [$latitude, $longitude, $latitude, $radius]);
                } else {
                    $categoriesQuery->selectRaw('(SELECT COUNT(*) FROM propertys WHERE categories.id = propertys.category_id AND status = 1 AND request_status = "approved" AND latitude = ? AND longitude = ?) as properties_count', [$latitude, $longitude]);
                }
            } else {
                // Si 'onlyActive' falla por no estar definido en tu modelo Category, usamos un fallback directo
                if (method_exists(Category::class, 'scopeOnlyActive')) {
                    $categoriesQuery->whereHas('properties', function ($query) { $query->onlyActive(); });
                }
                
                $categoriesQuery->withCount(['properties' => function ($query) {
                    $query->where(['status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                        $q->where('expiry_date', '>=', now())->orWhereNull('expiry_date');
                    });
                }]);
            }

            if (method_exists(Category::class, 'translations')) {
                $categoriesQuery->with('translations');
            }

            $data['categories'] = [
                'section_id' => $homepageData[$categoriesHomepageSection]['id'] ?? null,
                'data' => $categoriesQuery->limit(12)->get()
            ];
        } catch (\Exception $e) {
            $data['categories'] = ['section_id' => null, 'data' => []];
        }

        // ==========================================
        // 2. AGENTES
        // ==========================================
        try {
            $agentsQuery = Customer::select('id', 'name', 'email', 'profile', 'slug_id', 'is_agent_verified')
                ->where('is_agent', 1)
                ->where('isActive', 1);

            if (method_exists(Customer::class, 'agent_profile')) {
                $agentsQuery->with('agent_profile');
            }

            if ($latitude && $longitude) {
                if ($radius) {
                    $agentsQuery->selectRaw('(SELECT COUNT(*) FROM projects WHERE customers.id = projects.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as projects_count', [$latitude, $longitude, $latitude, $radius])
                        ->selectRaw('(SELECT COUNT(*) FROM propertys WHERE customers.id = propertys.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude != 0 AND longitude != 0 AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) < ?) as property_count', [$latitude, $longitude, $latitude, $radius]);
                } else {
                    $agentsQuery->selectRaw('(SELECT COUNT(*) FROM projects WHERE customers.id = projects.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude = ? AND longitude = ?) as projects_count', [$latitude, $longitude])
                        ->selectRaw('(SELECT COUNT(*) FROM propertys WHERE customers.id = propertys.added_by AND status = 1 AND request_status = "approved" AND role_context = \'agent\' AND latitude = ? AND longitude = ?) as property_count', [$latitude, $longitude]);
                }
            } else {
                $agentsQuery->withCount([
                    'properties' => function ($query) { $query->where(['status' => 1, 'request_status' => 'approved']); },
                    'projects' => function ($query) { $query->where(['status' => 1, 'request_status' => 'approved']); }
                ]);
            }

            $data['agents'] = [
                'section_id' => $homepageData[$agentsHomepageSection]['id'] ?? null,
                'data' => $agentsQuery->limit(12)->get()
            ];
        } catch (\Exception $e) {
            $data['agents'] = ['section_id' => null, 'data' => []];
        }

        // ==========================================
        // 3. ARTÍCULOS Y FAQS
        // ==========================================
        try {
            $articlesQuery = Article::where('status', 1)->orderBy('id', 'DESC')->limit(12);
            if (method_exists(Article::class, 'translations')) { $articlesQuery->with('translations'); }
            $data['articles'] = [
                'section_id' => $homepageData[$articlesHomepageSection]['id'] ?? null,
                'data' => $articlesQuery->get()
            ];
        } catch (\Exception $e) {
            $data['articles'] = ['section_id' => null, 'data' => []];
        }

        try {
            $data['faqs'] = [
                'section_id' => $homepageData[$faqsHomepageSection]['id'] ?? null,
                'data' => Faq::where('status', 1)->orderBy('id', 'DESC')->get()
            ];
        } catch (\Exception $e) {
            $data['faqs'] = ['section_id' => null, 'data' => []];
        }

        // ==========================================
        // 4. SLIDERS Y BANNERS
        // ==========================================
        try {
            $sliderQuery = Slider::select('id', 'type', 'image', 'web_image', 'category_id', 'propertys_id', 'show_property_details', 'link')
                ->with([
                    'category:id,category,image,slug_id',
                    'category.translations',
                    'property:id,slug_id,title,title_image,price,propery_type,city,state,country,rentduration,is_premium,category_id',
                ]);
            if (Schema::hasColumn('sliders', 'status')) {
                $sliderQuery->where('status', 1);
            }

            $sliderData = $sliderQuery->orderBy('id', 'DESC')->get()->map(function ($slider) {
                // Keep numeric type for frontend checks (2 = category, 4 = external link).
                $slider->slider_type = (string) $slider->getRawOriginal('type');

                if (collect($slider->property)->isNotEmpty()) {
                    $slider->property->property_type = $slider->property->propery_type;
                    $slider->property->parameters = $slider->property->parameters;
                }

                if ($slider->category) {
                    $slider->category->translated_name = $slider->category->translated_name;
                }

                // Fallback so slider is still visible when no dedicated slider image exists.
                if (empty($slider->web_image) && !empty($slider->property?->title_image)) {
                    $slider->web_image = $slider->property->title_image;
                }
                if (empty($slider->image) && !empty($slider->property?->title_image)) {
                    $slider->image = $slider->property->title_image;
                }

                return $slider;
            });

            $data['slider'] = [
                'section_id' => $homepageData[$sliderHomepageSection]['id'] ?? null,
                'data' => $sliderData,
            ];
        } catch (\Exception $e) {
            $data['slider'] = ['section_id' => null, 'data' => []];
        }
        
        try {
            $now = now();
            $adBannersQuery = AdBanner::where('is_active', 1)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now);
            if (method_exists(AdBanner::class, 'property')) { $adBannersQuery->with('property:id,title,slug_id'); }
            $data['ad_banners'] = $adBannersQuery->get();
        } catch (\Exception $e) {
            $data['ad_banners'] = [];
        }

        $data['location_based_data'] = $locationBasedData;

        return ApiResponseService::successResponse('Other Sections Fetched Successfully', $data);
    }

    private function getUnsplashData($cityData)
    {
        $apiKey = env('UNSPLASH_API_KEY');
        if (empty($apiKey)) {
            return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => ''];
        }

        $query = $cityData->city;
        $apiUrl = 'https://api.unsplash.com/search/photos/?query='.urlencode($query);
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID '.$apiKey,
        ]);
        $unsplashResponse = curl_exec($ch);
        $curlError = curl_errno($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== 0 || $httpStatus < 200 || $httpStatus >= 300 || empty($unsplashResponse)) {
            return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => ''];
        }

        $unsplashData = json_decode($unsplashResponse, true);
        if (isset($unsplashData['results'])) {
            $results = $unsplashData['results'];
            $imageUrl = '';
            foreach ($results as $result) {
                $imageUrl = $result['urls']['regular'];
                break;
            }
            if ($imageUrl != '') {
                return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => $imageUrl];
            }
        }

        return ['City' => $cityData->city, 'Count' => $cityData->property_count, 'image' => ''];
    }
}
