<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\Property;
use App\Models\Usertokens;
use Illuminate\Http\Request;
use App\Models\Notifications;
use App\Services\FileService;
use App\Services\ResponseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'notification')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $property_list = Property::where('status',1)->get();
            return view('notification.index', compact('property_list'));
        }
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!has_permissions('create', 'notification')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            try {
                DB::beginTransaction();
                $firebaseProjectId = system_setting('firebase_project_id');
                $firebaseServiceJsonFile = system_setting('firebase_service_json_file');
                if(empty($firebaseProjectId)){
                    ResponseService::errorRedirectResponse(route('notification.index'),'Firebase Project ID is Missing');
                }else if(empty($firebaseServiceJsonFile)){
                    ResponseService::errorRedirectResponse(route('notification.index'),'Firebase Service File is Missing');
                }else{
                    $request->validate([
                        'file'      => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
                        'type'      => 'required',
                        'send_type' => 'required',
                        'user_id'   => 'required_if:send_type,==,0',
                        'title'     => 'required',
                        'message'   => 'required',
                        'include_image' => 'nullable',
                    ],
                    [
                        'user_id.*' => trans('Select User From Table'),
                    ]);

                    $imageName = '';
                    $includeImage = $request->include_image ? 1 : 0;
                    if ($request->hasFile('file') && $includeImage == 1) {
                        $imageName = FileService::compressAndUpload($request->file('file'), config('global.NOTIFICATION_IMG_PATH'));
                    }

                    // Get Customer ids who is active and has notification activated
                    $customer_ids = Customer::where(['isActive' => '1','notification' => 1 ])->pluck('id');
                    // Start Query for user token according to customer ids
                    $userTokenQuery = Usertokens::whereIn('customer_id', $customer_ids);
                    if ($request->send_type == 1) {
                        $user_id = '';
                        $fcm_ids = $userTokenQuery->clone()->pluck('fcm_id');
                    } else {
                        $user_id = $request->user_id;
                        $fcm_ids = $userTokenQuery->clone()->where("customer_id", $user_id)->pluck('fcm_id');
                    }
                    $type = 0;
                    if (isset($request->property)) {
                        $type = 2;
                        $propertyId = $request->property;
                        $propertyData = Property::find($propertyId);
                        if(!$propertyData){
                            ResponseService::errorRedirectResponse(route('notification.index'),'Property Not Found');
                        }
                    } else {
                        $type = $request->type;
                    }
                    Notifications::create([
                        'title' => $request->title,
                        'message' => $request->message,
                        'image' => $imageName,
                        'type' => $type,
                        'send_type' => $request->send_type,
                        'customers_id' => $user_id,
                        'propertys_id' => isset($propertyId) ? $propertyId : 0
                    ]);

                    $img = null;
                    if($imageName != ''){
                        $img = FileService::getFileUrl(config('global.NOTIFICATION_IMG_PATH') . $imageName);
                    }else{
                        if($request->property){
                            $img = $propertyData->title_image;
                        }
                    }


                    //START :: Send Notification To Customer
                    if (collect($fcm_ids)->isNotEmpty()) {

                        $registrationIDs = array_filter($fcm_ids->toArray());

                        $fcmMsg = array(
                            'title' => $request->title,
                            'message' => $request->message,
                            'image' => $img,
                            'property_id' => !empty($propertyId) ? $propertyId : null,
                            'type' => 'default',
                            'body' => $request->message,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',

                        );
                        send_push_notification($registrationIDs, $fcmMsg);
                        //END ::  Send Notification To Customer
                    }
                    DB::commit();
                    ResponseService::successRedirectResponse('Message Send Successfully');
                }
            }catch (Exception $e) {
                DB::rollBack();
                ResponseService::errorRedirectResponse(route('notification.index'),$e->getMessage());
            }
        }
    }
    public function destroy(Request $request)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != "superadmin@gmail.com") {
                ResponseService::errorResponse(trans('This is not allowed in the Demo Version'));
            }
            if (!has_permissions('delete', 'notification')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }
            $id = $request->id;
            $notification = Notifications::where('id', $id)->first();
            FileService::delete(config('global.NOTIFICATION_IMG_PATH'), $notification->getRawOriginal('image'));
            $notification->delete();
            ResponseService::successResponse("Data Deleted Successfully");
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, "Notification Delete Error", "Something Went Wrong");
        }
    }
    public function notificationList(Request $request)
    {
        if (!has_permissions('read', 'notification')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');
        $sql = Notifications::where('id', '!=', 0);

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('message', 'LIKE', "%$search%");
        }

        $total = $sql->count();

        $sql = $sql->orderBy($sort, $order)->skip($offset)->take($limit);

        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;
        $operate = '';
        foreach ($res as $row) {
            $tempRow = $row->toArray();

            if (has_permissions('delete', 'notification')) {
                $operate = '<a data-id=' . $row->id . ' data-image="' . $row->image . '" class="btn icon btn-danger btn-sm rounded-pill mt-2 delete-data" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-dark" title="Delete"><i class="bi bi-trash"></i></a>';
            }
            $type = '';
            if ($row->type == 0) {
                $type = trans('General Notification');
            }
            if ($row->type == 1) {
                $type = trans('Inquiry Notification');
            }
            if ($row->type == 2) {
                $type = trans('Property Notification');
            }
            $tempRow['count'] = $count;

            $tempRow['type'] = $type;
            $tempRow['title'] = $row->title;
            $tempRow['send_type'] = ($row->send_type == 0) ? 'Selected' : 'All';
            $tempRow['created_at'] = $row->created_at->diffForHumans();
            $tempRow['operate'] = $operate;
            if($row->customerData){
                $tempRow['customer_data'] = $row->customerData->map(function($item){
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                    ];
                });
            }
            $rows[] = $tempRow;
            $count++;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function multiple_delete(Request $request)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != "superadmin@gmail.com") {
                ResponseService::errorResponse(trans('This is not allowed in the Demo Version'));
            }
            if (has_permissions('delete', 'notification')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            }
            $id = $request->id;
            $notifications = Notifications::whereIn('id', explode(',', $id))->get();
            foreach ($notifications as $notification) {
                FileService::delete(config('global.NOTIFICATION_IMG_PATH'), $notification->getRawOriginal('image'));
            }
            Notifications::whereIn('id', explode(',', $id))->delete();
            ResponseService::successResponse("Data Deleted Successfully");
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, "Notification Multiple Delete Error", "Something Went Wrong");
        }
    }
}
