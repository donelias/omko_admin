<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Setting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Projects;
use App\Models\parameter;
use App\Models\Usertokens;
use Illuminate\Support\Str;
use App\Models\ProjectPlans;
use App\Models\RejectReason;
use Illuminate\Http\Request;
use App\Models\Notifications;
use App\Services\FileService;
use App\Services\HelperService;
use App\Models\ProjectDocuments;
use App\Models\OutdoorFacilities;
use App\Services\ResponseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\BootstrapTableService;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!has_permissions('read', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::all();
        return view('project.index',compact('category'));
    }

    public function create(){
        if (!has_permissions('create', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::where('status', '1')->get();
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();
        $languages = HelperService::getActiveLanguages();
        $geminiEnabled = HelperService::getSettingData('gemini_ai_enabled') == '1';
        return view('project.create', compact('category', 'currency_symbol', 'languages', 'geminiEnabled'));
    }

    public function store(Request $request){
        if (!has_permissions('create', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $validator = Validator::make($request->all(), [
            'title'             => 'required',
            'description'       => 'required',
            'image'             => 'required|file|max:3000|mimes:jpeg,png,jpg,webp',
            'meta_title'        => 'nullable|max:255',
            'meta_image'        => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description'  => 'nullable|max:255',
            'meta_keywords'     => 'nullable|max:255',
            'category_id'       => 'required',
            'city'              => 'required',
            'state'             => 'required',
            'country'           => 'required',
            'video_link'        => ['nullable', 'url', function ($attribute, $value, $fail) {
                // Regular expression to validate YouTube URLs
                $youtubePattern = '/^(https?\:\/\/)?(www\.youtube\.com|youtu\.be)\/.+$/';

                if (!preg_match($youtubePattern, $value)) {
                    return $fail("The Video Link must be a valid YouTube URL.");
                }

                // Transform youtu.be short URL to full YouTube URL for validation
                if (strpos($value, 'youtu.be') !== false) {
                    $value = 'https://www.youtube.com/watch?v=' . substr(parse_url($value, PHP_URL_PATH), 1);
                }

                // Get the headers of the URL
                $headers = @get_headers($value);

                // Check if the URL is accessible
                if (!$headers || strpos($headers[0], '200') === false) {
                    return $fail("The Video Link must be accessible.");
                }
            }]
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;

            $project = new Projects();
            $project->title = $request->title;
            $project->slug_id = generateUniqueSlug($slugData, 4);
            $project->category_id = $request->category_id;
            $project->description = $request->description;
            $project->location = $request->address;
            $project->meta_title = $request->meta_title ?? null;
            $project->meta_description = $request->meta_description ?? null;
            $project->meta_keywords = $request->keywords ?? null;
            $project->added_by = null;
            $project->is_admin_listing = true;
            $project->request_status = 'approved';
            $project->status = 1;
            $project->country = $request->country;
            $project->state = $request->state;
            $project->city = $request->city;
            $project->latitude = $request->latitude;
            $project->longitude = $request->longitude;
            $project->video_link = $request->video_link;
            $project->type = $request->project_type;

            if ($request->hasFile('image')) {
                $path = config('global.PROJECT_TITLE_IMG_PATH');
                $project->image = FileService::compressAndUpload($request->file('image'), $path, true);
            }
            if ($request->hasFile('meta_image')) {
                $path = config('global.PROJECT_SEO_IMG_PATH');
                $project->meta_image = FileService::compressAndUpload($request->file('meta_image'), $path);
            }

            $project->save();

            if ($request->hasfile('gallery_images')) {
                $galleryImages = array();
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->file('gallery_images') as $file) {
                    $image = FileService::compressAndUpload($file, $path, true);
                    $galleryImages[] = array(
                        'project_id' => $project->id,
                        'name' => $image,
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($galleryImages)){
                    ProjectDocuments::insert($galleryImages);
                }
            }

            if ($request->hasfile('documents')) {
                $projectDocuments = array();
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->file('documents') as $file) {
                    $document = FileService::compressAndUpload($file, $path);
                    $projectDocuments[] = array(
                        'project_id' => $project->id,
                        'name' => $document,
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($projectDocuments)){
                    ProjectDocuments::insert($projectDocuments);
                }
            }

            if ($request->floor_data) {
                $projectPlan = array();
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->floor_data as $key => $planArray) {
                    $plan = (object)$planArray;
                    $document = FileService::compressAndUpload($plan->floor_image, $path, true);
                    $projectPlan[] = array(
                        'title' => $plan->title,
                        'project_id' => $project->id,
                        'document' => $document,
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }

                if(!empty($projectPlan)){
                    ProjectPlans::insert($projectPlan);
                }
            }

            // START ::Add Translations
            if(isset($request->translations) && !empty($request->translations)){
                $translationData = array();
                foreach($request->translations as $translation){
                    foreach($translation as $key => $value){
                        $translationData[] = array(
                            'translatable_id'   => $project->id,
                            'translatable_type' => 'App\Models\Projects',
                            'language_id'       => $value['language_id'],
                            'key'               => $key,
                            'value'             => $value['value'],
                        );
                    }
                }
                if(!empty($translationData)){
                    HelperService::storeTranslations($translationData);
                }
            }

            // END ::Add Translations

            DB::commit();
            ResponseService::successResponse("Data Created Successfully");
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse("Something Went Wrong");
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        if (!has_permissions('read', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');


        $sql = Projects::with('category')->with('gallary_images')->with('documents')->with('plans')->with('customer')->orderBy($sort, $order);

        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = $_GET['search'];
            $sql = $sql->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('location', 'LIKE', "%$search%")->orwhereHas('category', function ($query) use ($search) {
                $query->where('category', 'LIKE', "%$search%");
            })->orWhereHas('customer', function ($query) use ($search) {
                $query->where('name', 'LIKE', "%$search%")->orwhere('email', 'LIKE', "%$search%");
            });
        }

        if ($_GET['status'] != '' && isset($_GET['status'])) {
            $status = $_GET['status'];
            $sql = $sql->where('status', $status);
        }


        if ($_GET['category'] != '' && isset($_GET['category'])) {
            $category_id = $_GET['category'];
            $sql = $sql->where('category_id', $category_id);
        }

        if ($_GET['owner'] != '' && isset($_GET['owner'])) {
            $owner = $_GET['owner'];
            if($owner == 0){
                $sql = $sql->where('is_admin_listing', 1);
            }else{
                $sql = $sql->whereNot('is_admin_listing', 0);
            }
        }

        $total = $sql->count();

        if (isset($_GET['limit'])) {
            $sql->skip($offset)->take($limit);
        }

        $res = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $count = 1;
        $currency_symbol = Setting::where('type', 'currency_symbol')->pluck('data')->first();

        foreach ($res as $row) {
            $documentsButtonCustomClasses = ["btn","icon","btn-primary","btn-sm","rounded-pill","documents-btn"];
            $documentsButtonCustomAttributes = ["id" => $row->id, "title" => trans('Documents'), "data-toggle" => "modal", "data-bs-target" => "#documentsModal", "data-bs-toggle" => "modal"];
            $documentAction = BootstrapTableService::button('bi bi-eye-fill', '',$documentsButtonCustomClasses,$documentsButtonCustomAttributes);

            $operate = null;
            if (has_permissions('update', 'project')) {
                $requestStatusButtonCustomClasses = ["btn","icon","btn-warning","btn-sm","rounded-pill","request-status-btn"];
                $requestStatusButtonCustomAttributes = ["id" => $row->id, "title" => trans('Change Status'), "data-toggle" => "modal", "data-bs-target" => "#changeRequestStatusModal", "data-bs-toggle" => "modal"];
                $operate = BootstrapTableService::button('fa fa-exclamation-circle', '',$requestStatusButtonCustomClasses,$requestStatusButtonCustomAttributes);
                $operate .= BootstrapTableService::editButton(route('project.edit', $row->id), false);
            }
            if (has_permissions('delete', 'project')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('project.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['owner_name'] = $row->is_admin_listing == true ? "Admin" : $row->customer->name;
            if($row->is_admin_listing == true && $row->request_status == "approved"){
                $tempRow['edit_status'] = $row->status;
                $tempRow['edit_status_url'] = 'updateProjectStatus';
            }else{
                $tempRow['edit_status'] = null;
                $tempRow['edit_status_url'] = null;
            }

            $tempRow['price'] = $currency_symbol . '' . $row->price . '/' . (!empty($row->rentduration) ? $row->rentduration : 'Month');
            $tempRow['raw_document_action'] = $documentAction;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
            $count++;
        }


        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id){
        if (!has_permissions('update', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $project = Projects::where('id',$id)->with([
            'plans:id,title,project_id,document',
            'gallary_images' => function($query) {
                $query->select('id', 'project_id', 'name', 'type');
            },
            'documents' => function($query) {
                $query->select('id', 'project_id', 'name', 'type');
            },
            'translations'
        ])->first();
        $category = Category::where('status', '1')->get();
        $languages = HelperService::getActiveLanguages();
        $geminiEnabled = HelperService::getSettingData('gemini_ai_enabled') == '1';
        return view('project.edit',compact('project','category','languages', 'geminiEnabled'));
    }

    public function update($id,Request $request){
        if (!has_permissions('create', 'project')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $validator = Validator::make($request->all(), [
            'title'             => 'required',
            'description'       => 'required',
            'image'             => 'nullable|file|max:3000|mimes:jpeg,png,jpg,webp',
            'category_id'       => 'required',
            'meta_title'        => 'nullable|max:255',
            'meta_image'        => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120',
            'meta_description'  => 'nullable|max:255',
            'meta_keywords'     => 'nullable|max:255',
            'city'              => 'required',
            'state'             => 'required',
            'country'           => 'required',
            'video_link'        => ['nullable', 'url', function ($attribute, $value, $fail) {
                // Regular expression to validate YouTube URLs
                $youtubePattern = '/^(https?\:\/\/)?(www\.youtube\.com|youtu\.be)\/.+$/';

                if (!preg_match($youtubePattern, $value)) {
                    return $fail("The Video Link must be a valid YouTube URL.");
                }

                // Transform youtu.be short URL to full YouTube URL for validation
                if (strpos($value, 'youtu.be') !== false) {
                    $value = 'https://www.youtube.com/watch?v=' . substr(parse_url($value, PHP_URL_PATH), 1);
                }

                // Get the headers of the URL
                $headers = @get_headers($value);

                // Check if the URL is accessible
                if (!$headers || strpos($headers[0], '200') === false) {
                    return $fail("The Video Link must be accessible.");
                }
            }]
        ]);
        if ($validator->fails()) {
            ResponseService::errorResponse($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $slugData = (isset($request->slug_id) && !empty($request->slug_id)) ? $request->slug_id : $request->title;

            $project = Projects::find($id);
            $project->title = $request->title;
            $project->slug_id = generateUniqueSlug($slugData, 4,null,$id);
            $project->category_id = $request->category_id;
            $project->description = $request->description;
            $project->location = $request->address;
            $project->meta_title = $request->meta_title ?? null;
            $project->meta_description = $request->meta_description ?? null;
            $project->meta_keywords = $request->keywords ?? null;
            $project->country = $request->country;
            $project->state = $request->state;
            $project->city = $request->city;
            $project->latitude = $request->latitude;
            $project->longitude = $request->longitude;
            $project->video_link = $request->video_link;
            $project->type = $request->project_type;
            if($project->is_admin_listing == false){
                if(isset($request->edit_reason) && !empty($request->edit_reason)){
                    $project->edit_reason = $request->edit_reason;
                }else{
                    ResponseService::validationError('Edit Reason is required');
                }
            }
            if ($request->hasFile('image')) {
                $path = config('global.PROJECT_TITLE_IMG_PATH');
                $rawImage = $project->getRawOriginal('image');
                $project->image = FileService::compressAndReplace($request->file('image'), $path, $rawImage, true);
            }
            if ($request->hasFile('meta_image')) {
                $path = config('global.PROJECT_SEO_IMG_PATH');
                $rawImage = $project->getRawOriginal('meta_image');
                $project->meta_image = FileService::compressAndReplace($request->file('meta_image'), $path, $rawImage);
            }

            $project->save();

            if ($request->hasfile('gallery_images')) {
                $galleryImages = array();
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->file('gallery_images') as $file) {
                    $image = FileService::compressAndUpload($file, $path, true);
                    $galleryImages[] = array(
                        'project_id' => $project->id,
                        'name' => $image,
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($galleryImages)){
                    ProjectDocuments::insert($galleryImages);
                }
            }

            if ($request->hasfile('documents')) {
                $path = config('global.PROJECT_DOCUMENT_PATH');
                $projectDocuments = array();
                foreach ($request->file('documents') as $file) {
                    $document = FileService::compressAndUpload($file, $path);
                    $projectDocuments[] = array(
                        'project_id' => $project->id,
                        'name' => $document,
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    );
                }
                if(!empty($projectDocuments)){
                    ProjectDocuments::insert($projectDocuments);
                }
            }

            if ($request->floor_data) {
                $path = config('global.PROJECT_DOCUMENT_PATH');
                foreach ($request->floor_data as $key => $planArray) {
                    $plan = (object)$planArray;
                    if(!empty($plan->floor_image)){
                        $document = FileService::compressAndUpload($plan->floor_image, $path, true);
                        ProjectPlans::updateOrCreate(['id' => $plan->id],['title' => $plan->title, 'project_id' => $project->id, 'document' => $document]);
                    }else{
                        ProjectPlans::updateOrCreate(['id' => $plan->id],['title' => $plan->title, 'project_id' => $project->id]);
                    }
                }
            }

            // START ::Add Translations
            if(isset($request->translations) && !empty($request->translations)){
                $translationData = array();
                foreach($request->translations as $translation){
                    foreach($translation as $key => $value){
                        $translationData[] = array(
                            'id'                => $value['id'] ?? null,
                            'translatable_id'   => $project->id,
                            'translatable_type' => 'App\Models\Projects',
                            'language_id'       => $value['language_id'],
                            'key'               => $key,
                            'value'             => $value['value'],
                        );
                    }
                }
                if(!empty($translationData)){
                    HelperService::storeTranslations($translationData);
                }
            }

            // END ::Add Translations

            DB::commit();
            ResponseService::successResponse("Data Updated Successfully");
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse("Something Went Wrong");
        }
    }

    public function destroy($id){
        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            DB::beginTransaction();
            $project = Projects::find($id);

            DB::commit();
            if ($project->delete()) {
                ResponseService::successResponse("Data Deleted Successfully");
            }else{
                ResponseService::errorResponse("Something Went Wrong");
            }
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::errorResponse("Something Went Wrong");
        }
    }

    public function updateStatus(Request $request)
    {
        if (!has_permissions('update', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            Projects::where('id', $request->id)->update(['status' => $request->status]);
            $project = Projects::with('customer')->find($request->id);

            // if ($project->customer) {
            //     // Send mail for project status
            //     try {
            //         $projectData = Projects::where('id',$request->id)->select('id','title','status','added_by')->with('customer:id,name,email')->where('is_admin_listing',false)->firstOrFail();

            //         if(!empty($projectData->customer->email)){
            //             // Get Data of email type
            //             $emailTypeData = HelperService::getEmailTemplatesTypes("project_status");

            //             // Email Template
            //             $projectStatusTemplateData = system_setting($emailTypeData['type']);
            //             $appName = env("APP_NAME") ?? "eBroker";
            //             $variables = array(
            //                 'app_name' => $appName,
            //                 'user_name' => $projectData->customer->name,
            //                 'project_name' => $projectData->title,
            //                 'status' => $request->status == 1 ? 'Enabled' : 'Disabled',
            //                 'email' => $projectData->customer->email,
            //             );
            //             if(empty($projectStatusTemplateData)){
            //                 $projectStatusTemplateData = "Your Project :- ".$variables['projectName']." is ".$variables['status'];
            //             }
            //             $projectStatusTemplate = HelperService::replaceEmailVariables($projectStatusTemplateData,$variables);

            //             $data = array(
            //                 'email_template' => $projectStatusTemplate,
            //                 'email' => $projectData->customer->email,
            //                 'title' => $emailTypeData['title'],
            //             );
            //             HelperService::sendMail($data);
            //         }


            //     } catch (Exception $e) {
            //         Log::error("Something Went Wrong in Project Status Update Mail Sending");
            //     }
            // }


            /** Send Notification */
            // $fcm_ids = array();
            // if ($project->customer->isActive == 1 && $project->customer->notification == 1) {
            //     $user_token = Usertokens::where('customer_id', $project->customer->id)->pluck('fcm_id')->toArray();
            // }

            // $fcm_ids[] = $user_token;

            // $msg = "";
            // if (!empty($fcm_ids)) {
            //     $msg = $project->status == 1 ? 'Activate now by Administrator ' : 'Deactivated now by Administrator ';
            //     $registrationIDs = $fcm_ids[0];

            //     $fcmMsg = array(
            //         'title' =>  $project->title . 'Project Updated',
            //         'message' => 'Your Project Post ' . $msg,
            //         'type' => 'project_inquiry',
            //         'body' => 'Your Project Post ' . $msg,
            //         'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            //         'sound' => 'default',
            //         'id' => (string)$project->id,
            //     );
            //     send_push_notification($registrationIDs, $fcmMsg);
            // }
            // //END ::  Send Notification To Customer

            // Notifications::create([
            //     'title' => $project->title . 'Project Updated',
            //     'message' => 'Your Project Post ' . $msg,
            //     'image' => '',
            //     'type' => '1',
            //     'send_type' => '0',
            //     'customers_id' => $project->customer->id,
            //     'projects_id' => $project->id
            // ]);

            // $response['error'] = false;
            ResponseService::successResponse($request->status ? "Project Activated Successfully" : "Project Deactivated Successfully");
        }
    }
    public function generateAndCheckSlug(Request $request){
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        // Generate the slug or throw exception
        try {
            $title = $request->title;
            $id = $request->has('id') && !empty($request->id) ? $request->id : null;
            if($id){
                $slug = generateUniqueSlug($title,4,null,$id);
            }else{
                $slug = generateUniqueSlug($title,4);
            }
            ResponseService::successResponse("",$slug);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, "Project Slug Generation Error", "Something Went Wrong");
        }
    }

    public function removeGalleryImage(Request $request)
    {

        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;

            $getImage = ProjectDocuments::where('id', $id)->first();


            $image = $getImage->getRawOriginal('name');
            $path = config('global.PROJECT_DOCUMENT_PATH');
            if (ProjectDocuments::where('id', $id)->delete()) {
                FileService::delete($path, $image);
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }
            return response()->json($response);
        }
    }
    public function removeDocument(Request $request)
    {

        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            $id = $request->id;

            $getDocument = ProjectDocuments::where('id', $id)->first();


            $file = $getDocument->getRawOriginal('name');
            $path = config('global.PROJECT_DOCUMENT_PATH');
            if (ProjectDocuments::where('id', $id)->delete()) {
                FileService::delete($path, $file);
                $response['error'] = false;
            } else {
                $response['error'] = true;
            }
            return response()->json($response);
        }
    }

    public function removeFloorPlan($id){
        if (!has_permissions('delete', 'project')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        } else {
            try {
                $getDocument = ProjectPlans::where('id', $id)->first();

                $file = $getDocument->getRawOriginal('document');
                $path = config('global.PROJECT_DOCUMENT_PATH');
                if (ProjectPlans::where('id', $id)->delete()) {
                    FileService::delete($path, $file);
                    ResponseService::successResponse("Data Deleted Sucessfully");
                } else {
                    ResponseService::errorResponse("Something Went Wrong");
                }
            } catch (Exception $e) {
                ResponseService::errorResponse("Something Went Wrong");
            }
        }
    }


    public function updateRequestStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_status' => 'required|in:approved,rejected',
            'reject_reason' => 'required_if:request_status,rejected|max:300'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            if (!has_permissions('update', 'project')) {
                ResponseService::errorResponse(PERMISSION_ERROR_MSG);
            } else {
                if($request->request_status == "rejected"){
                    RejectReason::create(array(
                        'project_id' => $request->id,
                        'reason' => $request->reject_reason
                    ));
                }
                Projects::where('id', $request->id)->update(['request_status' => $request->request_status, 'status' => 0]);
                DB::commit();

                // Send mail for project status
                try {
                    $projectData = Projects::where('id',$request->id)->select('id','title','request_status','added_by')->with('customer:id,name,email')->firstOrFail();
                    if(!empty($projectData->customer->email)){
                        // Get Data of email type
                        $emailTypeData = HelperService::getEmailTemplatesTypes("project_status");

                        // Email Template
                        $projectStatusTemplateData = system_setting($emailTypeData['type']);
                        $appName = env("APP_NAME") ?? "eBroker";
                        $variables = array(
                            'app_name' => $appName,
                            'user_name' => $projectData->customer->name,
                            'project_name' => $projectData->title,
                            'status' => $request->request_status,
                            'reject_reason' => $request->request_status == 'rejected' ? $request->reject_reason : null,
                            'email' => $projectData->customer->email
                        );
                        if(empty($projectStatusTemplateData)){
                            $projectStatusTemplateData = "Project Status have been changed";
                        }
                        $projectStatusTemplate = HelperService::replaceEmailVariables($projectStatusTemplateData,$variables);

                        $data = array(
                            'email_template' => $projectStatusTemplate,
                            'email' => $projectData->customer->email,
                            'title' => $emailTypeData['title'],
                        );
                        HelperService::sendMail($data);
                    }
                } catch (Exception $e) {
                    Log::error("Something Went Wrong in project Status Update Mail Sending");
                }



                // Send Notification
                $project = Projects::with('customer:id,name,isActive,notification')->select('id','title','request_status','added_by')->find($request->id);
                $fcm_ids = array();
                if ($project->customer->isActive == 1 && $project->customer->notification == 1) {
                    $user_token = Usertokens::where('customer_id', $project->customer->id)->pluck('fcm_id')->toArray();
                }

                $fcm_ids[] = $user_token ?? array();

                $msg = "";
                if (!empty($fcm_ids)) {
                    $title = 'Project updated :- :project_name';
                    $msg = $project->request_status == 'approved' ? 'Your project post approved by administrator' : 'Your project post rejected by administrator';
                    $registrationIDs = $fcm_ids[0];

                    $fcmMsg = array(
                        'title' =>  $title,
                        'message' => $msg,
                        'type' => 'project_inquiry',
                        'body' => $msg,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'sound' => 'default',
                        'id' => (string)$project->id,
                        'replace' => [
                            'project_name' => $project->title
                        ]

                    );
                    send_push_notification($registrationIDs, $fcmMsg);
                }
                //END ::  Send Notification To Customer

                $notificationMsg = $project->request_status == 'approved' ? 'Your project post approved by administrator' : 'Your project post rejected by administrator';
                Notifications::create([
                    'title' => "Project Updated :- ".$project->name,
                    'message' => $notificationMsg,
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $project->customer->id,
                    'projects_id' => $project->id
                ]);
                ResponseService::successResponse("Data Updated Successfully");
            }
        } catch (Exception $e) {
            DB::rollback();
            ResponseService::logErrorResponse($e, "Update Request Status in Project", "Something Went Wrong");
        }
    }
}
