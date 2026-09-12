<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GooglePlacesService
{
    public static string $apiKey = '';

    public function __construct()
    {
        $placeApiKey = HelperService::getSettingData('place_api_key');
        self::$apiKey = (string) $placeApiKey;
    }

    public static function autocomplete(string $input, ?string $locale = null): array
    {
        if (self::$apiKey === '') {
            return [];
        }

        $locale = $locale ?? app()->getLocale();
        $normalized = mb_strtolower(trim($input));
        $cacheKey = 'gplaces:ac:'.md5($normalized.'|'.$locale);

        $cached = Cache::store('gplaces')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'key' => self::$apiKey,
            'input' => $input,
            'language' => $locale,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $json = (array) $response->json();
        if (is_array($json) && ($json['status'] ?? null) === 'OK') {
            Cache::store('gplaces')->put($cacheKey, $json, now()->addDays(7));
        }

        return $json;
    }

    public static function detailsOrGeocode(?string $placeId = null, ?float $latitude = null, ?float $longitude = null, ?string $locale = null): array
    {
        if (self::$apiKey === '') {
            return [];
        }

        $locale = $locale ?? app()->getLocale();
        $params = ['key' => self::$apiKey, 'language' => $locale];
        $cacheKey = '';
        $endpoint = '';

        if (! empty($placeId)) {
            // Directly fetch Place Details
            $params['place_id'] = $placeId;
            $params['fields'] = 'address_components,geometry,formatted_address';
            $cacheKey = 'gplaces:details:pid:'.$placeId.'|'.$locale;
            $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json';
        } else {
            // Step 1: Try to get place_id from cache (place_id is language-agnostic, so no locale in this key)
            $geoCacheKey = 'gplaces:geocode:latlng:'.$latitude.','.$longitude;
            $geocode = Cache::store('gplaces')->get($geoCacheKey);

            if (! $geocode) {
                // Not in cache → call API
                $geocode = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'latlng' => $latitude.','.$longitude,
                    'key' => self::$apiKey,
                ])->json();

                if (($geocode['status'] ?? null) === 'OK') {
                    Cache::store('gplaces')->put($geoCacheKey, $geocode, now()->addDays(7));
                }
            }

            $placeId = $geocode['results'][0]['place_id'] ?? null;
            if (! $placeId) {
                return [];
            }

            // Step 2: Fetch Place Details using place_id
            $params['place_id'] = $placeId;
            $params['fields'] = 'address_components,geometry,formatted_address';
            $cacheKey = 'gplaces:details:pid:'.$placeId.'|'.$locale;
            $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json';
        }

        // Place Details cache
        $cached = Cache::store('gplaces')->get($cacheKey);
        if (! is_null($cached)) {
            return (array) $cached;
        }

        $response = Http::get($endpoint, $params);
        if (! $response->successful()) {
            return [];
        }

        $json = (array) $response->json();
        if (is_array($json) && ($json['status'] ?? null) === 'OK') {
            Cache::store('gplaces')->put($cacheKey, $json, now()->addDays(7));
        }

        return $json;
    }
}
