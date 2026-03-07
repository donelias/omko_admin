<?php
namespace App\Services;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use GuzzleHttp\Client;
use App\Models\Feature;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\Language;
use App\Models\Projects;
use App\Models\Property;
use App\Models\Usertokens;
use App\Models\ProjectView;
use App\Models\Translation;
use App\Models\UserPackage;
use Illuminate\Support\Str;
use App\Models\PropertyView;
use App\Models\UserInterest;
use App\Models\PasswordReset;
use App\Models\PackageFeature;
use App\Models\UserPackageLimit;
use App\Mail\GenericMailTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Services\ApiResponseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Intl\Currencies;

class HelperService {
    public static function currencyCode(){
        $currencies = Currencies::getNames();
        $currenciesArray = array();
        foreach ($currencies as $key => $value) {
            $currenciesArray[] = array(
                'currency_code' => $key,
                'currency_name' => $value
            );
        }
        return $currenciesArray;
    }

    public static function getCurrencyData($code){
        $name = Currencies::getName($code);
        $currencySymbol = Currencies::getSymbol($code);
        return array('code' => $code, 'name' => $name, 'symbol' => $currencySymbol);
    }

    // Generate Token
    public static function generateToken(){
        return bin2hex(random_bytes(50)); // Generates a secure random token
    }

    // Store Token
    public static function storeToken($email,$token){
        $expiresAt = now()->addMinutes(60); // Set token to expire after 60 minutes
        PasswordReset::updateOrCreate(
            array(
                'email' => $email
            ),
            array(
                'token' => $token,
                'expires_at' => $expiresAt,
            )
        );
        return true;
    }

    // Verify Token
    public static function verifyToken($token){
        $record = PasswordReset::where('token', $token)->where('expires_at', '>', now())->first();
        if ($record) {
            return $record->email;
        } else {
            return false;
        }
    }

    // Make Token Expire
    public static function expireToken($email){
        $expiresAt = now(); // Set token to expire after 60 minutes
        PasswordReset::updateOrCreate(
            array(
                'email' => $email
            ),
            array(
                'expires_at' => $expiresAt,
            )
        );
        return true;
    }

