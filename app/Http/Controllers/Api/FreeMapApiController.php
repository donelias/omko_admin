<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\GeoNamesMapService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/*
 * Free map provider endpoints (OpenStreetMap path), powered by GeoNames.
 * Kept separate from MapApiController (Google) so the two providers never share code.
 */
class FreeMapApiController extends Controller
{
    public function getOsmPlacesListData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'input' => 'required|string',
            ], [
                'input.required' => trans('Input is required'),
            ]);
            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }
            $input = (string) $request->query('input', '');
            $service = new GeoNamesMapService;
            $responseData = $service->autocomplete($input);

            return ApiResponseService::successResponse('Data Fetched Successfully', $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getOsmPlacesListData error: '.$e->getMessage());

            return ApiResponseService::errorResponse();
        }
    }

    public function getOsmPlaceDetailsData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required_without:place_id|numeric',
            'longitude' => 'required_without:place_id|numeric',
            'place_id' => 'required_without_all:latitude,longitude|string',
        ], [
            'latitude.required_without' => trans('Latitude is required when place_id is null'),
            'longitude.required_without' => trans('Longitude is required when place_id is null'),
            'latitude.numeric' => trans('Latitude must be a number'),
            'longitude.numeric' => trans('Longitude must be a number'),
            'place_id.required_without_all' => trans('Place ID is required when latitude and longitude are null'),
            'place_id.string' => trans('Place ID must be a string'),
        ]);
        if ($validator->fails()) {
            return ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $latitude = $request->has('latitude') ? (float) $request->latitude : null;
            $longitude = $request->has('longitude') ? (float) $request->longitude : null;
            $placeId = $request->has('place_id') ? $request->place_id : null;

            $service = new GeoNamesMapService;
            $responseData = $service->detailsOrReverse($placeId, $latitude, $longitude);

            return ApiResponseService::successResponse('Data Fetched Successfully', $responseData ?? []);
        } catch (Exception $e) {
            Log::error('getOsmPlaceDetailsData error: '.$e->getMessage());

            return ApiResponseService::errorResponse();
        }
    }
}
