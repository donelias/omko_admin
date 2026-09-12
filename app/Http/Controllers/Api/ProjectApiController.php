<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\ProjectDocuments;
use App\Models\ProjectPlans;
use App\Models\Notifications;
use App\Models\Projects;
use App\Models\Property;
use App\Models\User;
use App\Models\Usertokens;
use App\Rules\VideoUrlRule;
use App\Services\ApiResponseService;
use App\Services\AuditLogService;
use App\Services\BulkProjectUnitImportService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ProjectUnitSyncService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProjectApiController extends Controller
{
    private ProjectUnitSyncService $projectUnitSyncService;

    public function __construct(ProjectUnitSyncService $projectUnitSyncService)
    {
        $this->projectUnitSyncService = $projectUnitSyncService;
    }

    public function post_project(Request $request)
    {
        $videoRules = [
            'video_type' => 'nullable|in:0,1,2',
            'video_link' => ['nullable', 'required_if:video_type,1,2', new VideoUrlRule($request->video_type)],
            'custom_video' => 'nullable|file|mimes:mp4,webm,ogg|max:20480|required_if:video_type,0',
        ];

        if ($request->has('id')) {
            $validator = Validator::make($request->all(), array_merge([
                'title' => 'required',
            ], $videoRules), [], [
                'custom_video.max' => 'File size exceeds the :max limit. Please upload a smaller video.',
            ]);
        } else {
            $validator = Validator::make($request->all(), array_merge([
                'title' => 'required',
                'description' => 'required',
                'image' => 'required|file|max:3000|mimes:jpeg,png,jpg,webp',
                'category_id' => 'required',
                'city' => 'required',
                'state' => 'required',
                'country' => 'required',
                'translations.*.title.translation_id' => 'nullable|exists:translations,id',
                'translations.*.title.language_id' => 'nullable|exists:languages,id',
                'translations.*.title.value' => 'nullable',
                'translations.*.description.translation_id' => 'nullable|exists:translations,id',
                'translations.*.description.language_id' => 'nullable|exists:languages,id',
                'translations.*.description.value' => 'nullable',
                'is_draft' => 'nullable|in:true,false',
            ], $videoRules),[
                'custom_video.max' => 'File size exceeds the :max limit. Please upload a smaller video.',
                'custom_video.*.max' => 'File size exceeds the :max limit. Please upload a smaller video.',
            ]);
        }
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();
            $isPayAsYouGo = false;
            $isDraft = false;

            if ($request->boolean('is_draft')) {
                $isDraft = true;   
            }

            if (! $request->id) {
                // Check if limit is available without failing automatically
                $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), true, true, $request->user_active_role);
                if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
                    $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), false, true);
                    $isPayAsYouGo = ($limitResult === 'pay_as_you_go');
                } else {
                    // If no limit or package is available, implicitly save as draft!
                    $isDraft = true;
                    $isPayAsYouGo = false;
                }
            }
            $slugData = (isset($request->slug_id) && ! empty($request->slug_id)) ? $request->slug_id : $request->title;

            $currentUserId = Auth::user()->id;
            $watermarkAgentId = $request->user_active_role === 'agent' ? $currentUserId : null;
            if (! (isset($request->id))) {
                $project = new Projects;

                if ($isDraft) {
                    $project->status = 0;
                    $project->request_status = 'draft';
                } else {
                    // Check the auto approve and verified user status and make project auto enable or disable
                    $autoApproveStatus = HelperService::getAutoApproveStatus($currentUserId, $request->user_active_role);
                    if ($autoApproveStatus) {
                        $project->status = 1;
                        $project->request_status = 'approved';
                        if ($isPayAsYouGo) {
                            $project->expiry_date = Carbon::now()->addDays(30);
                        } elseif (! $isDraft) {
                            $project->expiry_date = HelperService::calculateExpirationDate($currentUserId);
                        }
                    } else {
                        $project->status = 0;
                        $project->request_status = 'pending';
                    }
                }

                // if ($autoApproveStatus) {

                // }

                $project->is_premium = isset($request->is_premium) ? ($request->is_premium == 'true' ? 1 : 0) : 0;
            } else {
                $project = Projects::where('added_by', $currentUserId)->find($request->id);
                if (! $project) {
                    $response['error'] = false;
                    $response['message'] = trans('Project Not Found');

                    return response()->json($response);
                }
                $wasDraft = ($project->getRawOriginal('request_status') === 'draft');

                if ($wasDraft) {
                    $graduation = $this->graduateDraft($project, $request->user_active_role);
                    if ($graduation['success']) {
                        $isPayAsYouGo = $graduation['is_pay_as_you_go'];
                        $autoApproveStatus = $graduation['auto_approve'];
                    } else {
                        // Stay as draft
                    }
                } else {
                    $autoApproveStatus = HelperService::getAutoApproveStatus($currentUserId, $request->user_active_role);
                    if (! $autoApproveStatus) {
                        if (HelperService::getSettingData('auto_approve_edited_listings') == 0) {
                            $project->request_status = 'pending';
                        }
                    }
                }
            }

            // is_premium check
            $showPremiumToggle = system_setting('show_premium_toggle');
            if ($showPremiumToggle == 1) {
                $project->is_premium = isset($request->is_premium) ? ($request->is_premium) : 0;
            } else {
                $project->is_premium = 0;
            }

            if ($request->category_id) {
                $project->category_id = $request->category_id;
            }
            if ($request->description) {
                $project->description = $request->description;
            }
            if ($request->location) {
                $project->location = $request->location;
            }
            // Meta details
            $project->meta_title = isset($request->meta_title) && ! empty($request->meta_title) ? $request->meta_title : null;
            $project->meta_description = isset($request->meta_description) && ! empty($request->meta_description) ? $request->meta_description : null;
            $project->meta_keywords = isset($request->meta_keywords) && ! empty($request->meta_keywords) ? $request->meta_keywords : null;

            $project->added_by = $currentUserId;
            // role_context auto-set by HasRoleContext trait on creation
            if ($request->country) {
                $project->country = $request->country;
            }
            if ($request->state) {
                $project->state = $request->state;
            }
            if ($request->city) {
                $project->city = $request->city;
            }
            if ($request->latitude) {
                $project->latitude = $request->latitude;
            }
            if ($request->longitude) {
                $project->longitude = $request->longitude;
            }
            // Video Logic
            if ($request->has('remove_video') && $request->remove_video == 1) {
                if ($project->video_type == Projects::VIDEO_CUSTOM && ! empty($project->getRawOriginal('video_link'))) {
                    FileService::delete(config('global.PROJECT_VIDEO_PATH'), $project->getRawOriginal('video_link'));
                }
                $project->video_type = null;
                $project->video_link = null;
            } else {
                $videoType = $request->video_type;
                $directUploadEnabled = HelperService::getSettingData('show_direct_video_upload');

                if ($videoType !== null && (int) $videoType === Projects::VIDEO_CUSTOM && (int) $directUploadEnabled !== 1) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Direct video upload is currently disabled by admin.',
                    ]);
                }

                $project->video_type = $videoType;
                if ($videoType !== null && (int) $videoType === Projects::VIDEO_CUSTOM && $request->hasFile('custom_video')) {
                    $file = $request->file('custom_video');
                    if ($file instanceof UploadedFile) {
                        $path = config('global.PROJECT_VIDEO_PATH');
                        if (isset($request->id) && $project->getRawOriginal('video_link')) {
                            $project->video_link = FileService::compressAndReplace($file, $path, $project->getRawOriginal('video_link'));
                        } else {
                            $project->video_link = FileService::compressAndUpload($file, $path);
                        }
                    } else {
                        Log::error('custom_video is not an instance of UploadedFile', ['value' => $file]);
                    }
                } elseif ($videoType !== null && in_array($videoType, [1, 2])) {
                    $project->video_link = $request->video_link;
                }
            }
            if ($request->type) {
                $project->type = $request->type;
            }
            if ($request->id) {
                if ($project->title !== $request->title) {
                    $title = ! empty($request->title) ? $request->title : $project->title;
                    $project->title = $title;
                } else {
                    $title = $request->title;
                    $project->title = $title;
                }
                $project->slug_id = generateUniqueSlug($slugData, 4, null, $request->id);
                if ($request->hasFile('image')) {
                    $path = config('global.PROJECT_TITLE_IMG_PATH');
                    $rawImage = $project->getRawOriginal('image');
                    $project->image = FileService::compressAndReplace($request->file('image'), $path, $rawImage, true, $watermarkAgentId);
                }

                if ($request->has('remove_meta_image') && $request->remove_meta_image == 1) {
                    if (! empty($project->meta_image)) {
                        $file = $project->getRawOriginal('meta_image');
                        FileService::delete(config('global.PROJECT_SEO_IMG_PATH'), $file);
                    }
                    $project->meta_image = null;
                }

                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROJECT_SEO_IMG_PATH');
                    $rawImage = $project->getRawOriginal('meta_image');
                    $project->meta_image = FileService::compressAndReplace($request->file('meta_image'), $path, $rawImage);
                }
            } else {
                $project->title = $request->title;
                if ($request->hasFile('image')) {
                    $path = config('global.PROJECT_TITLE_IMG_PATH');
                    $project->image = FileService::compressAndUpload($request->file('image'), $path, true, $watermarkAgentId);
                }
                if ($request->hasFile('meta_image')) {
                    $path = config('global.PROJECT_SEO_IMG_PATH');
                    $project->meta_image = FileService::compressAndUpload($request->file('meta_image'), $path);
                }
                $title = $request->title;
                $project->slug_id = generateUniqueSlug($slugData, 4);
            }

            $project->save();

            // Link payment transaction to project (pay-as-you-go only, new projects)
            if ($isPayAsYouGo && HelperService::$lastConsumedPaymentTransactionId) {
                PaymentTransaction::where('id', HelperService::$lastConsumedPaymentTransactionId)
                    ->update(['project_id' => $project->id]);
            }

            if ($request->remove_gallery_images) {
                $removeGalleryImagesIds = explode(',', $request->remove_gallery_images);
                $galleryImagesQuery = ProjectDocuments::whereIn('id', $removeGalleryImagesIds);
                $galleryImagesData = $galleryImagesQuery->get();
                if (collect($galleryImagesData)->isNotEmpty()) {
                    foreach ($galleryImagesData as $row) {
                        $file = $row->getRawOriginal('name');
                        $path = config('global.PROJECT_DOCUMENT_PATH');
                        FileService::delete($path, $file);
                    }
                    $galleryImagesQuery->delete();
                }
            }

            if ($request->remove_documents) {
                $removeDocumentsIds = explode(',', $request->remove_documents);
                $documentsQuery = ProjectDocuments::whereIn('id', $removeDocumentsIds);
                $documentsData = $documentsQuery->get();
                if (collect($documentsData)->isNotEmpty()) {
                    foreach ($documentsData as $row) {
                        $file = $row->getRawOriginal('name');
                        $path = config('global.PROJECT_DOCUMENT_PATH');
                        FileService::delete($path, $file);
                    }
                    $documentsQuery->delete();
                }
            }

            if ($request->hasfile('gallery_images')) {
                $galleryImagesData = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImagesData[] = [
                        'project_id' => $project->id,
                        'name' => FileService::compressAndUpload($file, config('global.PROJECT_DOCUMENT_PATH'), true, $watermarkAgentId),
                        'type' => 'image',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (collect($galleryImagesData)->isNotEmpty()) {
                    ProjectDocuments::insert($galleryImagesData);
                }
            }

            if ($request->hasfile('documents')) {
                $documentsData = [];
                foreach ($request->file('documents') as $file) {
                    $documentsData[] = [
                        'project_id' => $project->id,
                        'name' => FileService::compressAndUpload($file, config('global.PROJECT_DOCUMENT_PATH')),
                        'type' => 'doc',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (collect($documentsData)->isNotEmpty()) {
                    ProjectDocuments::insert($documentsData);
                }
            }

            if (! empty($request->plans)) {

                $path = config('global.PROJECT_DOCUMENT_PATH');
                $planIds = collect($request->plans)->pluck('id')->filter()->all();

                // Preload existing plans in one query
                $existingPlans = ProjectPlans::whereIn('id', $planIds)->get()->keyBy('id');

                foreach ($request->plans as $planData) {

                    // Use existing plan or new instance
                    $projectPlan = $existingPlans->get($planData['id'] ?? null) ?? new ProjectPlans;

                    // Handle document upload if present
                    if (! empty($planData['document'])) {
                        $oldFile = $projectPlan->getRawOriginal('document');
                        $projectPlan->document = FileService::compressAndReplace($planData['document'], $path, $oldFile, true, $watermarkAgentId);
                    }

                    // Fill common fields
                    $projectPlan->fill([
                        'title' => $planData['title'] ?? '',
                        'project_id' => $project->id,
                    ]);

                    $projectPlan->save();
                }
            }

            if (! empty($request->remove_plans)) {
                $removePlanIds = array_filter(explode(',', $request->remove_plans));
                if (! empty($removePlanIds)) {
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    // Fetch all plans in one query
                    $plans = ProjectPlans::whereIn('id', $removePlanIds)->get();
                    // Delete associated files
                    foreach ($plans as $plan) {
                        $file = $plan->getRawOriginal('document');
                        if ($file) {
                            FileService::delete($path, $file);
                        }
                    }
                    // Delete all plans from DB in a single query
                    ProjectPlans::whereIn('id', $removePlanIds)->delete();
                }
            }

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = [
                            'id' => $value['translation_id'] ?? null,
                            'translatable_id' => $project->id,
                            'translatable_type' => 'App\Models\Projects',
                            'language_id' => $value['language_id'],
                            'key' => $key,
                            'value' => $value['value'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }
            $result = Projects::with('customer')->with('gallary_images')->with('documents')->with('plans')->with('category:id,category,image')->where('id', $project->id)->first();

            // $projectData = new CustomerResource($result, ['is_agent','is_user_verified', 'is_agent_verified', 'become_agent_status', 'agent_verification_status', 'user_verification_status']);

            DB::commit();
            $action = isset($request->id) ? 'updated' : 'created';
            $actionDesc = isset($request->id) ? 'updated' : 'created';
            AuditLogService::log('project', $project->id, $project->title, $action, "Project '{$project->title}' {$actionDesc} via app/web", 'api');
            $response['error'] = false;
            $response['message'] = isset($request->id) ? trans('Project Updated Successfully') : trans('Project Posted Successfully');
            // $response['data'] = $result;
            $customerMeta = HelperService::getCustomerMeta($result->added_by ?? null);

            if ($customerMeta) {
                $result->is_agent = $customerMeta['is_agent'] ?? false;
                $result->is_agent_verified = $customerMeta['is_agent_verified'] ?? false;
                $result->is_user_verified = $customerMeta['is_user_verified'] ?? false;
                $result->agent_verification_status = $customerMeta['agent_varification_status'] ?? 'not_applied';
                $result->become_agent_status = $customerMeta['become_agent_status'] ?? 'not_applied';
                $result->user_verification_status = $customerMeta['user_verification_status'] ?? 'not_applied';
            }

            $response['data'] = $result;

            // Notify owner when edited project goes back to pending review
            if (isset($request->id) && $result->getRawOriginal('request_status') === 'pending') {
                $notifyCustomer = $result->customer;
                if ($notifyCustomer && $notifyCustomer->isActive == 1 && $notifyCustomer->notification == 1) {
                    $tokens = Usertokens::where('customer_id', $notifyCustomer->id)->pluck('fcm_id')->toArray();
                    if (! empty($tokens)) {
                        $fcmMsg = [
                            'title' => 'Project updated :- :project_name',
                            'message' => trans('Your project edit is pending review by administrator'),
                            'type' => 'project_inquiry',
                            'body' => trans('Your project edit is pending review by administrator'),
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => 'default',
                            'id' => (string) $result->id,
                            'role_context' => $result->role_context ?? 'user',
                            'replace' => ['project_name' => $result->title],
                        ];
                        send_push_notification($tokens, $fcmMsg);
                    }
                }
                Notifications::create([
                    'title' => 'Project Updated :- '.$result->title,
                    'message' => trans('Your project edit is pending review by administrator'),
                    'image' => '',
                    'type' => '1',
                    'send_type' => '0',
                    'customers_id' => $currentUserId,
                    'role_context' => $result->role_context ?? 'user',
                ]);
            }

            return response()->json($response);
        } catch (Exception $e) {
            DB::rollback();
            $response = [
                'error' => true,
                'message' => trans('Something Went Wrong'),
                'details' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    public function getProjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'offset' => 'nullable|numeric',
                'limit' => 'nullable|numeric',
                'filters' => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ApiResponseService::validationError($validator->errors()->first());
            }

            // Decode filters from base64 (same as getPropertyList)
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

            $filterValidator = Validator::make(
                collect($filters)->toArray(),
                [
                    'category_id' => 'nullable|exists:categories,id',
                    'location.country' => 'nullable',
                    'location.state' => 'nullable',
                    'location.city' => 'nullable',
                    'location.latitude' => 'nullable|numeric|between:-90,90',
                    'location.longitude' => 'nullable|numeric|between:-180,180',
                    'location.radius' => 'nullable|numeric|min:0',
                    'posted_since' => 'nullable|in:0,1,2,3,4',
                    'flags.promoted' => 'nullable',
                    'flags.most_views' => 'nullable',
                    'flags.most_liked' => 'nullable',
                    'flags.get_all_premium_properties' => 'nullable',
                    'project_type' => 'nullable|in:0,1',
                    'role_context' => 'nullable|in:user,agent',
                    'search'       => 'nullable|string',
                ]
            );
            if ($filterValidator->fails()) {
                ApiResponseService::validationError($filterValidator->errors()->first());
            }

            // Get Offset and Limit
            $offset = isset($request->offset) ? $request->offset : 0;
            $limit = isset($request->limit) ? $request->limit : 10;

            // Get filter variables (same structure as getPropertyList)
            $categoryId = isset($filters['category_id']) ? $filters['category_id'] : null;
            $country = isset($filters['location']['country']) ? $filters['location']['country'] : null;
            $state = isset($filters['location']['state']) ? $filters['location']['state'] : null;
            $city = isset($filters['location']['city']) ? $filters['location']['city'] : null;
            $latitude = isset($filters['location']['latitude']) ? $filters['location']['latitude'] : null;
            $longitude = isset($filters['location']['longitude']) ? $filters['location']['longitude'] : null;
            // API filter key renamed to `radius`; internal $range kept to avoid touching the query
            $range = isset($filters['location']['radius']) ? $filters['location']['radius'] : null;
            $postedSince = isset($filters['posted_since']) ? $filters['posted_since'] : null;
            $promoted = isset($filters['flags']['promoted']) ? $filters['flags']['promoted'] : null;
            $getPremiumProjects = isset($filters['flags']['get_all_premium_properties']) ? $filters['flags']['get_all_premium_properties'] : null;
            $mostViewed = isset($filters['flags']['most_views']) ? $filters['flags']['most_views'] : null;
            $mostLiked = isset($filters['flags']['most_liked']) ? $filters['flags']['most_liked'] : null;
            $projectType = isset($filters['project_type']) ? $filters['project_type'] : null;
            $addedAs = isset($filters['role_context']) ? $filters['role_context'] : null;
            $search = isset($filters['search']) && $filters['search'] !== '' ? $filters['search'] : null;

            // Also support legacy top-level slug_id / id params
            $projectSlugId = $request->has('slug_id') ? $request->slug_id : null;
            $projectId = $request->has('id') ? $request->id : null;

            // Base query
            $projectsQuery = Projects::where(['request_status' => 'approved', 'status' => 1])
                ->where(function ($q) {
                    $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
                })
                ->when($addedAs, function ($query) use ($addedAs) {
                    return $query->where('role_context', $addedAs);
                })
                ->with('category:id,slug_id,image,category', 'gallary_images', 'category.translations', 'translations')
                ->with(['customer' => fn ($q) => $q->select('id', 'name', 'profile', 'email', 'mobile', 'slug_id', 'is_agent', 'is_agent_verified')->withStoryStatus()])
                ->select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'status', 'location', 'category_id', 'added_by', 'role_context', 'is_admin_listing', 'is_premium', 'request_status', 'meta_title', 'meta_description', 'meta_keywords', 'meta_image', 'total_click', 'expiry_date', 'created_at');

            // If Project Type is passed (0 = upcoming, 1 = under_construction)
            if (isset($projectType) && $projectType !== null) {
                $typeValue = $projectType == 0 ? 'upcoming' : 'under_construction';
                $projectsQuery = $projectsQuery->where('type', $typeValue);
            }

            // If Category ID is passed
            if (isset($categoryId) && ! empty($categoryId)) {
                $projectsQuery = $projectsQuery->where('category_id', $categoryId);
            }

            // If Country is passed
            if (isset($country) && ! empty($country)) {
                $projectsQuery = $projectsQuery->where('country', 'like', '%'.$country.'%');
            }

            // If State is passed
            if (isset($state) && ! empty($state)) {
                $projectsQuery = $projectsQuery->where('state', 'like', '%'.$state.'%');
            }

            // If City is passed
            if (isset($city) && ! empty($city)) {
                $projectsQuery = $projectsQuery->where('city', 'like', '%'.$city.'%');
            }

            // Latitude and Longitude
            if (isset($latitude) && ! empty($latitude) && isset($longitude) && ! empty($longitude) && $latitude != 'null' && $longitude != 'null') {
                if (isset($range) && ! empty($range) && $range != 'null') {
                    $projectsQuery = $projectsQuery->selectRaw("
                            (6371 * acos(cos(radians(?))
                            * cos(radians(latitude))
                            * cos(radians(longitude) - radians(?))
                            + sin(radians(?))
                            * sin(radians(latitude)))) AS distance", [$latitude, $longitude, $latitude])
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->having('distance', '<', $range);
                } else {
                    $projectsQuery = $projectsQuery->where('latitude', $latitude)->where('longitude', $longitude);
                }
            }

            // If Posted Since is passed
            if (isset($postedSince)) {
                if ($postedSince == 0) {
                    $projectsQuery = $projectsQuery->where(
                        'created_at', '>=',
                        Carbon::now()->subweek()
                    );
                }
                if ($postedSince == 1) {
                    $projectsQuery = $projectsQuery->whereDate('created_at', Carbon::yesterday());
                }
                if ($postedSince == 2) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonth());
                }
                if ($postedSince == 3) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonths(3));
                }
                if ($postedSince == 4) {
                    $projectsQuery = $projectsQuery->where('created_at', '>=', Carbon::now()->subMonths(6));
                }
            }

            // Legacy support for direct slug_id / id params
            if ($projectSlugId) {
                $projectsQuery = $projectsQuery->where('slug_id', $projectSlugId);
            }
            if ($projectId) {
                $projectsQuery = $projectsQuery->where('id', $projectId);
            }

            // Search filter
            if ($search) {
                $projectsQuery = $projectsQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhereHas('category', function ($q2) use ($search) {
                            $q2->where('category', 'like', '%'.$search.'%');
                        })
                        ->orWhere(function ($q3) use ($search) {
                            $q3->searchInAnyTranslation($search);
                        });
                });
            }

            // Existing get_featured support
            // if ($request->filled('get_featured') && $request->get_featured == 1) {
            //     $projectsQuery = $projectsQuery->whereHas('advertisement', function ($query) {
            //         $query->where('for', 'project')->where('status', 0)->where('is_enable', 1);
            //     });
            // }

            // If promoted is passed then show only projects that have active advertisements
            if (isset($promoted) && ! empty($promoted) && $promoted == 1) {
                $projectsQuery = $projectsQuery->whereHas('advertisement', function ($query) {
                    $query->where(['status' => 0, 'is_enable' => 1]);
                });
            }

            // If get_all_premium_properties is passed then show only premium projects
            if (isset($getPremiumProjects) && ! empty($getPremiumProjects) && $getPremiumProjects == 1) {
                $projectsQuery = $projectsQuery->where('is_premium', 1);
            }

            // Get Admin Company Details
            $adminCompanyTel1 = system_setting('company_tel1');
            $adminEmail = system_setting('company_email');
            $adminUser = User::where('id', 1)->select('id', 'slug_id', 'name')->first();

            // Shared mapper for the response shape
            $mapProject = function ($project) use ($adminCompanyTel1, $adminEmail, $adminUser) {
                // Check if listing is by admin then add admin details in customer
                if ($project->is_admin_listing == true) {
                    unset($project->customer);
                    $project->customer = [
                        'name' => $adminUser->name,
                        'email' => $adminEmail,
                        'mobile' => $adminCompanyTel1,
                        'slug_id' => $adminUser->slug_id,
                        'is_agent' => false,
                        'is_agent_verified' => false,
                        'is_admin' => true,
                    ];
                }
                if ($project->category) {
                    $project->category->translated_name = $project->category->translated_name;
                }
                $project->translated_title = $project->translated_title;
                $project->promoted = $project->is_promoted;
                $project->is_premium = $project->is_premium == 1 ? true : false;

                $customerId = $project->added_by ?? null;
                $customerMeta = HelperService::getCustomerMeta($customerId);

                if ($customerMeta) {
                    $project->is_agent = $customerMeta['is_agent'] ?? false;
                    $project->is_agent_verified = $customerMeta['is_agent_verified'] ?? false;
                    $project->is_user_verified = $customerMeta['is_user_verified'] ?? false;
                    $project->agent_verification_status = $customerMeta['agent_varification_status'] ?? 'not_applied';
                    $project->become_agent_status = $customerMeta['become_agent_status'] ?? 'not_applied';
                    $project->user_verification_status = $customerMeta['user_verification_status'] ?? 'not_applied';
                }

                return $project;
            };

            // Active-advertisement constraint that marks a project as "featured/promoted"
            $promotedConstraint = function ($query) {
                $query->where(['status' => 0, 'is_enable' => 1, 'for' => 'project']);
            };

            // The featured-fill distribution only applies to the plain listing.
            // When the client explicitly asks for promoted-only or premium-only,
            // keep the original promoted-first ordering instead.
            $applyFeaturedFill = ! ($promoted == 1) && ! ($getPremiumProjects == 1);

            if ($applyFeaturedFill) {
                // Featured pool: promoted projects, stable order by id DESC
                $featuredQuery = $projectsQuery->clone()
                    ->whereHas('advertisement', $promotedConstraint)
                    ->orderByDesc('id');

                // Normal pool: non-promoted projects, ordered by the chosen sort key
                $normalQuery = $projectsQuery->clone()
                    ->whereDoesntHave('advertisement', $promotedConstraint);

                if (isset($mostViewed) && ! empty($mostViewed) && $mostViewed == 1) {
                    $normalQuery = $normalQuery->orderByDesc('total_click');
                } else {
                    // most_liked falls back to id DESC (projects have no favourites table)
                    $normalQuery = $normalQuery->orderByDesc('id');
                }

                $featuredTotal = $featuredQuery->clone()->count();
                $normalTotal = $normalQuery->clone()->count();
                $total = $featuredTotal + $normalTotal;

                // Guarantee minimum 3 featured per page, backfill the rest with normal
                $slice = HelperService::featuredFillSlice($offset, $limit, $featuredTotal, 3);

                $featuredItems = $slice['featured_take'] > 0
                    ? $featuredQuery->skip($slice['featured_offset'])->take($slice['featured_take'])->get()
                    : collect();

                $normalItems = $slice['normal_take'] > 0
                    ? $normalQuery->skip($slice['normal_offset'])->take($slice['normal_take'])->get()
                    : collect();

                // Featured always on top of each page
                $data = $featuredItems->concat($normalItems)->map($mapProject)->values();
            } else {
                // Promoted-first ordering (original behaviour) for promoted/premium-only requests
                $projectsQuery = $projectsQuery->withCount([
                    'advertisement as promoted_count' => function ($query) {
                        $query->where('status', 0)
                            ->where('is_enable', 1)
                            ->where('for', 'project')
                            ->groupBy('project_id');
                    },
                ]);

                $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN 0 ELSE 1 END');

                if (isset($mostViewed) && ! empty($mostViewed) && $mostViewed == 1) {
                    $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - total_click) END');
                } else {
                    $projectsQuery = $projectsQuery->orderByRaw('CASE WHEN promoted_count > 0 THEN RAND() ELSE (999999999 - id) END');
                }

                $total = $projectsQuery->clone()->count();
                $featuredTotal = $projectsQuery->clone()->whereHas('advertisement', $promotedConstraint)->count();
                $normalTotal = $total - $featuredTotal;

                $data = $projectsQuery->clone()
                    ->take($limit)
                    ->skip($offset)
                    ->get()
                    ->map($mapProject);
            }

            ApiResponseService::successResponse('Data Fetched Successfully', $data, [
                'total' => $total,
                'featured_total' => $featuredTotal,
                'normal_total' => $normalTotal,
            ]);
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function getProjectDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required_without:slug_id|exists:projects,id',
            'slug_id' => 'required_without:id|exists:projects,slug_id',
            'get_similar' => 'nullable|in:1',
        ]);
        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }
        try {
            $isPremium = 0;
            if ($request->id) {
                $isPremium = Projects::where('id', $request->id)->value('is_premium');
            } elseif ($request->slug_id) {
                $isPremium = Projects::where('slug_id', $request->slug_id)->value('is_premium');
            }

            if ($isPremium) {
                if (Auth::guard('sanctum')->check()) {
                    HelperService::checkPackageLimit(config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'), true, true, $request->user_active_role);
                } else {
                    ApiResponseService::errorResponse('Please Login to see premium project details', null, config('constants.RESPONSE_CODE.UNAUTHORIZED'));
                }
            }

            $getSimilarProjects = [];
            $project = Projects::with(['customer' => function ($query) {
                $query->select('id', 'name', 'profile', 'email', 'mobile', 'address', 'slug_id', 'is_agent', 'is_agent_verified')
                    ->withStoryStatus();
            }])
                ->with('gallary_images')
                ->with('documents')
                ->with('plans')
                ->with('category:id,category,image')
                ->with('category.translations', 'translations')
                ->where(function ($query) {
                    $query->where(['request_status' => 'approved', 'status' => 1]);
                });

            if ($request->get_similar == 1) {
                $similarProjectMapper = function ($item) {
                    if ($item->category) {
                        $item->category->translated_name = $item->category->translated_name;
                    }
                    $item->translated_title = $item->translated_title;
                    $item->translated_description = $item->translated_description;

                    return $item;
                };
                $similarProjectQuery = Projects::select('id', 'slug_id', 'city', 'state', 'country', 'title', 'type', 'image', 'location', 'category_id', 'added_by', 'request_status')->where(function ($query) {
                    $query->where(['request_status' => 'approved', 'status' => 1]);
                })->with('category:id,category,image')->with('category.translations', 'translations');
                if ($request->has('id') && ! empty($request->id)) {
                    $getSimilarProjects = $similarProjectQuery->clone()->where('id', '!=', $request->id)->get()->map($similarProjectMapper);
                } elseif ($request->has('slug_id') && ! empty($request->slug_id)) {
                    $getSimilarProjects = $similarProjectQuery->clone()->where('slug_id', '!=', $request->slug_id)->get()->map($similarProjectMapper);
                }
            }

            if ($request->id) {
                $project = $project->where('id', $request->id);
                HelperService::incrementTotalClick('project', $request->id);
            }

            if ($request->slug_id) {
                $project = $project->where('slug_id', $request->slug_id);
                HelperService::incrementTotalClick('project', null, $request->slug_id);
            }

            $total = $project->clone()->count();
            $data = $project->first();

            if (! empty($data)) {
                if ($data->is_admin_listing != 1 && $data->customer) {
                    $roleContext = $data->role_context ?? 'user';
                    $data->customer->loadCount([
                        'projects' => function ($subQuery) use ($roleContext) {
                            $subQuery->onlyActive()->where('role_context', $roleContext);
                        },
                        'property' => function ($subQuery) use ($roleContext) {
                            $subQuery->onlyActive()->where('role_context', $roleContext);
                        },
                    ]);
                }

                if ($data->category) {
                    $data->category->translated_name = $data->category->translated_name;
                }
                $data->translated_title = $data->translated_title;
                $data->translated_description = $data->translated_description;

                if ($data->is_admin_listing == 1) {
                    $adminCompanyTel1 = system_setting('company_tel1');
                    $adminEmail = system_setting('company_email');
                    $adminAddress = system_setting('company_address');
                    $adminData = User::where('type', 0)->select('id', 'name', 'profile', 'slug_id')->first();
                    $totalPropertiesOfAdmin = Property::where(['added_by' => 0, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                        $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
                    })->count();
                    $totalProjectsOfAdmin = Projects::where(['is_admin_listing' => 1, 'status' => 1, 'request_status' => 'approved'])->where(function ($q) {
                        $q->where('expiry_date', '>=', now()->startOfDay())->orWhereNull('expiry_date');
                    })->count();

                    // Create modified customer data
                    $customCustomer = [
                        'id' => $adminData->id,
                        'name' => $adminData->name,
                        'slug_id' => $adminData->slug_id,
                        'profile' => ! empty($adminData->getRawOriginal('profile')) ? $adminData->profile : url('assets/images/faces/2.jpg'),
                        'mobile' => ! empty($adminCompanyTel1) ? $adminCompanyTel1 : '',
                        'email' => ! empty($adminEmail) ? $adminEmail : '',
                        'address' => ! empty($adminAddress) ? $adminAddress : '',
                        'total_properties' => $totalPropertiesOfAdmin,
                        'total_projects' => $totalProjectsOfAdmin,
                        'is_admin' => true,
                        'is_agent' => true,
                        'is_agent_verified' => true,
                    ];

                    // Force Laravel to include the modified customer data
                    $data->setRelation('customer', (object) $customCustomer);
                    $data->customer = (object) $customCustomer;
                } else {
                    // dd($data->customer);
                    $data->total_properties = $data->customer->property_count;
                    $data->total_projects = $data->customer->projects_count;

                    $data->customer?->applyResolvedAgentProfile();
                }
                $data->role_context = $data->role_context ?? 'user';
            }

            // $projectData = new CustomerResource($data, ['is_agent','is_user_verified', 'is_agent_verified']);

            $customerId = $data?->added_by ?? null;
            if ($customerId) {
                $meta = HelperService::getCustomerMeta($customerId);

                if ($meta) {
                    $data->is_agent = $meta['is_agent'] ?? false;
                    $data->is_agent_verified = $meta['is_agent_verified'] ?? false;
                    $data->is_user_verified = $meta['is_user_verified'] ?? false;
                    $data->agent_verification_status = $meta['agent_verification_status'] ?? 'not_applied';
                    $data->become_agent_status = $meta['become_agent_status'] ?? 'not_applied';
                    $data->user_verification_status = $meta['user_verification_status'] ?? 'not_applied';
                }
            }

            ApiResponseService::successResponse(
                'Data Fetched Successfully',
                $data,
                [
                    'total' => $total,
                    'similar_projects' => $getSimilarProjects,
                ]
            );
        } catch (Exception $e) {
            ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function delete_project(Request $request)
    {
        $current_user = Auth::user()->id;

        $validator = Validator::make($request->all(), [

            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }
        $project = Projects::where('added_by', $current_user)->with('gallary_images')->with('documents')->with('plans')->find($request->id);

        if ($project) {
            // Validate active role matches project's role context
            if ($request->user_active_role !== ($project->role_context ?? 'user')) {
                return response()->json([
                    'error' => true,
                    'message' => trans('This project was created in :role mode. Please switch to :role mode to delete it.', ['role' => $project->role_context ?? 'user']),
                ]);
            }
            if ($project->title_image != '') {
                $path = config('global.PROJECT_TITLE_IMG_PATH');
                FileService::clearCachedBlurImageUrl('blur_project_title_image_'.$project->id);
                FileService::delete($path, $project->getRawOriginal('image'));
            }
            if (collect($project->gallary_images)->isNotEmpty()) {
                foreach ($project->gallary_images as $row) {
                    $file = $row->getRawOriginal('name');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->gallary_images()->delete();
            }

            if (collect($project->documents)->isNotEmpty()) {
                foreach ($project->documents as $row) {
                    $file = $row->getRawOriginal('name');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->documents()->delete();
            }
            if (collect($project->plans)->isNotEmpty()) {
                foreach ($project->plans as $row) {
                    $file = $row->getRawOriginal('document');
                    $path = config('global.PROJECT_DOCUMENT_PATH');
                    FileService::delete($path, $file);
                }
                $project->plans()->delete();
            }
            $projectTitle = $project->title;
            $projectId = $project->id;
            $project->delete();
            AuditLogService::log('project', $projectId, $projectTitle, 'deleted', "Project '{$projectTitle}' deleted via app/web", 'api');
            $response['error'] = false;
            $response['message'] = trans('Project Deleted Successfully');
        } else {
            $response['error'] = true;
            $response['message'] = trans('No Data Found');
        }

        return response()->json($response);
    }

    public function changeProjectStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            ApiResponseService::validationError($validator->errors()->first());
        }

        try {
            $loggedInUserID = Auth::user()->id;
            // Get Query Data of project based on project id
            $projectQuery = Projects::where('id', $request->project_id);
            $projectQueryData = $projectQuery->firstOrFail();
            if ($projectQueryData->added_by != $loggedInUserID) {
                ApiResponseService::validationError('Cannot change the status of project owned by others');
            }
            // Validate active role matches project's role context
            if ($request->user_active_role !== ($projectQueryData->role_context ?? 'user')) {
                ApiResponseService::validationError(trans('This project was created in :role mode. Please switch to :role mode.', ['role' => $projectQueryData->role_context ?? 'user']));
            }
            if ($projectQueryData->request_status != 'approved') {
                ApiResponseService::validationError('Project is not approved');
            }
            // update user status
            $projectQuery->update(['status' => $request->status == 1 ? 1 : 0]);
            $statusLabel = $request->status == 1 ? 'Active' : 'Inactive';
            AuditLogService::log('project', $projectQueryData->id, $projectQueryData->title, 'status_changed', "Project status changed to {$statusLabel} via app/web", 'api');
            ApiResponseService::successResponse('Data Updated Successfully');
        } catch (Exception $e) {
            ApiResponseService::errorResponse();
        }
    }

    public function getAddedProjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'nullable|in:under_construction,upcoming',
                'request_status' => 'nullable|in:pending,approved,rejected,expired,draft',
                'status' => 'nullable',
            ]);

            if ($validator->fails()) {
                return ApiResponseService::validationError($validator->errors()->first());
            }

            // ✅ SEO requests skip auth & role validation
            $withSeo = $request->has('with_seo') && $request->with_seo == 1;

            $user = Auth::user();

            // 🔐 Authentication is required for normal (non-SEO) requests
            if (! $withSeo && ! $user) {
                return ApiResponseService::errorResponse(
                    'Unauthenticated.',
                    null,
                    null,
                    401
                );
            }

            // 🔐 Role validation (skipped for SEO requests)
            if (! $withSeo && ($request->has('id') || $request->has('slug_id'))) {
                $isAgentProject = Projects::where('added_by', $user->id)
                    ->when($request->filled('id'), fn ($q) => $q->where('id', $request->id))
                    ->when($request->filled('slug_id'), fn ($q) => $q->where('slug_id', $request->slug_id))
                    ->where('role_context', 'agent')
                    ->exists();

                if ($isAgentProject && $request->user_active_role != 'agent') {
                    return ApiResponseService::errorResponse(
                        'Unauthorized. Active role must be agent.',
                        null,
                        null,
                        403,
                        null,
                        [],
                        config('constants.API_RESPONSE_KEY.REQUIRED_AGENT_ROLE')
                    );
                }
            }

            // 📦 Base query — SEO requests are not scoped to the authenticated user/role
            $projectsQuery = Projects::query()
                ->when(! $withSeo, fn ($q) => $q->where([
                    'added_by' => $user->id,
                    'role_context' => $request->user_active_role,
                ]))
                ->with([
                    'category:id,slug_id,image,category',
                    'gallary_images',
                    'category.translations',
                    'translations',
                    'plans',
                    'documents',
                    'customer' => fn ($q) => $q->select('id', 'name', 'profile', 'email', 'mobile', 'is_agent', 'is_agent_verified')->withStoryStatus(),
                ]);

            // =========================================================
            // 📌 SINGLE PROJECT (id / slug_id)
            // =========================================================
            if ($request->filled('id') || $request->filled('slug_id')) {

                $data = $projectsQuery->clone()
                    ->where(function ($query) use ($request) {
                        $query->when($request->filled('id'), fn ($q) => $q->where('id', $request->id))
                            ->when($request->filled('slug_id'), fn ($q) => $q->orWhere('slug_id', $request->slug_id));
                    })
                    ->first();

                if (! empty($data)) {
                    $data->posted_since = $data->created_at->diffForHumans();

                    if ($data->category) {
                        $data->category->translated_name = $data->category->translated_name;
                    }

                    $data->translated_title = $data->translated_title;
                    $data->translated_description = $data->translated_description;

                    $data = $this->attachCustomerMeta($data);
                }

                $projectData = $data;

                // 🔁 Similar Projects
                $getSimilarProjects = collect();

                if ($request->filled('id')) {
                    $getSimilarProjects = $projectsQuery->clone()
                        ->where('id', '!=', $request->id)
                        ->get()
                        ->map(function ($project) {
                            return $this->formatProject($project);
                        });
                } elseif ($request->filled('slug_id')) {
                    $getSimilarProjects = $projectsQuery->clone()
                        ->where('slug_id', '!=', $request->slug_id)
                        ->get()
                        ->map(function ($project) {
                            return $this->formatProject($project);
                        });
                }

                $responseExtra = [];

                if ($request->user_active_role != 'agent') {
                    $responseExtra['similar_projects'] = $getSimilarProjects;
                }

                return ApiResponseService::successResponse(
                    'Data Fetched Successfully',
                    $projectData,
                    $responseExtra
                );
            }

            // =========================================================
            // 📌 LISTING PROJECTS
            // =========================================================
            $offset = $request->offset ?? 0;
            $limit = $request->limit ?? 10;

            $projectsQuery = $projectsQuery->clone()
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
                ->when($request->filled('status'), function ($q) use ($request) {
                    $statusData = explode(',', $request->status);
                    return $q->whereIn('status', $statusData)->where('request_status', 'approved');
                })
                ->when($request->filled('request_status'), function ($q) use ($request) {
                    if ($request->request_status == 'expired') {
                        return $q->whereNotNull('expiry_date')->where('expiry_date', '<', now()->startOfDay());
                    }

                    return $q->where('request_status', $request->request_status);
                })
                ->select(
                    'id', 'slug_id', 'city', 'state', 'country',
                    'title', 'type', 'image', 'location',
                    'status', 'category_id', 'added_by',
                    'created_at', 'request_status',
                    'edit_reason', 'is_premium', 'expiry_date'
                );

            $total = $projectsQuery->clone()->count();

            $data = $projectsQuery->clone()
                ->latest()
                ->skip($offset)
                ->take($limit)
                ->get()
                ->map(function ($project) {

                    if ($project->request_status == 'rejected') {
                        $project->reject_reason = $project->reject_reason()
                            ->select('id', 'project_id', 'reason', 'created_at')
                            ->latest()
                            ->first();
                    } else {
                        $project->reject_reason = (object) [];
                    }

                    return $this->formatProject($project);
                });

            return ApiResponseService::successResponse(
                'Data Fetched Successfully',
                $data,
                ['total' => $total]
            );

        } catch (Exception $e) {
            return ApiResponseService::errorResponse($e->getMessage());
        }
    }

    public function update_project_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $currentUserId = Auth::user()->id;
            $project = Projects::where('added_by', $currentUserId)->findOrFail($request->project_id);

            // Handle Draft Graduation
            if ($project->getRawOriginal('request_status') === 'draft') {
                $graduation = $this->graduateDraft($project, $request->user_active_role);
                if ($graduation['success']) {
                    return response()->json([
                        'error' => false,
                        'message' => trans('Project Published Successfully'),
                        'data' => $project,
                    ]);
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => $graduation['message'],
                    ]);
                }
            }

            return response()->json(['error' => true, 'message' => 'Invalid status update requested.']);

        } catch (Exception $e) {
            return ResponseService::errorResponse($e);
        }
    }

    private function graduateDraft($project, $userActiveRole)
    {
        $currentUserId = Auth::user()->id;
        $checkPackage = HelperService::checkPackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), true, true, $userActiveRole);
        if (is_array($checkPackage) && isset($checkPackage['limit_available']) && $checkPackage['limit_available'] == true) {
            $limitResult = HelperService::updatePackageLimit(config('constants.FEATURES.PROJECT_LIST.TYPE'), false, true);
            $isPayAsYouGo = ($limitResult === 'pay_as_you_go');

            $autoApproveStatus = HelperService::getAutoApproveStatus($currentUserId, $userActiveRole);
            if ($autoApproveStatus) {
                $project->request_status = 'approved';
            } else {
                $project->request_status = 'pending';
            }
            $project->status = 1;

            if ($autoApproveStatus) {
                if ($isPayAsYouGo) {
                    $project->expiry_date = Carbon::now()->addDays(30);
                } elseif ($autoApproveStatus) {
                    $project->expiry_date = HelperService::calculateExpirationDate($currentUserId);
                }
            }
            $project->save();

            return ['success' => true, 'is_pay_as_you_go' => $isPayAsYouGo, 'auto_approve' => $autoApproveStatus];
        } else {
            return ['success' => false, 'message' => trans('Please purchase a package to publish this project.')];
        }
    }

    private function formatProject($project)
    {
        $project = $this->appendProjectUnitDataToPlans($project);
        $project->posted_since = $project->created_at->diffForHumans();

        if ($project->category) {
            $project->category->translated_name = $project->category->translated_name;
            $parameterData = $project->category->parameters;
            if (collect($parameterData)->isNotEmpty()) {
                $parameterData = $parameterData->map(function ($item) {
                    $item->translated_name = $item->translated_name;
                    $item->translated_option_value = $item->translated_option_value;
                    unset($item->assigned_parameter);
                    return $item;
                });
            }
            $project->category->parameter_types = collect($parameterData)->values()->toArray();
        }

        $project->translated_title = $project->translated_title;
        $project->translated_description = $project->translated_description;

        $project = $this->appendProjectParametersAndFacilities($project);

        return $this->attachCustomerMeta($project);
    }

    private function attachCustomerMeta($project)
    {
        if (! empty($project->customer)) {
            $meta = HelperService::getCustomerMeta($project->customer->id);

            $project->is_agent = $meta['is_agent'] ?? false;
            $project->is_agent_verified = $meta['is_agent_verified'] ?? false;
            $project->is_user_verified = $meta['is_user_verified'] ?? false;
            $project->agent_verification_status = $meta['agent_verification_status'] ?? 'not_applied';
            $project->become_agent_status = $meta['become_agent_status'] ?? 'not_applied';
            $project->user_verification_status = $meta['user_verification_status'] ?? 'not_applied';
        }

        return $project;
    }

    private function appendProjectParametersAndFacilities($project)
    {
        if ($project->relationLoaded('assignParameter')) {
            $project->parameters = $project->assignParameter->map(function ($item) {
                return [
                    'parameter_id' => $item->parameter_id,
                    'value' => $item->value,
                    'image' => $item->parameter?->image ?? null,
                    'name' => $item->parameter?->name ?? '',
                    'translated_name' => $item->parameter?->translated_name ?? $item->parameter?->name ?? '',
                ];
            });
        } else {
            $project->parameters = [];
        }

        if ($project->relationLoaded('assignfacilities')) {
            $project->assign_facilities = $project->assignfacilities->map(function ($item) {
                return [
                    'facility_id' => $item->facility_id,
                    'distance' => $item->distance,
                    'image' => $item->outdoorfacility?->image ?? null,
                    'name' => $item->outdoorfacility?->name ?? '',
                    'translated_name' => $item->outdoorfacility?->translated_name ?? $item->outdoorfacility?->name ?? '',
                ];
            });
        } else {
            $project->assign_facilities = [];
        }

        return $project;
    }

    private function appendProjectUnitDataToPlans($project)
    {
        if (empty($project) || empty($project->plans) || collect($project->plans)->isEmpty()) {
            return $project;
        }

        $unitsForProject = Property::query()
            ->select([
                'project_id',
                'unit_code',
                'price',
                'currency',
                'total_units',
                'available_units',
                'unit_status',
                'category_id',
                'country',
                'state',
                'city',
                'address',
                'latitude',
                'longitude',
                'title',
            ])
            ->where('project_id', $project->id)
            ->where('is_project_unit', true)
            ->get();

        if ($unitsForProject->isEmpty()) {
            return $project;
        }

        $unitsByCode = $unitsForProject->keyBy('unit_code');
        $unitsByTitle = $unitsForProject->keyBy('title');

        foreach ($project->plans as $plan) {
            if (empty($plan->id)) {
                continue;
            }

            $unit = $unitsByCode->get('PLAN_'.$plan->id);

            if (! $unit && $plan->title) {
                $unit = $unitsByTitle->get($plan->title);
            }

            if (! $unit) {
                continue;
            }

            $plan->unit_code = $unit->unit_code;
            $plan->price = $unit->price;
            $plan->currency = $unit->currency;
            $plan->total_units = $unit->total_units;
            $plan->available_units = $unit->available_units;
            $plan->unit_status = $unit->unit_status;
            $plan->property_id = $unit->id;
            $plan->category_id = $unit->category_id;
            $plan->country = $unit->country;
            $plan->state = $unit->state;
            $plan->city = $unit->city;
            $plan->location = $unit->address;
            $plan->latitude = $unit->latitude;
            $plan->longitude = $unit->longitude;
        }

        return $project;
    }

    public function previewImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            $project = Projects::findOrFail($request->project_id);
            $service = app(BulkProjectUnitImportService::class);
            $rows = $service->parse($request->file('file'));

            if (empty($rows)) {
                return response()->json([
                    'error' => true,
                    'message' => 'No valid rows found in the file.',
                ]);
            }

            $errors = $service->validateRows($rows, $project);
            $validRows = $rows;

            if (! empty($errors)) {
                $errorRowNums = collect($errors)->pluck('row')->toArray();
                $validRows = array_values(array_filter($rows, function ($idx) use ($errorRowNums) {
                    return ! in_array($idx + 2, $errorRowNums);
                }, ARRAY_FILTER_USE_KEY));
            }

            $preview = $service->preview($validRows, $project);

            return response()->json([
                'error' => false,
                'data' => [
                    'total_rows' => count($rows),
                    'valid_rows' => count($validRows),
                    'errors' => $errors,
                    'preview' => $preview,
                    'columns' => ! empty($rows) ? array_keys($rows[0]) : [],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkImportUnits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|integer|exists:projects,id',
            'rows' => 'required|json',
            'same_as_previous' => 'nullable|json',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        try {
            DB::beginTransaction();

            $project = Projects::findOrFail($request->project_id);
            $service = app(BulkProjectUnitImportService::class);

            $rows = json_decode($request->rows, true);
            if (empty($rows)) {
                return response()->json([
                    'error' => true,
                    'message' => 'No valid rows provided.',
                ]);
            }

            $imageFiles = $request->file('images', []);
            $sameAsPrev = $request->filled('same_as_previous')
                ? json_decode($request->same_as_previous, true)
                : [];

            $result = $service->importWithImages($rows, $project, $imageFiles, $sameAsPrev);

            DB::commit();

            return response()->json([
                'error' => false,
                'message' => "{$result['created']} created, {$result['updated']} updated ({$result['total']} total).",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadProjectDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ]);
        }

        $filename = FileService::compressAndUpload(
            $request->file('file'),
            config('global.PROJECT_DOCUMENT_PATH')
        );

        if (! $filename) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to upload document',
            ]);
        }

        return response()->json([
            'error' => false,
            'filename' => $filename,
            'url' => FileService::getFileUrl(config('global.PROJECT_DOCUMENT_PATH').$filename),
        ]);
    }

    public function updatePlanStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|integer|exists:project_plans,id',
            'unit_status' => 'required|in:available,low_stock,sold_out,inactive',
        ]);

        if ($validator->fails()) {
            return ApiResponseService::errorResponse($validator->errors()->first());
        }

        try {
            $plan = ProjectPlans::findOrFail($request->plan_id);
            $project = Projects::findOrFail($plan->project_id);

            $unitCode = 'PLAN_'.$plan->id;

            $property = Property::where('project_id', $project->id)
                ->where('is_project_unit', true)
                ->where(function ($q) use ($unitCode, $plan) {
                    $q->where('unit_code', $unitCode)
                      ->orWhere('title', $plan->title);
                })
                ->first();

            if (! $property) {
                return ApiResponseService::errorResponse('No unit found for this plan');
            }

            $unitStatus = $request->unit_status;
            $property->unit_status = $unitStatus;

            switch ($unitStatus) {
                case 'sold_out':
                    $property->available_units = 0;
                    break;
                case 'available':
                    $property->available_units = $property->total_units ?? 1;
                    break;
                case 'low_stock':
                    if (($property->available_units ?? 0) > 3) {
                        $property->available_units = 3;
                    } elseif ($property->available_units === null) {
                        $property->available_units = 1;
                    }
                    break;
            }

            $property->save();

            return ApiResponseService::successResponse('Plan status updated successfully', [
                'property_id' => $property->id,
                'unit_status' => $property->unit_status,
                'available_units' => $property->available_units,
            ]);
        } catch (Exception $e) {
            Log::error('Error updating plan status: '.$e->getMessage());

            return ApiResponseService::errorResponse('Failed to update plan status');
        }
    }
}
