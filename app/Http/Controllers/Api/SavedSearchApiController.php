<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SavedSearchApiController extends Controller
{
    public function index(Request $request)
    {
        $searches = SavedSearch::where('customer_id', Auth::id())
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'error' => false,
            'data' => $searches,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'filters' => 'required|array',
            'frequency' => 'nullable|in:instant,daily,weekly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $search = SavedSearch::create([
            'customer_id' => Auth::id(),
            'name' => $request->name,
            'filters' => $request->filters,
            'frequency' => $request->frequency ?? 'instant',
        ]);

        $matchCount = $this->countMatches($search->filters);

        return response()->json([
            'error' => false,
            'message' => 'Búsqueda guardada correctamente',
            'data' => [
                'saved_search' => $search,
                'matching_properties_count' => $matchCount,
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $search = SavedSearch::where('customer_id', Auth::id())->find($id);

        if (! $search) {
            return response()->json([
                'error' => true,
                'message' => 'Búsqueda no encontrada',
            ], 404);
        }

        $matchCount = $this->countMatches($search->filters);

        return response()->json([
            'error' => false,
            'data' => [
                'saved_search' => $search,
                'matching_properties_count' => $matchCount,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $search = SavedSearch::where('customer_id', Auth::id())->find($id);

        if (! $search) {
            return response()->json([
                'error' => true,
                'message' => 'Búsqueda no encontrada',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:150',
            'filters' => 'sometimes|array',
            'frequency' => 'nullable|in:instant,daily,weekly',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $search->update($request->only(['name', 'filters', 'frequency', 'is_active']));

        return response()->json([
            'error' => false,
            'message' => 'Búsqueda actualizada',
            'data' => $search,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $search = SavedSearch::where('customer_id', Auth::id())->find($id);

        if (! $search) {
            return response()->json([
                'error' => true,
                'message' => 'Búsqueda no encontrada',
            ], 404);
        }

        $search->delete();

        return response()->json([
            'error' => false,
            'message' => 'Búsqueda eliminada',
        ]);
    }

    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filters' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $count = $this->countMatches($request->filters);

        return response()->json([
            'error' => false,
            'data' => ['matching_properties_count' => $count],
        ]);
    }

    private function countMatches(array $filters): int
    {
        $query = Property::query()->where('status', 1)->where('request_status', 'approved');

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }
        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if (isset($filters['propery_type'])) {
            $query->where('propery_type', $filters['propery_type']);
        }
        if (! empty($filters['min_bedrooms'])) {
            $query->where('bedrooms', '>=', $filters['min_bedrooms']);
        }
        if (! empty($filters['max_bedrooms'])) {
            $query->where('bedrooms', '<=', $filters['max_bedrooms']);
        }
        if (! empty($filters['min_bathrooms'])) {
            $query->where('bathrooms', '>=', $filters['min_bathrooms']);
        }
        if (! empty($filters['min_build_area'])) {
            $query->where('build_area', '>=', $filters['min_build_area']);
        }
        if (! empty($filters['max_build_area'])) {
            $query->where('build_area', '<=', $filters['max_build_area']);
        }

        return $query->count();
    }
}