    public static function getEmailTemplatesTypes($type = null){
        // Return required data if type is passed
        if($type){
            switch ($type) {
                case 'verify_mail':
                    return array(
                        'title' => __('Verify Email Account'),
                        'type' => 'verify_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'otp','is_condition' => false,
                            ],
                        )
                    );
                case 'reset_password':
                    return array(
                        'title' => __('Password Reset Mail'),
                        'type' => 'password_reset_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'link','is_condition' => false,
                            ],
                        )
                    );
                case 'welcome_mail':
                    return array(
                        'title' => __('Welcome Mail'),
                        'type' => 'welcome_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                        )
                    );
                case 'property_status':
                    return array(
                        'title' => __('Property status change by admin'),
                        'type' => 'property_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                            [
                                'name' => 'reject_reason','is_condition' => false,
                            ],
                        )
                    );
                case 'project_status':
                    return array(
                        'title' => __('Project status change by admin'),
                        'type' => 'project_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'project_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                            [
                                'name' => 'reject_reason','is_condition' => false,
                            ],
                        )
                    );
                case 'property_ads_status':
                    return array(
                        'title' => __('Property Advertisement status change by admin'),
                        'type' => 'property_ads_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ],
                            [
                                'name' => 'advertisement_status','is_condition' => false,
                            ],
                        )
                    );
                case 'user_status':
                    return array(
                        'title' => __('User account active de-active status'),
                        'type' => 'user_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                        )
                    );
                case 'agent_verification_status':
                    return array(
                        'title' => __('Agent Verification Status'),
                        'type' => 'agent_verification_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'status','is_condition' => false,
                            ],
                        )
                    );
                case 'new_property_in_category_listing':
                    return array(
                        'title' => __('New Property in Category Listing'),
                        'type' => 'new_property_in_category_listing_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'category_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ]
                        )
                    );
                case 'subscription_expiring_soon':
                    return array(
                        'title' => __('Subscription Expiring Soon'),
                        'type' => 'subscription_expiring_soon_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'package_name','is_condition' => false,
                            ],
                            [
                                'name' => 'subscription_end_date','is_condition' => false,
                            ],
                        )
                    );
                case 'new_appointment_request':
                    return array(
                        'title' => __('New Appointment Request'),
                        'type' => 'new_appointment_request_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ],
                            [
                                'name' => 'agent_name','is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_status','is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_type','is_condition' => false,
                            ],
                            [
                                'name' => 'start_time','is_condition' => false,
                            ],
                            [
                                'name' => 'end_time','is_condition' => false,
                            ],
                            [
                                'name' => 'date','is_condition' => false,
                            ],
                            [
                                'name' => 'notes','is_condition' => true,
                            ]
                        )
                    );
                case 'appointment_status':
                    return array(
                        'title' => __('Appointment Status'),
                        'type' => 'appointment_status_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ],
                            [
                                'name' => 'agent_name','is_condition' => false,
                            ],
                            [
                                'name' => 'customer_name','is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_status','is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_type','is_condition' => false,
                            ],
                            [
                                'name' => 'start_time','is_condition' => false,
                            ],
                            [
                                'name' => 'end_time','is_condition' => false,
                            ],
                            [
                                'name' => 'date','is_condition' => false,
                            ],
                            [
                                'name' => 'reason','is_condition' => true,
                            ]
                        )
                    );
                case 'appointment_meeting_type_change':
                    return array(
                        'title' => __('Appointment Meeting Type Change'),
                        'type' => 'appointment_meeting_type_change_mail_template',
                        'required_fields' => array(
                            [
                                'name' => 'app_name','is_condition' => false,
                            ],
                            [
                                'name' => 'user_name','is_condition' => false,
                            ],
                            [
                                'name' => 'property_name','is_condition' => false,
                            ],
                            [
                                'name' => 'agent_name','is_condition' => false,
                            ],
                            [
                                'name' => 'customer_name','is_condition' => false,
                            ],
                            [
                                'name' => 'old_meeting_type','is_condition' => false,
                            ],
                            [
                                'name' => 'new_meeting_type','is_condition' => false,
                            ],
                            [
                                'name' => 'start_time','is_condition' => false,
                            ],
                            [
                                'name' => 'end_time','is_condition' => false,
                            ],
                            [
                                'name' => 'date','is_condition' => false,
                            ]
                        )
                    );
            }
        }

        // Return All if no type is passed
        return array(
            [
                'title' => __('Verify Email Account'),
                'type' => 'verify_mail',
            ],
            [
                'title' => __('Password Reset Mail'),
                'type' => 'reset_password',
            ],
            [
                'title' => __('Welcome Mail'),
                'type' => 'welcome_mail',
            ],
            [
                'title' => __('Property status change by admin'),
                'type' => 'property_status',
            ],
            [
                'title' => __('Project status change by admin'),
                'type' => 'project_status',
            ],
            [
                'title' => __('Property Advertisement status change by admin'),
                'type' => 'property_ads_status',
            ],
            [
                'title' => __('User account active de-active status'),
                'type' => 'user_status',
            ],
            [
                'title' => __('Agent Verification Status'),
                'type' => 'agent_verification_status',
            ],
            [
                'title' => __('New Property in Category Listing'),
                'type' => 'new_property_in_category_listing',
            ],
            [
                'title' => __('Subscription Expiring Soon'),
                'type' => 'subscription_expiring_soon',
            ],
            [
                'title' => __('New Appointment Request'),
                'type' => 'new_appointment_request',
            ],
            [
                'title' => __('Appointment Status'),
                'type' => 'appointment_status',
            ],
            [
                'title' => __('Appointment Meeting Type Change'),
                'type' => 'appointment_meeting_type_change',
            ]
        );
    }


    public static function replaceEmailVariables($templateContent, $variables){
        $templateContent = htmlspecialchars_decode($templateContent);
        // First pass A: handle conditional blocks in legacy format {key}...{end_key}
        foreach ($variables as $key => $variable) {
            $startTag = '{' . $key . '}';
            $endTag = "{end_{$key}}";

            if (strpos($templateContent, $startTag) !== false && strpos($templateContent, $endTag) !== false) {
                $pattern = '/' . preg_quote($startTag, '/') . '(.*?)' . preg_quote($endTag, '/') . '/s';

                if (!empty($variable)) {
                    $templateContent = preg_replace_callback($pattern, function ($matches) {
                        return $matches[1];
                    }, $templateContent);
                } else {
                    $templateContent = preg_replace($pattern, '', $templateContent);
                }
            }
        }

        // First pass B: handle conditional blocks in new format {start_key} ... (end_key)
        foreach ($variables as $key => $variable) {
            $startTagNew = '{start_' . $key . '}';
            $endTagNew = '(end_' . $key . ')';
            if (strpos($templateContent, $startTagNew) !== false && strpos($templateContent, $endTagNew) !== false) {
                $patternNew = '/' . preg_quote($startTagNew, '/') . '(.*?)' . preg_quote($endTagNew, '/') . '/s';

                if (!empty($variable)) {
                    $templateContent = preg_replace_callback($patternNew, function ($matches) {
                        return $matches[1];
                    }, $templateContent);
                } else {
                    $templateContent = preg_replace($patternNew, '', $templateContent);
                }
            }
        }

        // Second pass: simple placeholder replacements {key}
        foreach ($variables as $key => $variable) {
            $placeholder = '{' . $key . '}';
            $templateContent = str_replace($placeholder, (string)$variable, $templateContent);
        }

        return $templateContent;
    }

    public static function sendMail($data, $requiredEmailException = false, $skipQueue = false)
    {
        try {

            $adminMail = env('MAIL_FROM_ADDRESS');
            $companyName = HelperService::getSettingData('company_name');

            // Prepare the Mailable
            $mailable = new GenericMailTemplate($data, $adminMail, $companyName);

            // Optimistically assume queue works if we've never seen it
            if($skipQueue == true){
                Mail::to($data['email'])->send($mailable);
            }else{
                Mail::to($data['email'])->queue($mailable);
            }


        } catch (Exception $e) {
            if ($requiredEmailException === true) {
                DB::rollback();
                throw $e;
            }

            if (Str::contains($e->getMessage(), [
                'Failed',
                'Mail',
                'Mailer',
                'MailManager'
            ])) {
                Log::error("Cannot send mail, there is issue with mail configuration.");
            } else {
                $logMessage = "Send Mail for property feature status changed";
                Log::error($logMessage . ' ' . $e->getMessage() . '---> ' . $e->getFile() . ' At Line : ' . $e->getLine());
            }
        }
    }

    public static function getFeatureList(){
        try {
            $features = Feature::where('status',1)->get();
            return $features;
        } catch (Exception $e) {
            Log::error('Issue in Get Feature list of Helper Service :- '.$e->getMessage());
            return array();
        }
    }

    public static function getSettingData($type, $getRawData = true){
        $settingQueryData = Setting::where('type',$type)->select('type','data')->first();
        if($getRawData == true){
            return $settingQueryData ? $settingQueryData->getRawOriginal('data') : null;
        }
        return $settingQueryData ? $settingQueryData->data : null;
    }

    public static function getMultipleSettingData(array $types, $raw = false){
        $settingData = Setting::whereIn('type',$types)->get();
        if(!empty($settingData)){
            $data = array();
            foreach ($settingData as $setting) {
                if($setting->type == 'default_language'){
                    if($raw == true){
                        $data[$setting->type] = $setting->getRawOriginal('data');
                    }else{
                        $data[$setting->type] = $setting->data;
                    }
                }else{
                    $data[$setting->type] = $setting->data;
                }
            }
            return $data ? $data : null;
        }
        return null;
    }

    public static function getOneActivePaymentGateway(){
        try {
            $paymentMethodTypes = array('stripe_gateway','razorpay_gateway','paystack_gateway','paypal_gateway','flutterwave_status','cashfree_gateway');
            $settingsData = Setting::whereIn('type',$paymentMethodTypes)->get();
            foreach ($settingsData as $key => $setting) {
                if($setting->data == 1){
                    return $setting->type;
                }
            }
            return 'none';
        } catch (Exception $e) {
            Log::error('Issue in Get Active Payment Gateway function of Helper Service :- '.$e->getMessage());
            return false;
        }
    }


    public static function getPaymentDetails($paymentMethod = null)
    {
        try {
            $getActivePaymentName = $paymentMethod ?? self::getOneActivePaymentGateway();

            switch ($getActivePaymentName) {
                case 'stripe_gateway':
                case 'stripe':
                    $types = ['stripe_currency', 'stripe_gateway', 'stripe_publishable_key', 'stripe_secret_key'];
                    $data = ['payment_method' => 'stripe'];
                    return array_merge($data, self::getMultipleSettingData($types));

                case 'razorpay_gateway':
                case 'razorpay':
                    $types = ['razorpay_gateway', 'razor_key', 'razor_secret', 'razorpay_webhook_url', 'razor_webhook_secret'];
                    $data = ['payment_method' => 'razorpay'];
                    return array_merge($data, self::getMultipleSettingData($types));

                case 'paystack_gateway':
                case 'paystack':
                    $types = ['paystack_secret_key', 'paystack_public_key', 'paystack_currency'];
                    $data = ['payment_method' => 'paystack'];
                    return array_merge($data, self::getMultipleSettingData($types));

                case 'paypal_gateway':
                case 'paypal':
                    $types = ['paypal_client_id', 'paypal_client_secret', 'paypal_currency', 'sandbox_mode'];
                    $data = ['payment_method' => 'paypal'];
                    return array_merge($data, self::getMultipleSettingData($types));

                case 'flutterwave_status':
                case 'flutterwave':
                    $types = ['flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_webhook_url', 'flutterwave_currency', 'flutterwave_status'];
                    $data = ['payment_method' => 'flutterwave'];
                    return array_merge($data, self::getMultipleSettingData($types));

                case 'cashfree_gateway':
                case 'cashfree':
                    $types = ['cashfree_app_id', 'cashfree_secret_key', 'cashfree_currency', 'cashfree_sandbox_mode', 'cashfree_webhook_url'];
                    $data = ['payment_method' => 'cashfree'];
                    return array_merge($data, self::getMultipleSettingData($types));
                
                case 'phonepe_gateway':
                case 'phonepe':
                    $types = ['phonepe_client_id', 'phonepe_client_secret', 'phonepe_merchant_id', 'phonepe_gateway', 'phonepe_webhook_url', 'phonepe_sandbox_mode', 'phonepe_client_version'];
                    $data = ['payment_method' => 'phonepe'];
                    return array_merge($data, self::getMultipleSettingData($types));
                
                case 'midtrans_gateway':
                case 'midtrans':
                    $types = ['midtrans_server_key', 'midtrans_client_key', 'midtrans_currency', 'midtrans_sandbox_mode'];
                    $data = ['payment_method' => 'midtrans'];
                    return array_merge($data, self::getMultipleSettingData($types));

                default:
                    return false;
            }
        } catch (Exception $e) {
            Log::error('Issue in Get Payment Details function of Helper Service :- ' . $e->getMessage());
            return false;
        }
    }

    public static function changeEnv($updateData = array()): bool {
        if (count($updateData) > 0) {
            // Read .env-file
            $env = file_get_contents(base_path() . '/.env');
            // Split string on every " " and write into array
            $env = preg_split('/\r\n|\r|\n/', $env);
            $env_array = [];
            foreach ($env as $env_value) {
                if (empty($env_value)) {
                    //Add and Empty Line
                    $env_array[] = "";
                    continue;
                }

                // Skip comments and lines without '='
                if (strpos($env_value, '#') === 0 || strpos($env_value, '=') === false) {
                    $env_array[] = $env_value;
                    continue;
                }

                $entry = explode("=", $env_value, 2);
                if (isset($entry[1])) {
                    $env_array[$entry[0]] = $entry[0] . "=\"" . str_replace("\"", "", $entry[1]) . "\"";
                } else {
                    $env_array[$entry[0]] = $entry[0] . "=\"\"";
                }
            }

            foreach ($updateData as $key => $value) {
                $env_array[$key] = $key . "=\"" . str_replace("\"", "", $value) . "\"";
            }
            // Turn the array back to a String
            $env = implode("\n", $env_array);

            // And overwrite the .env with the new data
            file_put_contents(base_path() . '/.env', $env);
            return true;
        }
        return false;
    }

    public static function getAllActivePackageIds($userId){
        // Retrieve user packages with end_time less than or equal to current date
        $packageIds = UserPackage::where('user_id', $userId)
            ->onlyActive()
            ->pluck('package_id');
        return $packageIds;
    }
    public static function getActivePackage($userId, $packageId){
        // Retrieve user packages with end_time less than or equal to current date
        $userPackages = UserPackage::where('user_id', $userId)
            ->where('package_id', $packageId)
            ->onlyActive()
            ->first();
        return $userPackages;
    }

    public static function getFeatureId($type){
        try {
            $featureQuery = Feature::query();
            switch ($type) {
                case config('constants.FEATURES.PROPERTY_LIST.TYPE'):
                    $featureQuery = $featureQuery->clone()->where('type', config('constants.FEATURES.PROPERTY_LIST.TYPE'));
                    break;
                case config('constants.FEATURES.PROJECT_LIST.TYPE'):
                    $featureQuery = $featureQuery->clone()->where('type', config('constants.FEATURES.PROJECT_LIST.TYPE'));
                    break;
                case config('constants.FEATURES.PROPERTY_FEATURE.TYPE'):
                    $featureQuery = $featureQuery->clone()->where('type', config('constants.FEATURES.PROPERTY_FEATURE.TYPE'));
                    break;
                case config('constants.FEATURES.PROJECT_FEATURE.TYPE'):
                    $featureQuery = $featureQuery->clone()->where('type', config('constants.FEATURES.PROJECT_FEATURE.TYPE'));
                    break;
                case config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.TYPE'):
                    $featureQuery = $featureQuery->clone()->where('type', config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.TYPE'));
                    break;
                case config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'):
                    $featureQuery = $featureQuery->clone()->where('type', config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'));
                    break;
                case config('constants.FEATURES.PROJECT_ACCESS.TYPE'):
                    $featureQuery = $featureQuery->clone()->where('type', config('constants.FEATURES.PROJECT_ACCESS.TYPE'));
                    break;
                default:
                    Log::error('Type not allowed in getFeatureId function of HelperService');
                    return false;
                    break;
            }
            return $featureQuery->pluck('id')->first();
        } catch (Exception $e) {
            Log::error('Issue in Get Feature ID HelperService Function => '.$e->getMessage());
            return false;
        }
    }


    public static function updatePackageLimit($type,$getPackageDataReturn = false)
    {
        try {
            $featureTypes = array(config('constants.FEATURES.PROPERTY_LIST.TYPE'), config('constants.FEATURES.PROPERTY_FEATURE.TYPE'), config('constants.FEATURES.PROJECT_LIST.TYPE'), config('constants.FEATURES.PROJECT_FEATURE.TYPE'), config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.TYPE'), config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'), config('constants.FEATURES.PROJECT_ACCESS.TYPE'));
            if (!in_array($type, $featureTypes)) {
                ApiResponseService::validationError("Invalid Feature Type");
            }

            $featureId = HelperService::getFeatureId($type);

            if (collect($featureId)->isEmpty()) {
                ApiResponseService::validationError("Invalid Feature Type");
            }

            if(Auth::guard('sanctum')->check()){
                $loggedInUserData = Auth::guard('sanctum')->user();
            }else{
                ApiResponseService::validationError('Package not found');
            }

            $packagesIds = HelperService::getAllActivePackageIds($loggedInUserData->id);
            if (collect($packagesIds)->isEmpty()) {
                ApiResponseService::validationError('Package not available');
            }
            $userPackageIds = UserPackage::whereIn('package_id', $packagesIds)->where('user_id',$loggedInUserData->id)->pluck('id');

            $packageFeatureQuery = PackageFeature::where('feature_id', $featureId)->whereIn('package_id', $packagesIds);
            $packageFeatureIds = $packageFeatureQuery->clone()->pluck('id');

            if (collect($packageFeatureIds)->isEmpty()) {
                ApiResponseService::validationError('Package not available');
            }

            $packageFeatures = $packageFeatureQuery->clone()->with(['user_package_limits' => function ($query) use($userPackageIds){
                $query->whereIn('user_package_id', $userPackageIds);
            },'package'])->get();

            foreach ($packageFeatures as $packageFeatureData) {
                if ($packageFeatureData->limit_type == 'unlimited') {
                    if($getPackageDataReturn == true){
                        return $packageFeatureData->package;
                    }else{
                        return true;
                    }
                }
                if($packageFeatureData->user_package_limits){
                    foreach ($packageFeatureData->user_package_limits as $package) {
                        if ($package->total_limit > $package->used_limit) {
                            // Deduct one limit
                            $package->used_limit += 1;
                            $package->save();
                            if($getPackageDataReturn == true){
                                return $packageFeatureData->package;
                            }else{
                                return true;
                            }
                        }
                    }
                }
            }

            ApiResponseService::validationError("Limit Not Available");
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Issue in update package limit helper function');
        }
    }


    public static function checkPackageLimit($type, $getCheckDataInReturn = false){
        try{
            $packageAvailable = false;
            $featureAvailable = false;
            $limitAvailable = false;
            $loggedInUserData = null;
            if(Auth::guard('sanctum')->check()){
                $loggedInUserData = Auth::guard('sanctum')->user();
            }
            $featureId = HelperService::getFeatureId($type);

            if (!empty($featureId)) {
                if($loggedInUserData){
                    $packageIds = HelperService::getAllActivePackageIds($loggedInUserData->id);
                }
                if (isset($packageIds) && collect($packageIds)->isNotEmpty()) {
                    $packageAvailable = true;
                    $userPackages = UserPackage::whereIn('package_id', $packageIds)->where('user_id',$loggedInUserData->id)->get();
                    $userPackageIds = $userPackages->pluck('id');

                    $packageFeatureQuery = PackageFeature::where('feature_id', $featureId)->whereIn('package_id', $packageIds);
                    $getPackageFeatureData = $packageFeatureQuery->clone()->get();
                    if(collect($getPackageFeatureData)->isNotEmpty()){
                        $featureAvailable = true;
                        foreach ($getPackageFeatureData as $packageFeatureData) {
                            if($packageFeatureData->limit_type == 'unlimited'){
                                $limitAvailable = true;
                            }else if($packageFeatureData->limit_type == 'limited'){
                                $packageFeatureIds = $packageFeatureQuery->clone()->pluck('id');
                                $userPackageLimit = UserPackageLimit::whereIn('user_package_id', $userPackageIds)->whereIn('package_feature_id', $packageFeatureIds)->get();
                                if (collect($userPackageLimit)->isNotEmpty()) {
                                    foreach ($userPackageLimit as $package) {
                                        if($package->total_limit > $package->used_limit){
                                            $limitAvailable = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if($getCheckDataInReturn == true){
                return [
                    'package_available' => $packageAvailable,
                    'feature_available' => $featureAvailable,
                    'limit_available' => $limitAvailable,
                ];
            }else{
                if($packageAvailable){
                    if($featureAvailable){
                        if($limitAvailable){
                            return true;
                        }else{
                            ApiResponseService::validationError("Limit Not Available");
                        }
                    }else{
                        ApiResponseService::validationError("Feature Not Available");
                    }
                }else{
                    ApiResponseService::validationError("Package Not Available");
                }
            }

        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Issue in check package limit helper function');
        }
    }
    public static function incrementTotalClick($type, $id = null, $slugId = null)
    {
        if(Auth::guard('sanctum')->check()){
            $loggedInUserData = Auth::guard('sanctum')->user();
            $currentDate = Carbon::now()->format('Y-m-d');

            if ($type === 'project') {
                if(!empty($id)){
                    $project = Projects::where('id', $id)->firstOrFail();
                }else{
                    $project = Projects::where('slug_id', $slugId)->firstOrFail();
                }

                $view = ProjectView::updateOrCreate(
                    ['project_id' => $project->id, 'date' => $currentDate, 'user_id' => $loggedInUserData->id],
                    ['views' => 1]
                );
                if ($view->wasRecentlyCreated) {
                    $project->increment('total_click');
                }

            } elseif ($type === 'property') {
                if(!empty($id)){
                    $property = Property::where('id', $id)->firstOrFail();
                }else{
                    $property = Property::where('slug_id', $slugId)->firstOrFail();
                }

                $view = PropertyView::updateOrCreate(
                    ['property_id' => $property->id, 'date' => $currentDate, 'user_id' => $loggedInUserData->id],
                    ['views' => 1]
                );
                if ($view->wasRecentlyCreated) {
                    $property->increment('total_click');
                }
            }
        }
        return true;
    }



    // Convert a UTC datetime to app timezone
    public static function toAppTimezone($dateTime)
    {
        $timezone = self::getSettingData('timezone');

        if (!$dateTime instanceof Carbon) {
            $dateTime = Carbon::parse($dateTime, 'UTC');
        } else {
            $dateTime = $dateTime->copy()->setTimezone('UTC');
        }

        return $dateTime->setTimezone($timezone);
    }

    // public static function getIntervalOfDate($endDate){
    //     $startDate = Carbon::now();
    //     $endDate = Carbon::parse($endDate);
    //     $diff = $startDate->diff($endDate);

    //     if ($diff->y > 0) {
    //         $interval = $diff->format('%y years left');
    //     } elseif ($diff->m > 0) {
    //         $interval = $diff->format('%m months left');
    //     } elseif ($diff->d > 0) {
    //         $interval = $diff->format('%d days left');
    //     } elseif ($diff->h > 0) {
    //         $interval = $diff->format('%h hours left');
    //     } elseif ($diff->i > 0) {
    //         $interval = $diff->format('%i minutes left');
    //     } else {
    //         $interval = $diff->format('%s seconds left');
    //     }
    //     return $interval ?? null;
    // }


    public static function getFeatureNames(){
        $featureNames = array(
            config('constants.FEATURES.PROPERTY_LIST'),
            config('constants.FEATURES.PROPERTY_FEATURE'),
            config('constants.FEATURES.PROJECT_LIST'),
            config('constants.FEATURES.PROJECT_FEATURE'),
            config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL'),
            config('constants.FEATURES.PREMIUM_PROPERTIES'),
            config('constants.FEATURES.PROJECT_ACCESS'),
        );
        return $featureNames;
    }


    /**
     * Get the homepage section types
     * @return array
     */
    public static function getHomepageSectionTypes(){
        $homepageSectionTypes = array(
            config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TYPE')                 => trans(config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TYPE')                    => trans(config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TYPE')                  => trans(config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TYPE')                        => trans(config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TYPE')         => trans(config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TYPE')           => trans(config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TYPE')       => trans(config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TYPE')      => trans(config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TYPE')           => trans(config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TYPE')                    => trans(config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TYPE')          => trans(config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TYPE')        => trans(config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TYPE')        => trans(config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TITLE')),
                config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TYPE')           => trans(config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TITLE')),
        );
        return $homepageSectionTypes;
    }

    public static function AlertUserForNewListing($propertyId){
        try{
            $property = Property::where('id',$propertyId)->with('category:id,category')->first();
            $categoryId = implode(',', array($property->category_id));

            $emailTypeData = self::getEmailTemplatesTypes("new_property_in_category_listing");
            $templateData = self::getSettingData($emailTypeData['type']);
            if(empty($templateData)){
                $templateData = "New Property in Category Listing";
            }

            $variables = [
                'app_name' => env("APP_NAME", "eBroker"),
                'category_name' => $property->category->category,
                'property_name' => $property->title
            ];
            $userInterests = UserInterest::whereRaw('FIND_IN_SET(?, category_ids)', [$categoryId])->get();
            $userIds = $userInterests->pluck('user_id');
            $userData = Customer::whereIn('id', $userIds)->select('id','name','email')->get();
            foreach($userData as $user){
                $variables['user_name'] = $user->name;
                $variables['email'] = $user->email;
                $propertyListTemplate = HelperService::replaceEmailVariables($templateData, $variables);
                $data = [
                    'email_template' => $propertyListTemplate,
                    'email' => $user->email,
                    'title' => 'New Property in Your Category',
                ];

                self::sendMail($data);
            }

            // Send notification to Users
            $userFCMTokens = Usertokens::whereIn('customer_id', $userIds)->pluck('fcm_id');
            if(!empty($userFCMTokens)){
                $translatedTitle = 'New Property Alert';
                $translatedBody = 'New Property in Your Category Selected';
                $fcmMsg = array(
                    'title'         => $translatedTitle,
                    'message'       => $translatedBody,
                    'type'          => 'new_property_listing',
                    'body'          => $translatedBody,
                    'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound'         => 'default',
                    'property_id'   => (string)$propertyId,

                );
                send_push_notification($userFCMTokens, $fcmMsg);
            }
            return true;
        }catch(Exception $e){
            Log::error("Issue in alert user for new listing helper function: " . $e->getMessage());
        }
    }

    public static function getActiveLanguages($specificSelect = null, $withEnglish = false){
        try{
            $englishCode = array('en','en-new');
            $languageQuery = Language::where('status', 1);
            if(!$withEnglish){
                $languageQuery = $languageQuery->whereNotIn('code', $englishCode);
            }
            if(!empty($specificSelect)){
                $languageQuery = $languageQuery->select($specificSelect);
            }
            // Ensure default language (from settings) appears first
            $defaultCode = self::getSettingData('default_language');
            if (!empty($defaultCode)) {
                $languageQuery = $languageQuery->orderByRaw("CASE WHEN code = ? THEN 0 ELSE 1 END", [$defaultCode]);
            }
            return $languageQuery->get();
        }catch(Exception $e){
            ApiResponseService::logErrorResponse($e, 'Issue in get active languages helper function');
        }
    }

    public static function storeTranslations($translations){
        try{
            $storeTranslations = array();
            foreach($translations as $translation){
                if(isset($translation['language_id']) && !empty($translation['language_id']) && isset($translation['value']) && !empty($translation['value'])){
                    $storeTranslations[] = [
                        'id'                => $translation['id'] ?? null,
                        'translatable_id'   => $translation['translatable_id'],
                        'translatable_type' => $translation['translatable_type'],
                        'language_id'       => $translation['language_id'],
                        'key'               => $translation['key'],
                        'value'             => $translation['value'],
                        'created_at'        => now(),
                        'updated_at'        => now()
                    ];
                }
            }
            if(!empty($storeTranslations)){
                Translation::upsert($storeTranslations, ['id']);
            }
        }catch(Exception $e){
            ApiResponseService::logErrorResponse($e, 'Issue in store translations helper function');
        }
    }

    public static function getTranslatedData($dataObject, $defaultData, $key){
        $languageCode = request()->header('Content-Language') ?? null;

        if (empty($languageCode)) {
            return $defaultData;
        }

        // Cache language ID lookup
        $languageId = cache()->remember("language_id_{$dataObject->id}_{$key}_{$languageCode}", 3600, function() use ($languageCode) {
            return Language::where('code', $languageCode)->value('id');
        });

        if (empty($languageId)) {
            return $defaultData;
        }

        // Use specific relationship or direct query
        if ($dataObject->relationLoaded('translations')) {
            $translation = $dataObject->translations->where('language_id', $languageId)
                                            ->where('key', $key)
                                            ->first();
            return $translation?->value ?? $defaultData;
        }
        return $defaultData;
    }


    public static function runQueue(){
        $client = new Client([
            'timeout'  => 10,
            'verify'   => false // for self-signed SSL, optional
        ]);

        $url = URL::route('run.queue');
        $response = $client->request('GET', $url);

        if ($response->getStatusCode() === 200) {
            return true;
        }
        return false;
    }

    public static function getCurlRequest(){
        $request = request();

        $method  = strtoupper($request->method());
        $url     = $request->fullUrl();
        $headers = [];

        foreach ($request->headers->all() as $key => $values) {
            foreach ($values as $value) {
                $headers[] = "-H '" . $key . ": " . $value . "'";
            }
        }

        $data = '';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $request->header('Content-Type');

            if (str_contains($contentType, 'application/json')) {
                // JSON payload
                $payload = $request->all();
                $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $data = " --data '" . addslashes($payloadJson) . "'";
            } elseif (str_contains($contentType, 'multipart/form-data')) {
                // Multipart (e.g., file upload)
                $parts = [];
                foreach ($request->all() as $key => $value) {
                    if (is_array($value)) {
                        foreach ($value as $v) {
                            $parts[] = "-F '{$key}={$v}'";
                        }
                    } else {
                        $parts[] = "-F '{$key}={$value}'";
                    }
                }

                // Handle uploaded files
                foreach ($request->files->all() as $key => $file) {
                    if (is_array($file)) {
                        foreach ($file as $f) {
                            $parts[] = "-F '{$key}=@{$f->getRealPath()};filename={$f->getClientOriginalName()}'";
                        }
                    } else {
                        $parts[] = "-F '{$key}=@{$file->getRealPath()};filename={$file->getClientOriginalName()}'";
                    }
                }

                $data = " " . implode(" \\\n  ", $parts);
            } else {
                // Default: x-www-form-urlencoded
                $payload = http_build_query($request->all());
                if (!empty($payload)) {
                    $data = " --data '" . addslashes($payload) . "'";
                }
            }
        }

        $curl = "curl -X {$method} '" . $url . "' \\\n  " . implode(" \\\n  ", $headers) . $data;

        Log::error("CURL Request:\n" . $curl);
    }

    public static function getQueryLog($sqlQuery, $bindings)
    {
        /** To Get Query and bindings in a readable format */
        // $queryLog = DB::getQueryLog();
        // $lastQuery = end($queryLog);
        // $readyQuery = HelperService::getQueryLog($lastQuery['query'], $lastQuery['bindings']);
        // dd($readyQuery);


        $sql = vsprintf(
            str_replace('?', "'%s'", $sqlQuery),
            collect($bindings)->map(function ($binding) {
                // Handle DateTime objects
                if ($binding instanceof \DateTime) {
                    return $binding->format('Y-m-d H:i:s');
                }
                // Escape strings properly
                return addslashes($binding);
            })->toArray()
        );

        return $sql;
    }


    /**
     * Get the watermark config from the database
     * @return array|false
     */
    public static function getWatermarkConfigStatus(){
        $watermarkConfig = self::getWatermarkConfig();
        if($watermarkConfig){
            return $watermarkConfig['enabled'] == 1 ? true : false;
        }
        return false;
    }
    public static function getWatermarkConfigDecoded(){
        $watermarkConfig = self::getWatermarkConfig();
        return $watermarkConfig;
    }
    private static function getWatermarkConfig()
    {
        try {
            // Cache the watermark config for 24 hours
            return cache()->remember('watermark_config', 86400, function () {
                $watermarkConfig = Setting::where('type', 'watermark_config')->first();
                if($watermarkConfig){
                    return json_decode($watermarkConfig->data, true);
                }
                return [];
            });
        } catch(Exception $e) {
            Log::error('Error getting watermark config: ' . $e->getMessage());
            return false;
        }
    }


    public static function getCustomerDefaultLanguage(){
        $auth = Auth::guard('sanctum');
        if($auth->check()){
            $user = $auth->user();
            $languageCode = $user->default_language;
            if($languageCode == 'en'){
                $languageCode = 'en-new';
            }
            $languageQuery = Language::where(['code' => $languageCode, 'status' => 1]);
            $languageCount = $languageQuery->count();
            $language = $languageQuery->first();
            if($languageCount > 0){
                return $language->code;
            }
            return null;
        }
        return null;
    }

}

?>
