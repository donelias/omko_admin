<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Feature;
use App\Models\Translation;
use Illuminate\Http\Request;
use App\Models\PackageFeature;
use App\Services\HelperService;
use App\Services\ResponseService;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Validator;

class PackageFeatureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!has_permissions('read', 'package-feature')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        return view('packages.features.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!has_permissions('create', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Feature::create($request->only('name'));
            ResponseService::successResponse(trans("Data Created Successfully"));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,'Error in Package Feature Store Controller',trans("Something Went Wrong"));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (!has_permissions('read', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $search = request('search');

        $sql = Feature::when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('name', 'LIKE', "%$search%");
                });
            });


        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            $row = (object)$row;
            $operate = BootstrapTableService::button('fa fa-language', route('package-features.translated-names', $row->id), ['btn-primary'], ['title' => __('Manage Translations')]);

            $tempRow = $row->toArray();
            $tempRow['edit_status_url'] = route('package-features.status-update');
            $tempRow['name'] = trans($tempRow['name']);
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (!has_permissions('update', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Feature::where('id',$id)->update($request->only('name'));
            ResponseService::successResponse(trans("Data Updated Successfully"));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,'Error in Package Feature Update Controller',trans("Something Went Wrong"));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (!has_permissions('delete', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Feature::where('id',$id)->delete();
            ResponseService::successResponse(trans("Data Deleted Successfully"));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,'Error in Package Feature Delete Controller',trans("Something Went Wrong"));
        }
    }

    /**
     * Update status of specified resource.
     */
    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            Feature::where('id',$request->id)->update(['status' => $request->status == 1 ? true : false]);
            ResponseService::successResponse(trans("Status Updated Successfully"));
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,'Error in Package Feature Update Controller',trans("Something Went Wrong"));
        }
    }

    public function translatedNames(string $id)
    {
        if (!has_permissions('read', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $feature = Feature::find($id);
        $languages = HelperService::getActiveLanguages();
        $featureTranslations = Translation::where('translatable_id', $id)->where('translatable_type', 'App\Models\Feature')->get();
        return view('packages.features.translated-names', compact('feature', 'languages', 'featureTranslations'));
    }

    public function updateTranslatedNames(Request $request)
    {
        if (!has_permissions('update', 'package-feature')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            $validator = Validator::make($request->all(), [
                'feature_id'                    => 'required|exists:features,id',
                'translations'                  => 'required|array',
                'translations.*.language_id'    => 'required|exists:languages,id',
                'translations.*.value'          => 'required|string',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            
            // Add Translations
            if(isset($request->translations) && !empty($request->translations)){
                $translationData = array();
                foreach($request->translations as $translation){
                    $translationData[] = array(
                        'id'                => $translation['id'] ?? null,
                        'translatable_id'   => $request->feature_id,
                        'translatable_type' => 'App\Models\Feature',
                        'key'               => 'name',
                        'value'             => $translation['value'],
                        'language_id'       => $translation['language_id'],
                    );
                }
                if(!empty($translationData)){
                    HelperService::storeTranslations($translationData);
                }
            }
            ResponseService::successResponse(trans("Data Updated Successfully"));
        }
        catch (Exception $e) {
            ResponseService::logErrorResponse($e,'Error in Package Feature Update Translated Names Controller',trans("Something Went Wrong"));
        }
    }
}
