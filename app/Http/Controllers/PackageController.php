<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Package;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\Usertokens;
use App\Models\UserPackage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Notifications;
use App\Models\PackageFeature;
use App\Services\HelperService;
use App\Models\UserPackageLimit;
use App\Services\ResponseService;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use App\Models\OldUserPurchasedPackage;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'package')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
        $featuresList = HelperService::getFeatureList();

        $featureMapData = array();
        foreach ($featuresList as $key => $feature) {
            $featureMapData[$feature->name] = $feature->id;
        }
        $languages = HelperService::getActiveLanguages();

        return view('packages.index', compact('currency_symbol','featuresList','featureMapData','languages'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!has_permissions('create', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'name'                      => 'required|string|max:30',
                'ios_product_id'            => 'nullable|string|max:255|unique:packages,ios_product_id',
                'duration'                  => 'required|integer|min:1|max:730',
                'package_type'              => 'required|in:free,paid',
                'purchase_type'             => 'required|in:unlimited,one_time',
                'price'                     => 'nullable|required_if:package_type,paid|numeric|between:0,99999999.99',
                'feature_data'              => 'required|array',
                'feature_data.*.feature_id' => 'required',
                'feature_data.*.type'       => 'required',
                'feature_data.*.limit'      => 'nullable|required_if:feature_data.*.type,limited'
            ],
            [
                'duration.max' => trans('The duration must not exceed more than 730 days that is 2 years.'),
            ]
        );
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            try {
                DB::beginTransaction();

                // Create Package Data
                $packageData = $request->only('name','ios_product_id','duration','package_type','purchase_type');
                $packageData['price'] = $request->has('price') && !empty($request->price) ? round($request->price,2) : null;
                $packageData['duration'] = $request->duration * 24;
                $package = Package::create($packageData);

                // Assign Features to Package
                $packageFeatureData = array();
                foreach ($request->feature_data as $featureDataArray) {
                    $featureData = (object)$featureDataArray;
                    $packageFeatureData[] = array(
                        'package_id' => $package->id,
                        'feature_id' => (int)$featureData->feature_id,
                        'limit_type' => $featureData->type,
                        'limit' => $featureData->limit ?? null
                    );
                }

                PackageFeature::upsert($packageFeatureData,['package_id','feature_id'],['limit_type','limit']);

                // Add Translations
                if(isset($request->translations) && !empty($request->translations)){
                    $translationData = array();
                    foreach($request->translations as $translation){
                        $translationData[] = array(
                            'translatable_id'   => $package->id,
                            'translatable_type' => 'App\Models\Package',
                            'key'               => 'name',
                            'value'             => $translation['value'],
                            'language_id'       => $translation['language_id'],
                        );
                    }
                    if(!empty($translationData)){
                        HelperService::storeTranslations($translationData);
                    }
                }
                DB::commit();
                ResponseService::successResponse(trans("Data Created Successfully"));
            } catch (Exception $e) {
                DB::rollback();
                ResponseService::logErrorResponse($e,'Package Controller -> store method',trans("Something Went Wrong"));
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!has_permissions('read', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $sql = Package::with('package_features', 'translations');

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql->where('id', 'LIKE', "%$search%")->orwhere('name', 'LIKE', "%$search%")->orwhere('duration', 'LIKE', "%$search%");
        }

        $total = $sql->count();
        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;

        $priceSymbol = HelperService::getSettingData('currency_symbol') ?? '$';
        foreach ($res as $row) {
            $tempRow = $row->toArray();

            $operate = '';
            if (has_permissions('update', 'package')) {
                $operate .= BootstrapTableService::editButton('', true, null, null, $row->id);
            }
            if (has_permissions('delete', 'package')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('package.destroy',$row->id));
            }

            $tempRow['operate'] = $operate;
            if (has_permissions('update', 'package')) {
                $tempRow['edit_status_url'] = route('package.updatestatus');
            }
            $tempRow['duration'] = $row->duration / 24;
            $tempRow['price_symbol'] = $priceSymbol;
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * Update the specified resource in storage.
     *
     */
    public function update($id, Request $request)
    {

        if (!has_permissions('update', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $validator = Validator::make($request->all(), [
                'edit_id'           => 'required|exists:packages,id',
                'name'              => 'required|string|max:30',
                'ios_product_id'    => 'nullable|unique:packages,ios_product_id,'.$request->edit_id.'id',
                'duration'          => 'required|integer|min:1|max:730',
                'purchase_type'     => 'required|in:unlimited,one_time',
                'price'             => 'nullable',
            ],
            [
                'duration.max' => trans('The duration must not exceed more than 730 days that is 2 years.'),
            ]
        );
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            try{
                $requestData = $request->only('name','ios_product_id','purchase_type');
                $data = $requestData;
                $data['duration'] = $request->duration * 24;
                if($request->has('price') && !empty($request->price)){
                    $data = array_merge($data,array('price' => round($request->price,2)));
                }
                Package::where('id',$id)->update($data);

                // Add Translations
                 if(isset($request->translations) && !empty($request->translations)){
                    $translationData = array();
                    foreach($request->translations as $translation){
                        $translationData[] = array(
                            'id'                => $translation['id'],
                            'translatable_id'   => $id,
                            'translatable_type' => 'App\Models\Package',
                            'key'               => 'name',
                            'value'             => $translation['value'],
                            'language_id'       => $translation['language_id'],
                        );
                    }
                    if(!empty($translationData)){
                        HelperService::storeTranslations($translationData);
                    }
                }
                ResponseService::successResponse('Data Updated Successfully');

            } catch (Exception $e) {
                ResponseService::logErrorResponse($e,'Package Controller -> Update method',trans("Something Went Wrong"));
            }
        }
    }

    public function destroy($id){
        try {
            if (!has_permissions('delete', 'package')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }
            Package::where('id',$id)->delete();
            ResponseService::successResponse("Data Deleted Successfully");
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e,'Package Controller -> Destroy method',trans("Something Went Wrong"));
        }
    }

    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Package::where('id', $request->id)->update(['status' => $request->status]);
            $response['error'] = false;
            return response()->json($response);
        }
    }

    public function userPackageIndex(){
        if (!has_permissions('read', 'user_package')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        return view('packages.user_packages');
    }
    public function getUserPackageList(Request $request)
    {
        if (!has_permissions('read', 'user_package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');
        $search = $request->input('search');

        $sql = UserPackage::with('package','customer:id,name')
            ->when($request->has('search') && !empty($search),function($query) use($search){
                $query->where('id', 'LIKE', "%$search%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%$search%");
                    })->orWhereHas('package', function ($q1) use ($search) {
                        $q1->where('name', 'LIKE', "%$search%");
                    });
            });

        $total = $sql->count();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();
        $res = $sql->orderBy($sort, $order)->skip($offset)->take($limit)->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;
        foreach ($res as $row) {
            $tempRow = $row->toArray();
            $tempRow['subscription_status'] = $row->end_date >= now() ? 1 : 0;
            $tempRow['start_date'] = $row->start_date->format('d-m-Y H:i:s');
            $tempRow['end_date'] = $row->end_date->format('d-m-Y H:i:s');
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function assignPackageToUserIndex(){
        if (!has_permissions('read', 'assign_package')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        return view('packages.assign_package');
    }
    public function assignPackageToUser(Request $request){
        if (!has_permissions('update', 'assign_package')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'package_id' => 'required|exists:packages,id',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $package = Package::findOrFail($request->package_id);
            $customer = Customer::findOrFail($request->customer_id);
            $hasPurchasedPackage = UserPackage::where(['user_id' => $customer->id, 'package_id' => $package->id])->exists();
            if($package->purchase_type == 'one_time' && $hasPurchasedPackage){
                ResponseService::validationError("This package can only be purchased once");
            }

            // If user already has an active package and force assign is not confirmed yet, ask for confirmation
            if (!$request->boolean('force_assign')) {
                $hasActivePackage = UserPackage::where('user_id', $customer->id)->onlyActive()->exists();
                if ($hasActivePackage) {
                    return ResponseService::warningResponse(
                        'Selected user already has an active package. Do you want to assign anyway?',
                        ['confirm_required' => true]
                    );
                }
            }

            DB::beginTransaction();

            $userPackage = UserPackage::create([
                'package_id' => $package->id,
                'user_id'    => $customer->id,
                'start_date' => Carbon::now(),
                'end_date'   => $package->package_type == 'unlimited' ? null : Carbon::now()->addHours($package->duration),
            ]);

            PaymentTransaction::create([
                'user_id'        => $customer->id,
                'package_id'     => $package->id,
                'amount'         => $package->price ?? 0,
                'payment_gateway'=> null,
                'payment_type'   => 'manual',
                'payment_status' => 'success',
                'order_id'       => Str::uuid(),
                'transaction_id' => Str::uuid(),
            ]);

            $packageFeatures = PackageFeature::where(['package_id' => $package->id, 'limit_type' => 'limited'])->get();
            if (collect($packageFeatures)->isNotEmpty()) {
                $userPackageLimitData = [];
                foreach ($packageFeatures as $feature) {
                    $userPackageLimitData[] = [
                        'user_package_id'    => $userPackage->id,
                        'package_feature_id' => $feature->id,
                        'total_limit'        => $feature->limit,
                        'used_limit'         => 0,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }

                if (!empty($userPackageLimitData)) {
                    UserPackageLimit::insert($userPackageLimitData);
                }
            }

            $userFcmTokensDB = Usertokens::where('customer_id', $customer->id)->pluck('fcm_id');
            if(collect($userFcmTokensDB)->isNotEmpty()){
                $title = "New package assigned";
                $body = 'Package :- :name';

                $registrationIDs = array_filter($userFcmTokensDB->toArray());

                $fcmMsg = array(
                    'title' => $title,
                    'message' => $body,
                    "image" => null,
                    'type' => 'default',
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'replace' => [
                        'name' => $package->name
                    ]

                );
                send_push_notification($registrationIDs, $fcmMsg);

                Notifications::create([
                    'title' => 'New package assigned',
                    'message' => 'Package :- ' . $package->name,
                    'image' => '',
                    'type' => '2',
                    'send_type' => '0',
                    'customers_id' => $customer->id,
                ]);
            }

            DB::commit();
            ResponseService::successResponse('Package Assigned Successfully');
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::errorResponse(trans('Something Went Wrong'));
        }
    }

    public function selectPackages(Request $request)
    {
        if (!has_permissions('read', 'assign_package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $term = $request->input('q');
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 20);

        $query = Package::where('status', 1);
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%$term%")
                  ->orWhere('id', 'like', "%$term%");
            });
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get(['id', 'name']);

        $results = $items->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'text' => $pkg->getRawOriginal('name') . ' (#' . $pkg->id . ')',
            ];
        })->toArray();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => ($page * $perPage) < $total,
            ],
        ]);
    }

    public function selectCustomers(Request $request)
    {
        if (!has_permissions('read', 'assign_package')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }

        $term = $request->input('q');
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 20);

        $query = Customer::where('isActive', 1);
        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%$term%")
                  ->orWhere('email', 'like', "%$term%")
                  ->orWhere('id', 'like', "%$term%");
            });
        }

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get(['id', 'name', 'email']);

        $results = $items->map(function ($cust) {
            $label = trim($cust->getRawOriginal('name') . ' <' . ($cust->email ?? '') . '>');
            $label = rtrim($label, ' <>');
            return [
                'id' => $cust->id,
                'text' => $label . ' (#' . $cust->id . ')',
            ];
        })->toArray();

        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => ($page * $perPage) < $total,
            ],
        ]);
    }
}
