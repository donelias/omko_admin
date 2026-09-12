<?php

namespace App\Services;

use App\Mail\GenericMailTemplate;
use App\Models\AgentAvailability;
use App\Models\AgentProfile;
use App\Models\AgentVerification;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Language;
use App\Models\NumberOtp;
use App\Models\PackageFeature;
use App\Models\PasswordReset;
use App\Models\Projects;
use App\Models\ProjectView;
use App\Models\Property;
use App\Models\PropertyView;
use App\Models\Setting;
use App\Models\Translation;
use App\Models\User;
use App\Models\UserInterest;
use App\Models\SavedSearch;
use App\Models\PropertyAlert;
use App\Models\UserPackage;
use App\Models\UserPackageLimit;
use App\Models\UserPayAsYouGoCredit;
use App\Models\Usertokens;
use App\Models\VerifyCustomer;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Intl\Currencies;

class HelperService
{
    public static $lastConsumedPaymentTransactionId = null;

    public static function currencyCode()
    {
        $currencies = Currencies::getNames();
        $currenciesArray = [];
        foreach ($currencies as $key => $value) {
            $currenciesArray[] = [
                'currency_code' => $key,
                'currency_name' => $value,
            ];
        }

        return $currenciesArray;
    }

    public static function generateOtp()
    {
        return rand(100000, 999999);
    }

    public static function getCurrencyData($code)
    {
        $name = Currencies::getName($code);
        $currencySymbol = Currencies::getSymbol($code);

        return ['code' => $code, 'name' => $name, 'symbol' => $currencySymbol];
    }

    // Generate Token
    public static function generateToken()
    {
        return bin2hex(random_bytes(50)); // Generates a secure random token
    }

    // Store Token
    public static function storeToken($email, $token)
    {
        $expiresAt = now()->addMinutes(60); // Set token to expire after 60 minutes
        PasswordReset::updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'token' => $token,
                'expires_at' => $expiresAt,
            ]
        );

        return true;
    }

    public static function storeOtp($email, $otp)
    {
        $expiresAt = now()->addMinutes(5); // Set token to expire after 60 minutes
        NumberOtp::updateOrCreate(
            // array(
            //     'email' => $email,
            //     'otp' => $otp,
            //     'expire_at' => $expiresAt,
            // ),
            [
                'email' => $email,
            ],
            [
                'otp' => $otp,
                'expire_at' => $expiresAt,
            ]
        );

        return true;
    }

    // Verify Token
    public static function verifyToken($token)
    {
        $record = PasswordReset::where('token', $token)->where('expires_at', '>', now())->first();
        if ($record) {
            return $record->email;
        } else {
            return false;
        }
    }

    // Make Token Expire
    public static function expireToken($email)
    {
        $expiresAt = now(); // Set token to expire after 60 minutes
        PasswordReset::updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'expires_at' => $expiresAt,
            ]
        );

        return true;
    }

    public static function getEmailTemplatesTypes($type = null)
    {
        // Return required data if type is passed
        if ($type) {
            switch ($type) {
                case 'verify_mail':
                    return [
                        'title' => __('Verify Email Account'),
                        'type' => 'verify_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'otp',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'reset_password':
                    return [
                        'title' => __('OTP for Reset Password'),
                        'type' => 'password_reset_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'otp',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'welcome_mail':
                    return [
                        'title' => __('Welcome Mail'),
                        'type' => 'welcome_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'property_status':
                    return [
                        'title' => __('Property status change by admin'),
                        'type' => 'property_status_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'property_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'status',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'reject_reason',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'project_status':
                    return [
                        'title' => __('Project status change by admin'),
                        'type' => 'project_status_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'project_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'status',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'reject_reason',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'property_ads_status':
                    return [
                        'title' => __('Property Advertisement status change by admin'),
                        'type' => 'property_ads_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'property_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'advertisement_status',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'user_status':
                    return [
                        'title' => __('User account active de-active status'),
                        'type' => 'user_status_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'status',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'agent_verification_status':
                    return [
                        'title' => __('Agent Verification Status'),
                        'type' => 'agent_verification_status_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'status',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'user_verification_status':
                    return [
                        'title' => __('User Verification Status'),
                        'type' => 'user_verification_status_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'status',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'new_property_in_category_listing':
                    return [
                        'title' => __('New Property in Category Listing'),
                        'type' => 'new_property_in_category_listing_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'category_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'property_name',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'subscription_expiring_soon':
                    return [
                        'title' => __('Subscription Expiring Soon'),
                        'type' => 'subscription_expiring_soon_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'package_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'subscription_end_date',
                                'is_condition' => false,
                            ],
                        ],
                    ];
                case 'new_appointment_request':
                    return [
                        'title' => __('New Appointment Request'),
                        'type' => 'new_appointment_request_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'property_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'agent_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_status',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_type',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'start_time',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'end_time',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'date',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'notes',
                                'is_condition' => true,
                            ],
                        ],
                    ];
                case 'appointment_status':
                    return [
                        'title' => __('Appointment Status'),
                        'type' => 'appointment_status_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'property_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'agent_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'customer_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_status',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'meeting_type',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'start_time',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'end_time',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'date',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'reason',
                                'is_condition' => true,
                            ],
                        ],
                    ];
                case 'appointment_meeting_type_change':
                    return [
                        'title' => __('Appointment Meeting Type Change'),
                        'type' => 'appointment_meeting_type_change_mail_template',
                        'required_fields' => [
                            [
                                'name' => 'app_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'user_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'property_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'agent_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'customer_name',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'old_meeting_type',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'new_meeting_type',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'start_time',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'end_time',
                                'is_condition' => false,
                            ],
                            [
                                'name' => 'date',
                                'is_condition' => false,
                            ],
                        ],
                    ];
            }
        }

        // Return All if no type is passed
        return [
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
                'title' => __('User Verification Status'),
                'type' => 'user_verification_status',
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
            ],
        ];
    }

    public static function replaceEmailVariables($templateContent, $variables)
    {
        $templateContent = htmlspecialchars_decode($templateContent);
        // First pass A: handle conditional blocks in legacy format {key}...{end_key}
        foreach ($variables as $key => $variable) {
            $startTag = '{'.$key.'}';
            $endTag = "{end_{$key}}";

            if (strpos($templateContent, $startTag) !== false && strpos($templateContent, $endTag) !== false) {
                $pattern = '/'.preg_quote($startTag, '/').'(.*?)'.preg_quote($endTag, '/').'/s';

                if (! empty($variable)) {
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
            $startTagNew = '{start_'.$key.'}';
            $endTagNew = '(end_'.$key.')';
            if (strpos($templateContent, $startTagNew) !== false && strpos($templateContent, $endTagNew) !== false) {
                $patternNew = '/'.preg_quote($startTagNew, '/').'(.*?)'.preg_quote($endTagNew, '/').'/s';

                if (! empty($variable)) {
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
            $placeholder = '{'.$key.'}';
            $templateContent = str_replace($placeholder, (string) $variable, $templateContent);
        }

        return $templateContent;
    }

    public static function sendMail($data, $requiredEmailException = false, $skipQueue = false)
    {
        try {

            // Guard against a missing/empty recipient. Without this, Mail::to('')
            // dispatches a job that fails in the queue worker with
            // "An email must have a 'To', 'Cc', or 'Bcc' header." and is then
            // retried repeatedly, flooding the logs.
            $recipient = $data['email'] ?? null;
            if (empty($recipient) || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                Log::error('Skipped sending mail: missing or invalid recipient email.'.(isset($data['subject']) ? ' Subject: '.$data['subject'] : ''));

                if ($requiredEmailException === true) {
                    DB::rollback();
                    throw new Exception('Missing or invalid recipient email.');
                }

                return;
            }

            $adminMail = env('MAIL_FROM_ADDRESS');
            $companyName = HelperService::getSettingData('company_name');

            // Prepare the Mailable
            $mailable = new GenericMailTemplate($data, $adminMail, $companyName);

            // Optimistically assume queue works if we've never seen it
            if ($skipQueue == true) {
                Mail::to($data['email'])->send($mailable);
            } else {
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
                'MailManager',
            ])) {
                Log::error('Cannot send mail, there is issue with mail configuration.');
            } else {
                $logMessage = 'Send Mail for property feature status changed';
                Log::error($logMessage.' '.$e->getMessage().'---> '.$e->getFile().' At Line : '.$e->getLine());
            }
        }
    }

    public static function getFeatureList($userType = null)
    {
        try {
            $query = Feature::where('status', 1);
            if ($userType) {
                $query->whereIn('user_type', [$userType, 'all']);
            }

            return $query->get();
        } catch (Exception $e) {
            Log::error('Issue in Get Feature list of Helper Service :- '.$e->getMessage());

            return [];
        }
    }

    public static function getSettingData($type, $getRawData = true)
    {
        $settingQueryData = Setting::where('type', $type)->select('type', 'data')->first();
        if ($getRawData == true) {
            return $settingQueryData ? $settingQueryData->getRawOriginal('data') : null;
        }

        return $settingQueryData ? $settingQueryData->data : null;
    }

    public static function getMultipleSettingData(array $types, $raw = false)
    {
        $settingData = Setting::whereIn('type', $types)->get();
        if (! empty($settingData)) {
            $data = [];
            foreach ($settingData as $setting) {

                if ($setting->type == 'default_language') {
                    if ($raw == true) {
                        $data[$setting->type] = $setting->getRawOriginal('data');
                    } else {
                        $data[$setting->type] = $setting->data;
                    }
                } else {
                    $data[$setting->type] = $setting->data;
                }
            }

            return $data ? $data : null;
        }

        return null;
    }

    public static function getOneActivePaymentGateway()
    {
        try {
            $paymentMethodTypes = ['stripe_gateway', 'razorpay_gateway', 'paystack_gateway', 'paypal_gateway', 'flutterwave_status', 'cashfree_gateway'];
            $settingsData = Setting::whereIn('type', $paymentMethodTypes)->get();
            foreach ($settingsData as $key => $setting) {
                if ($setting->data == 1) {
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

                case 'mock':
                    return ['payment_method' => 'mock', 'mock_currency' => 'USD'];

                default:
                    return false;
            }
        } catch (Exception $e) {
            Log::error('Issue in Get Payment Details function of Helper Service :- '.$e->getMessage());

            return false;
        }
    }

    public static function changeEnv($updateData = []): bool
    {
        if (count($updateData) > 0) {
            // Read .env-file
            $env = file_get_contents(base_path().'/.env');
            // Split string on every " " and write into array
            $env = preg_split('/\r\n|\r|\n/', $env);
            $env_array = [];
            foreach ($env as $env_value) {
                if (empty($env_value)) {
                    // Add and Empty Line
                    $env_array[] = '';

                    continue;
                }

                $entry = explode('=', $env_value, 2);
                $env_array[$entry[0]] = $entry[0].'="'.str_replace('"', '', $entry[1]).'"';
            }

            foreach ($updateData as $key => $value) {
                $env_array[$key] = $key.'="'.str_replace('"', '', $value).'"';
            }
            // Turn the array back to a String
            $env = implode("\n", $env_array);

            // And overwrite the .env with the new data
            file_put_contents(base_path().'/.env', $env);

            return true;
        }

        return false;
    }

    public static function getAllActivePackageIds($userId, $role = null)
    {
        $packageIds = UserPackage::where('user_id', $userId)
            ->forRole()
            ->onlyActive()
            ->pluck('package_id');

        return $packageIds;
    }

    public static function getActivePackage($userId, $packageId, $role = null)
    {
        $userPackages = UserPackage::where(['user_id' => $userId, 'package_id' => $packageId])
        ->forRole()    
        ->onlyActive()
            ->first();

        return $userPackages;
    }

    public static function getFeatureId($type)
    {
        try {
            $featureTypes = array_column(config('constants.FEATURES'), 'TYPE');
            if (! in_array($type, $featureTypes)) {
                Log::error('Type not allowed in getFeatureId function of HelperService');

                return false;
            }

            return Feature::where('type', $type)->pluck('id')->first();
        } catch (Exception $e) {
            Log::error('Issue in Get Feature ID HelperService Function => '.$e->getMessage());

            return false;
        }
    }

    public static function consumePayAsYouGoCredit($payAsYouGoType, $loggedInUserData, $getPackageDataReturn = false)
    {
        if ($payAsYouGoType) {
            $payAsYouGoCredit = UserPayAsYouGoCredit::where('user_id', $loggedInUserData->id)
                ->where('used', 0)
                ->whereHas('pay_as_you_go', function ($query) use ($payAsYouGoType) {
                    $query->where('type', $payAsYouGoType);
                })->first();

            if ($payAsYouGoCredit) {
                $payAsYouGoCredit->used = 1;
                $payAsYouGoCredit->save();

                self::$lastConsumedPaymentTransactionId = $payAsYouGoCredit->payment_transaction_id;

                if ($getPackageDataReturn) {
                    return (object) ['id' => 0, 'name' => 'Pay As You Go', 'is_pay_as_you_go' => true];
                } else {
                    return 'pay_as_you_go';
                }
            }
        }

        return false;
    }

    public static function updatePackageLimit($type, $getPackageDataReturn = false, $fallbackToPayAsYouGo = false, $userActiveRole = null)
    {
        try {
            $featureTypes = array_column(config('constants.FEATURES'), 'TYPE');
            if (! in_array($type, $featureTypes)) {
                ApiResponseService::validationError('Invalid Feature Type');
            }

            $payAsYouGoType = ($type == config('constants.FEATURES.PROPERTY_LIST.TYPE')) ? 'property' : (($type == config('constants.FEATURES.PROJECT_LIST.TYPE')) ? 'project' : null);

            $featureId = HelperService::getFeatureId($type);

            if (collect($featureId)->isEmpty()) {
                ApiResponseService::validationError('Invalid Feature Type');
            }

            $loggedInUserData = Auth::user();
            if (! $loggedInUserData) {
                ApiResponseService::validationError('Package not found');
            }

            $packagesIds = HelperService::getAllActivePackageIds($loggedInUserData->id, $userActiveRole);
            if (collect($packagesIds)->isEmpty()) {
                if ($fallbackToPayAsYouGo && $res = self::consumePayAsYouGoCredit($payAsYouGoType, $loggedInUserData, $getPackageDataReturn)) {
                    return $res;
                }
                Log::warning('Package not available for user', [
                    'user_id' => $loggedInUserData->id ?? null,
                    'role' => $userActiveRole,
                    'packages_ids' => $packagesIds->toArray(),
                    'feature_type' => $type,
                ]);
                ApiResponseService::validationError('Package not available');
            }
            $userPackageIds = UserPackage::whereIn('package_id', $packagesIds)->where('user_id', $loggedInUserData->id)->pluck('id');

            $packageFeatureQuery = PackageFeature::where('feature_id', $featureId)->whereIn('package_id', $packagesIds);
            $packageFeatureIds = $packageFeatureQuery->clone()->pluck('id');

            if (collect($packageFeatureIds)->isEmpty()) {
                if ($fallbackToPayAsYouGo && $res = self::consumePayAsYouGoCredit($payAsYouGoType, $loggedInUserData, $getPackageDataReturn)) {
                    return $res;
                }
                Log::warning('No package features found for required feature', [
                    'user_id' => $loggedInUserData->id ?? null,
                    'role' => $userActiveRole,
                    'package_ids' => $packagesIds->toArray(),
                    'feature_id' => $featureId,
                    'feature_type' => $type,
                ]);
                ApiResponseService::validationError('Package not available');
            }

            $packageFeatures = $packageFeatureQuery->clone()->with(['user_package_limits' => function ($query) use ($userPackageIds) {
                $query->whereIn('user_package_id', $userPackageIds);
            }, 'package'])->get();

            foreach ($packageFeatures as $packageFeatureData) {
                if ($packageFeatureData->limit_type == 'unlimited') {
                    if ($getPackageDataReturn == true) {
                        return $packageFeatureData->package;
                    } else {
                        return true;
                    }
                }
                if ($packageFeatureData->user_package_limits) {
                    foreach ($packageFeatureData->user_package_limits as $package) {
                        if ($package->total_limit > $package->used_limit) {
                            // Deduct one limit
                            $package->used_limit += 1;
                            $package->save();
                            if ($getPackageDataReturn == true) {
                                return $packageFeatureData->package;
                            } else {
                                return true;
                            }
                        }
                    }
                }
            }

            if ($fallbackToPayAsYouGo && $res = self::consumePayAsYouGoCredit($payAsYouGoType, $loggedInUserData, $getPackageDataReturn)) {
                return $res;
            }
            Log::warning('Package limit not available', [
                'user_id' => $loggedInUserData->id ?? null,
                'role' => $userActiveRole,
                'package_ids' => $packagesIds->toArray(),
                'feature_id' => $featureId,
                'feature_type' => $type,
                'package_features_count' => count($packageFeatures ?? []),
            ]);
            ApiResponseService::validationError('Limit Not Available');
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Issue in update package limit helper function');
        }
    }

    public static function checkPackageLimit($type, $getCheckDataInReturn = false, $fallbackToPayAsYouGo = false, $userActiveRole = null)
    {
        try {
            $packageAvailable = false;
            $featureAvailable = false;
            $limitAvailable = false;
            $loggedInUserData = null;
            if (Auth::guard('sanctum')->check()) {
                $loggedInUserData = Auth::guard('sanctum')->user();
            }
            $featureId = HelperService::getFeatureId($type);

            if (! empty($featureId)) {
                if ($loggedInUserData) {
                    $packageIds = HelperService::getAllActivePackageIds($loggedInUserData->id, $userActiveRole);
                }
                if (isset($packageIds) && collect($packageIds)->isNotEmpty()) {
                    $packageAvailable = true;
                    $userPackages = UserPackage::where('user_id', $loggedInUserData->id)
                        ->where('role_context', $userActiveRole)
                        ->whereIn('package_id', $packageIds)
                        ->get();
                    $userPackageIds = $userPackages->pluck('id');

                    $packageFeatureQuery = PackageFeature::where('feature_id', $featureId)->whereIn('package_id', $packageIds);
                    $getPackageFeatureData = $packageFeatureQuery->clone()->get();
                    if (collect($getPackageFeatureData)->isNotEmpty()) {
                        $featureAvailable = true;
                        foreach ($getPackageFeatureData as $packageFeatureData) {
                            if ($packageFeatureData->limit_type == 'unlimited') {
                                $limitAvailable = true;
                            } elseif ($packageFeatureData->limit_type == 'limited') {
                                $packageFeatureIds = $packageFeatureQuery->clone()->pluck('id');
                                $userPackageLimit = UserPackageLimit::whereIn('user_package_id', $userPackageIds)->whereIn('package_feature_id', $packageFeatureIds)->get();
                                if (collect($userPackageLimit)->isNotEmpty()) {
                                    foreach ($userPackageLimit as $package) {
                                        if ($package->total_limit > $package->used_limit) {
                                            $limitAvailable = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($fallbackToPayAsYouGo && ! $limitAvailable && $loggedInUserData) {
                $payAsYouGoType = ($type == config('constants.FEATURES.PROPERTY_LIST.TYPE')) ? 'property' : (($type == config('constants.FEATURES.PROJECT_LIST.TYPE')) ? 'project' : null);
                if ($payAsYouGoType) {
                    $hasPayAsYouGo = UserPayAsYouGoCredit::where('user_id', $loggedInUserData->id)
                        ->where('used', 0)
                        ->whereHas('pay_as_you_go', function ($query) use ($payAsYouGoType) {
                            $query->where('type', $payAsYouGoType);
                        })->exists();

                    if ($hasPayAsYouGo) {
                        $packageAvailable = true;
                        $featureAvailable = true;
                        $limitAvailable = true;
                    }
                }
            }

            if ($getCheckDataInReturn == true) {
                return [
                    'package_available' => $packageAvailable,
                    'feature_available' => $featureAvailable,
                    'limit_available' => $limitAvailable,
                ];
            } else {
                if ($packageAvailable) {
                    if ($featureAvailable) {
                        if ($limitAvailable) {
                            return true;
                        } else {
                            ApiResponseService::validationError('Limit Not Available');
                        }
                    } else {
                        ApiResponseService::validationError('Feature Not Available');
                    }
                } else {
                    ApiResponseService::validationError('Package Not Available');
                }
            }
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Issue in check package limit helper function');
        }
    }

    /**
     * FASE 8 (T6 restante) — Acceso pagado a leads (crm_leads_access).
     * Revisa los planes activos del usuario (roles agent/agencia) que incluyan
     * la feature crm_leads_access y devuelve estado + créditos restantes.
     */
    public static function checkLeadAccessLimit($userId)
    {
        $roles = ['agent', 'agencia'];
        $featureId = Feature::where('type', config('constants.FEATURES.CRM_LEADS_ACCESS.TYPE'))->value('id');

        if (! $featureId) {
            return ['available' => false, 'unlimited' => false, 'remaining' => null];
        }

        $packageIds = UserPackage::where('user_id', $userId)
            ->whereIn('role_context', $roles)
            ->onlyActive()
            ->pluck('package_id');

        if ($packageIds->isEmpty()) {
            return ['available' => false, 'unlimited' => false, 'remaining' => 0];
        }

        $packageFeatures = PackageFeature::where('feature_id', $featureId)
            ->whereIn('package_id', $packageIds)
            ->get();

        if ($packageFeatures->isEmpty()) {
            return ['available' => false, 'unlimited' => false, 'remaining' => 0];
        }

        $userPackageIds = UserPackage::where('user_id', $userId)
            ->whereIn('role_context', $roles)
            ->onlyActive()
            ->whereIn('package_id', $packageIds)
            ->pluck('id');

        foreach ($packageFeatures as $packageFeature) {
            if ($packageFeature->limit_type === 'unlimited') {
                return ['available' => true, 'unlimited' => true, 'remaining' => null];
            }

            $userPackageLimit = UserPackageLimit::where('package_feature_id', $packageFeature->id)
                ->whereIn('user_package_id', $userPackageIds)
                ->orderByDesc('total_limit')
                ->first();

            if ($userPackageLimit && $userPackageLimit->total_limit > $userPackageLimit->used_limit) {
                return [
                    'available' => true,
                    'unlimited' => false,
                    'remaining' => $userPackageLimit->total_limit - $userPackageLimit->used_limit,
                    'user_package_limit_id' => $userPackageLimit->id,
                ];
            }
        }

        return ['available' => false, 'unlimited' => false, 'remaining' => 0];
    }

    /**
     * Consume un crédito de acceso a contacto de leads. Devuelve los créditos
     * restantes (null si el plan es unlimited). Si no hay crédito disponible
     * devuelve 0 sin cambiar nada.
     */
    public static function consumeLeadAccess($userId)
    {
        $access = self::checkLeadAccessLimit($userId);
        if (! $access['available']) {
            return (int) ($access['remaining'] ?? 0);
        }
        if ($access['unlimited']) {
            return null;
        }
        if (empty($access['user_package_limit_id'])) {
            return 0;
        }

        return DB::transaction(function () use ($access) {
            $userPackageLimit = UserPackageLimit::where('id', $access['user_package_limit_id'])
                ->lockForUpdate()
                ->first();

            if (! $userPackageLimit || $userPackageLimit->total_limit <= $userPackageLimit->used_limit) {
                return 0;
            }

            $userPackageLimit->used_limit += 1;
            $userPackageLimit->save();

            return $userPackageLimit->total_limit - $userPackageLimit->used_limit;
        });
    }

    public static function incrementTotalClick($type, $id = null, $slugId = null)
    {
        if (Auth::guard('sanctum')->check()) {
            $loggedInUserData = Auth::guard('sanctum')->user();
            $currentDate = Carbon::now()->format('Y-m-d');

            if ($type === 'project') {
                if (! empty($id)) {
                    $project = Projects::where('id', $id)->first();
                } else {
                    $project = Projects::where('slug_id', $slugId)->first();
                }

                if (! $project) {
                    return true;
                }

                $view = ProjectView::updateOrCreate(
                    ['project_id' => $project->id, 'date' => $currentDate, 'user_id' => $loggedInUserData->id],
                    ['views' => 1]
                );
                if ($view->wasRecentlyCreated) {
                    $project->increment('total_click');
                }
                // dd($project->total_click);
            } elseif ($type === 'property') {
                if (! empty($id)) {
                    $property = Property::where('id', $id)->first();
                } else {
                    $property = Property::where('slug_id', $slugId)->first();
                }

                if (! $property) {
                    return true;
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

    protected static ?string $cachedTimezone = null;

    // Get cached timezone value
    public static function toAppTimezoneValue(): string
    {
        if (self::$cachedTimezone === null) {
            self::$cachedTimezone = self::getSettingData('timezone') ?: 'UTC';
        }

        return self::$cachedTimezone;
    }

    // Convert a UTC datetime to app timezone
    public static function toAppTimezone($dateTime)
    {
        $timezone = self::toAppTimezoneValue();

        if (! $dateTime instanceof Carbon) {
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

    public static function getFeatureNames()
    {
        $featureNames = [
            config('constants.FEATURES.PROPERTY_LIST'),
            config('constants.FEATURES.PROPERTY_FEATURE'),
            config('constants.FEATURES.PROJECT_LIST'),
            config('constants.FEATURES.PROJECT_FEATURE'),
            config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL'),
            config('constants.FEATURES.PREMIUM_PROPERTIES'),
            config('constants.FEATURES.PREMIUM_PROJECTS'),
            config('constants.FEATURES.AGENT_WATERMARK'),
        ];

        return $featureNames;
    }

    /**
     * Get the homepage section types
     *
     * @return array
     */
    public static function getHomepageSectionTypes()
    {
        $homepageSectionTypes = [
            config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.AGENTS_LIST_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.ARTICLES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.CATEGORIES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.FAQS_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROPERTIES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.FEATURED_PROJECTS_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.MOST_LIKED_PROPERTIES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.MOST_VIEWED_PROPERTIES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.NEARBY_PROPERTIES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.PROJECTS_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROJECTS_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROJECTS_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.PREMIUM_PROPERTIES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.USER_RECOMMENDATIONS_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_BY_CITIES_SECTION.TITLE')),
            config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TYPE') => trans(config('constants.HOMEPAGE_SECTION_TYPES.PROPERTIES_ON_MAP_SECTION.TITLE')),
        ];

        return $homepageSectionTypes;
    }

    public static function AlertUserForNewListing($propertyId)
    {
        try {
            $property = Property::where('id', $propertyId)->with('category:id,category')->first();
            $categoryId = implode(',', [$property->category_id]);

            $emailTypeData = self::getEmailTemplatesTypes('new_property_in_category_listing');
            $templateData = self::getSettingData($emailTypeData['type']);
            if (empty($templateData)) {
                $templateData = 'New Property in Category Listing';
            }

            $variables = [
                'app_name' => env('APP_NAME', 'omko'),
                'category_name' => $property->category->category,
                'property_name' => $property->title,
            ];
            $userInterests = UserInterest::whereRaw('FIND_IN_SET(?, category_ids)', [$categoryId])->get();
            $userIds = $userInterests->pluck('user_id');
            $userData = Customer::whereIn('id', $userIds)->select('id', 'name', 'email')->get();
            foreach ($userData as $user) {
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
            if (! empty($userFCMTokens)) {
                $translatedTitle = 'New Property Alert';
                $translatedBody = 'New Property in Your Category Selected';
                $propertyRoleContext = Property::where('id', $propertyId)->value('role_context') ?? 'user';
                $fcmMsg = [
                    'title' => $translatedTitle,
                    'message' => $translatedBody,
                    'type' => 'new_property_listing',
                    'body' => $translatedBody,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                    'property_id' => (string) $propertyId,
                    'role_context' => $propertyRoleContext,

                ];
                send_push_notification($userFCMTokens, $fcmMsg);
            }

            // Also trigger saved search alerts
            \App\Jobs\CheckSavedSearchAlerts::dispatch();

            return true;
        } catch (Exception $e) {
            Log::error('Issue in alert user for new listing helper function: '.$e->getMessage());
        }
    }

    public static function getActiveLanguages($specificSelect = null, $withEnglish = false)
    {
        try {
            $englishCode = ['en', 'en-new'];
            $languageQuery = Language::where('status', 1);
            if (! $withEnglish) {
                $languageQuery = $languageQuery->whereNotIn('code', $englishCode);
            }
            if (! empty($specificSelect)) {
                $languageQuery = $languageQuery->select($specificSelect);
            }
            // Ensure default language (from settings) appears first
            $defaultCode = self::getSettingData('default_language');
            if (! empty($defaultCode)) {
                $languageQuery = $languageQuery->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$defaultCode]);
            }

            return $languageQuery->get();
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Issue in get active languages helper function');
        }
    }

    public static function storeTranslations($translations)
    {
        try {
            $storeTranslations = [];
            foreach ($translations as $translation) {
                if (isset($translation['language_id']) && ! empty($translation['language_id']) && isset($translation['value']) && ! empty($translation['value'])) {
                    $storeTranslations[] = [
                        'id' => $translation['id'] ?? null,
                        'translatable_id' => $translation['translatable_id'],
                        'translatable_type' => $translation['translatable_type'],
                        'language_id' => $translation['language_id'],
                        'key' => $translation['key'],
                        'value' => $translation['value'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (! empty($storeTranslations)) {
                Translation::upsert($storeTranslations, ['id']);
            }
        } catch (Exception $e) {
            ApiResponseService::logErrorResponse($e, 'Issue in store translations helper function');
        }
    }

    public static function getTranslatedData($dataObject, $defaultData, $key)
    {
        $languageCode = request()->header('Content-Language') ?? app()->getLocale();

        if (empty($languageCode)) {
            return $defaultData;
        }

        if ($languageCode == 'en') {
            $languageCode = 'en-new';
        }

        // Cache language ID lookup
        $languageId = cache()->remember("language_id_{$dataObject->id}_{$key}_{$languageCode}", 3600, function () use ($languageCode) {
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

    public static function runQueue()
    {
        $client = new Client([
            'timeout' => 10,
            'verify' => false, // for self-signed SSL, optional
        ]);

        $url = URL::route('run.queue');
        $response = $client->request('GET', $url);

        if ($response->getStatusCode() === 200) {
            return true;
        }

        return false;
    }

    public static function getCurlRequest()
    {
        $request = request();

        $method = strtoupper($request->method());
        $url = $request->fullUrl();
        $headers = [];

        foreach ($request->headers->all() as $key => $values) {
            foreach ($values as $value) {
                $headers[] = "-H '".$key.': '.$value."'";
            }
        }

        $data = '';

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $request->header('Content-Type');

            if (str_contains($contentType, 'application/json')) {
                // JSON payload
                $payload = $request->all();
                $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $data = " --data '".addslashes($payloadJson)."'";
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

                $data = ' '.implode(" \\\n  ", $parts);
            } else {
                // Default: x-www-form-urlencoded
                $payload = http_build_query($request->all());
                if (! empty($payload)) {
                    $data = " --data '".addslashes($payload)."'";
                }
            }
        }

        $curl = "curl -X {$method} '".$url."' \\\n  ".implode(" \\\n  ", $headers).$data;

        Log::error("CURL Request:\n".$curl);
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
     *
     * @return array|false
     */
    public static function getWatermarkConfigStatus()
    {
        $watermarkConfig = self::getWatermarkConfig();
        if ($watermarkConfig) {
            return $watermarkConfig['enabled'] == 1 ? true : false;
        }

        return false;
    }

    public static function getWatermarkConfigDecoded()
    {
        $watermarkConfig = self::getWatermarkConfig();

        return $watermarkConfig;
    }

    public static function checkAgentWatermarkFeature($agentId): array
    {
        try {
            $packageAvailable = false;
            $featureAvailable = false;
            $limitAvailable = false;
            $featureId = self::getFeatureId(config('constants.FEATURES.AGENT_WATERMARK.TYPE'));

            if (empty($agentId) || empty($featureId)) {
                return [
                    'package_available' => $packageAvailable,
                    'feature_available' => $featureAvailable,
                    'limit_available' => $limitAvailable,
                ];
            }

            $packageIds = UserPackage::where('user_id', $agentId)
                ->where('role_context', 'agent')
                ->onlyActive()
                ->pluck('package_id');

            if ($packageIds->isEmpty()) {
                return [
                    'package_available' => false,
                    'feature_available' => false,
                    'limit_available' => false,
                ];
            }

            $packageAvailable = true;
            $userPackageIds = UserPackage::where('user_id', $agentId)
                ->where('role_context', 'agent')
                ->whereIn('package_id', $packageIds)
                ->onlyActive()
                ->pluck('id');

            $packageFeatures = PackageFeature::where('feature_id', $featureId)
                ->whereIn('package_id', $packageIds)
                ->with(['user_package_limits' => function ($query) use ($userPackageIds) {
                    $query->whereIn('user_package_id', $userPackageIds);
                }])
                ->get();

            if ($packageFeatures->isNotEmpty()) {
                $featureAvailable = true;
                foreach ($packageFeatures as $packageFeature) {
                    if ($packageFeature->limit_type == 'unlimited') {
                        $limitAvailable = true;
                        break;
                    }

                    foreach ($packageFeature->user_package_limits as $limit) {
                        if ($limit->total_limit > $limit->used_limit) {
                            $limitAvailable = true;
                            break 2;
                        }
                    }
                }
            }

            return [
                'package_available' => $packageAvailable,
                'feature_available' => $featureAvailable,
                'limit_available' => $limitAvailable,
            ];
        } catch (Exception $e) {
            Log::error('Error checking agent watermark feature: '.$e->getMessage());

            return [
                'package_available' => false,
                'feature_available' => false,
                'limit_available' => false,
            ];
        }
    }

    public static function agentHasWatermarkFeature($agentId): bool
    {
        $feature = self::checkAgentWatermarkFeature($agentId);

        log::info('Agent Watermark Feature Check', ['agent_id' => $agentId, 'feature_check_result' => $feature]);

        return ! empty($feature['feature_available']) && ! empty($feature['limit_available']);
    }

    public static function getAgentWatermarkConfig($agentId): array
    {
        try {
            $agentProfile = AgentProfile::where('customer_id', $agentId)->first();
            if (! $agentProfile) {
                Log::warning('Watermark: no AgentProfile found', ['agent_id' => $agentId]);

                return [];
            }

            $watermarkImage = $agentProfile->getRawOriginal('watermark_image');
            $watermarkPath = $watermarkImage ? storage_path('app/public/'.config('global.AGENT_WATERMARK_IMG_PATH').$watermarkImage) : null;
            $fileExists = $watermarkPath ? file_exists($watermarkPath) : false;

            Log::info('Watermark: agent config resolved', [
                'agent_id'        => $agentId,
                'watermark_enabled' => (bool) $agentProfile->watermark_enabled,
                'watermark_image' => $watermarkImage,
                'expected_path'   => $watermarkPath,
                'file_exists'     => $fileExists,
            ]);

            return [
                'source' => 'agent',
                'enabled' => (bool) $agentProfile->watermark_enabled,
                'watermark_image' => $watermarkImage,
                'watermark_path' => ($watermarkPath && $fileExists) ? $watermarkPath : null,
                'opacity' => $agentProfile->watermark_opacity ?? 25,
                'size' => $agentProfile->watermark_size ?? 10,
                'style' => $agentProfile->watermark_style ?? 'tile',
                'position' => $agentProfile->watermark_position ?? 'center',
                'rotation' => $agentProfile->watermark_rotation ?? 30,
            ];
        } catch (Exception $e) {
            Log::error('Watermark: error getting agent config: '.$e->getMessage());

            return [];
        }
    }

    public static function resolveListingWatermarkConfig($agentId = null): array
    {
        if (! empty($agentId) && self::agentHasWatermarkFeature($agentId)) {
            $agentWatermarkConfig = self::getAgentWatermarkConfig($agentId);

            Log::info('Watermark: resolving for agent', [
                'agent_id'       => $agentId,
                'enabled'        => $agentWatermarkConfig['enabled'] ?? false,
                'watermark_path' => $agentWatermarkConfig['watermark_path'] ?? null,
                'result'         => (! empty($agentWatermarkConfig['enabled']) && ! empty($agentWatermarkConfig['watermark_path'])) ? 'agent_applied' : 'falling_back_to_admin',
            ]);

            if (! empty($agentWatermarkConfig['enabled']) && ! empty($agentWatermarkConfig['watermark_path'])) {
                return $agentWatermarkConfig;
            }
            // Agent has feature but watermark not configured/enabled — fall through to admin watermark.
        }

        Log::info('Watermark: checking admin config', ['agent_id' => $agentId]);

        $adminWatermarkConfig = self::getWatermarkConfigDecoded();
        if (! empty($adminWatermarkConfig) && ! empty($adminWatermarkConfig['enabled'])) {
            $adminWatermarkConfig['source'] = 'admin';
            $adminWatermarkConfig['watermark_path'] = self::resolveAdminWatermarkPath($adminWatermarkConfig);

            if (! empty($adminWatermarkConfig['watermark_path'])) {
                return $adminWatermarkConfig;
            }
        }

        return [];
    }

    private static function resolveAdminWatermarkPath(array $watermarkConfig): ?string
    {
        $watermarkImage = $watermarkConfig['watermark_image'] ?? null;
        $watermarkPath = $watermarkImage ? public_path('assets/images/logo/'.$watermarkImage) : null;

        if ($watermarkPath && file_exists($watermarkPath)) {
            return $watermarkPath;
        }

        $companyLogo = self::getSettingData('company_logo');
        if ($companyLogo) {
            $watermarkPath = public_path('assets/images/logo/'.$companyLogo);
            if (file_exists($watermarkPath)) {
                return $watermarkPath;
            }
        }

        $watermarkPath = public_path('assets/images/logo/logo.png');

        return file_exists($watermarkPath) ? $watermarkPath : null;
    }

    private static function getWatermarkConfig()
    {
        try {
            // Cache the watermark config for 24 hours
            return cache()->remember('watermark_config', 86400, function () {
                $watermarkConfig = Setting::where('type', 'watermark_config')->first();
                if ($watermarkConfig) {
                    return json_decode($watermarkConfig->data, true);
                }

                return [];
            });
        } catch (Exception $e) {
            Log::error('Error getting watermark config: '.$e->getMessage());

            return false;
        }
    }

    public static function getCustomerDefaultLanguage()
    {
        $auth = Auth::guard('sanctum');
        if ($auth->check()) {
            $user = $auth->user();
            $languageCode = $user->default_language;
            if ($languageCode == 'en') {
                $languageCode = 'en-new';
            }
            $languageQuery = Language::where(['code' => $languageCode, 'status' => 1]);
            $languageCount = $languageQuery->count();
            $language = $languageQuery->first();
            if ($languageCount > 0) {
                return $language->code;
            }

            return null;
        }

        return null;
    }

    public static function getPackageDuration($package)
    {
        if ($package->list_duration_type == 'Custom') {
            return $package->custom_duration;
        } elseif ($package->list_duration_type == 'Package' || $package->list_duration_type === null) {
            return $package->duration / 24; // Duration is stored in hours
        } elseif ($package->list_duration_type == 'Standard') {
            return 30; // Standard 30 days
        }
    }

    public static function calculateExpirationDate($userId)
    {
        if ($userId == 0) { // Admin
            return null; // Or some long duration? Admin listings might not expire? Let's say null for now or checking logic.

            // Actually admin listings usually don't expire, or follow standard.
            // If added_by is 0 (Admin), they might not have a package.
            // So if $userId is 0, we can return null (never expire) or a default.
            // Let's return null for admin and handle it in expiration logic (if null, don't expire).
            return null;
        }

        $activePackage = UserPackage::where('user_id', $userId)->onlyActive()->forRole()->first();
        if ($activePackage) {
            $package = $activePackage->package;
            $duration = self::getPackageDuration($package);

            return Carbon::now()->addDays($duration);
        }

        // return Carbon::now()->addDays(30); // Default fallback if no package found

    }

    public static function checkPackageLimitExists($loggedInUserId, $packageId, $userActiveRole)
    {
        $hasActivePackage = UserPackage::where(['user_id' => $loggedInUserId, 'package_id' => $packageId, 'role_context' => $userActiveRole])->with('user_package_limits')->orderBy('id', 'desc')->onlyActive()->first();
        $isAllFeatureLimitExits = true;
        if (! empty($hasActivePackage)) {
            if (! empty($hasActivePackage->user_package_limits)) {
                foreach ($hasActivePackage->user_package_limits as $userPackageLimit) {
                    if ($userPackageLimit->is_limit_over) {
                        $isAllFeatureLimitExits = false;
                        break;
                    }
                }
            }
        } else {
            $isAllFeatureLimitExits = false;
        }

        return $isAllFeatureLimitExits;
    }

    public static function checkUserPurchasedPackage($loggedInUserId, $packageId)
    {
        return UserPackage::where(['user_id' => $loggedInUserId, 'package_id' => $packageId])->forRole()->exists();
    }

    public static function applyLocationFilterToListings($query, $latitude, $longitude, $radius)
    {
        if (! $latitude || ! $longitude) {
            return clone $query;
        }
        $locationQuery = clone $query;
        if ($radius) {
            $locationQuery->selectRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance',
                [$latitude, $longitude, $latitude]
            )
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                ->having('distance', '<', $radius);
        } else {
            $locationQuery->where(['latitude' => $latitude, 'longitude' => $longitude]);
        }

        return $locationQuery;
    }

    public static function getAppointmentCountForTimeSlot($agentId, $dayOfWeek, $startTime, $endTime, $agentTimezone)
    {
        try {
            // Get the current week's date range for the specific day of week
            $today = Carbon::now()->setTimezone($agentTimezone);
            $startOfWeek = $today->copy()->startOfWeek();
            $endOfWeek = $today->copy()->endOfWeek();

            // Find the specific day of week
            $dayMap = [
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
                'sunday' => 0,
            ];

            $targetDay = $dayMap[strtolower($dayOfWeek)];
            $targetDate = $startOfWeek->copy()->addDays($targetDay);

            // Convert time slot to UTC for database query
            $slotStartAgent = Carbon::parse($targetDate->format('Y-m-d').' '.$startTime, $agentTimezone);
            $slotEndAgent = Carbon::parse($targetDate->format('Y-m-d').' '.$endTime, $agentTimezone);
            $slotStartUtc = $slotStartAgent->setTimezone('UTC')->toDateTimeString();
            $slotEndUtc = $slotEndAgent->setTimezone('UTC')->toDateTimeString();

            // Count appointments that overlap with this time slot
            $count = Appointment::where('agent_id', $agentId)
                ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                ->where(function ($query) use ($slotStartUtc, $slotEndUtc) {
                    $query->where(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment starts within the slot
                        $q->where('start_at', '>=', $slotStartUtc)
                            ->where('start_at', '<', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment ends within the slot
                        $q->where('end_at', '>', $slotStartUtc)
                            ->where('end_at', '<=', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment completely contains the slot
                        $q->where('start_at', '<=', $slotStartUtc)
                            ->where('end_at', '>=', $slotEndUtc);
                    });
                })
                ->count();

            return $count;
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function getAppointmentCountForExtraTimeSlot($agentId, $date, $startTime, $endTime, $agentTimezone)
    {
        try {
            // Convert time slot to UTC for database query
            $slotStartAgent = Carbon::parse($date.' '.$startTime, $agentTimezone);
            $slotEndAgent = Carbon::parse($date.' '.$endTime, $agentTimezone);
            $slotStartUtc = $slotStartAgent->setTimezone('UTC')->toDateTimeString();
            $slotEndUtc = $slotEndAgent->setTimezone('UTC')->toDateTimeString();

            // Count appointments that overlap with this time slot
            $count = Appointment::where('agent_id', $agentId)
                ->whereIn('status', ['pending', 'confirmed', 'rescheduled'])
                ->where(function ($query) use ($slotStartUtc, $slotEndUtc) {
                    $query->where(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment starts within the slot
                        $q->where('start_at', '>=', $slotStartUtc)
                            ->where('start_at', '<', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment ends within the slot
                        $q->where('end_at', '>', $slotStartUtc)
                            ->where('end_at', '<=', $slotEndUtc);
                    })->orWhere(function ($q) use ($slotStartUtc, $slotEndUtc) {
                        // Appointment completely contains the slot
                        $q->where('start_at', '<=', $slotStartUtc)
                            ->where('end_at', '>=', $slotEndUtc);
                    });
                })
                ->count();

            return $count;
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function validateTimeSlotOverlaps($schedule)
    {
        $dayGroups = [];

        // Group by day
        foreach ($schedule as $item) {
            $day = $item['day'];
            if (! isset($dayGroups[$day])) {
                $dayGroups[$day] = [];
            }
            $dayGroups[$day][] = $item;
        }

        // Check for overlaps within each day
        foreach ($dayGroups as $day => $slots) {
            for ($i = 0; $i < count($slots); $i++) {
                for ($j = $i + 1; $j < count($slots); $j++) {
                    if (self::timeSlotsOverlap($slots[$i], $slots[$j])) {
                        ApiResponseService::validationError("Time slots overlap on {$day}: {$slots[$i]['start_time']}-{$slots[$i]['end_time']} and {$slots[$j]['start_time']}-{$slots[$j]['end_time']}");
                    }
                }
            }
        }
    }

    public static function validateExistingTimeSlotOverlaps($schedule, $agentData, $deletedIds)
    {
        // Get existing time slots for the agent (excluding those being deleted)
        $timezone = $agentData->getTimezone(true);
        $existingSlots = AgentAvailability::where('agent_id', $agentData->id)
            ->whereNotIn('id', $deletedIds)
            ->get()
            ->groupBy('day_of_week');

        foreach ($schedule as $newSlot) {
            $day = $newSlot['day'];

            if (isset($existingSlots[$day])) {
                foreach ($existingSlots[$day] as $existingSlot) {
                    $existingSlotArray = [
                        'start_time' => Carbon::parse($existingSlot->start_time, $timezone)->setTimezone('UTC')->format('H:i'),
                        'end_time' => Carbon::parse($existingSlot->end_time, $timezone)->setTimezone('UTC')->format('H:i'),
                    ];

                    if (self::timeSlotsOverlap($newSlot, $existingSlotArray)) {
                        if ((isset($newSlot['id']) && ! empty($newSlot['id'])) && $newSlot['id'] == $existingSlot->id) {
                            continue;
                        }
                        ApiResponseService::validationError("Time slot overlaps with existing schedule on {$day}: {$newSlot['start_time']}-{$newSlot['end_time']} overlaps with {$existingSlot->start_time}-{$existingSlot->end_time}");
                    }
                }
            }
        }
    }

    private static function timeSlotsOverlap($slot1, $slot2)
    {
        $start1 = strtotime($slot1['start_time']);
        $end1 = strtotime($slot1['end_time']);
        $start2 = strtotime($slot2['start_time']);
        $end2 = strtotime($slot2['end_time']);

        // Two time slots overlap if one starts before the other ends
        return $start1 < $end2 && $start2 < $end1;
    }

    public static function getAutoApproveStatus($loggedInUserId, $role)
    {
        $autoApproveStatus = false;
        $userData = Customer::find($loggedInUserId);

        if ($userData) {

            if ($role == 'agent') {
                // Agent context: check agent-specific auto-approve setting and their agent verification status
                $status = self::getSettingData('agent_auto_approve');
                $autoApproveStatus = ($status == 1 && $userData->is_agent_verified);
            } else {
                // User context: check user-specific auto-approve setting and their user verification status
                $status = self::getSettingData('auto_approve');
                $autoApproveStatus = ($status == 1 && $userData->is_user_verified);
            }
        }

        return $autoApproveStatus;
    }

    public static function getCustomerMeta($id)
    {
        $agentData = AgentVerification::where('customer_id', $id)
            ->whereIn('form_type', ['become_agent', 'verify_agent'])
            ->get()
            ->keyBy('form_type');

        $userVerification = VerifyCustomer::where('user_id', $id)->first();

        return [
            'is_agent' => isset($agentData['become_agent']) &&
                strtolower($agentData['become_agent']->status) === 'approved',

            'is_agent_verified' => isset($agentData['verify_agent']) &&
                strtolower($agentData['verify_agent']->status) === 'approved',

            'is_user_verified' => strtolower($userVerification?->status ?? '') === 'approved',

            'agent_verification_status' => $agentData['verify_agent']->status ?? 'not_applied',

            'become_agent_status' => $agentData['become_agent']->status ?? 'not_applied',

            'user_verification_status' => $userVerification->status ?? 'not_applied',
        ];
    }

    /**
     * Compute slice boundaries to guarantee a minimum number of "featured"
     * items per page while backfilling the rest with "normal" items.
     *
     * Featured and normal items are paginated as two independent, ordered
     * pools. Each page pulls up to $minFeatured featured items (its own slice,
     * never repeated) and fills the remaining slots with normal items. When
     * the featured pool is exhausted, pages contain only normal items.
     *
     * @return array{featured_offset:int, featured_take:int, normal_offset:int, normal_take:int}
     */
    public static function featuredFillSlice(int $offset, int $limit, int $featuredTotal, int $minFeatured = 3): array
    {
        $pageIndex = $limit > 0 ? intdiv($offset, $limit) : 0;
        $perPage = min($minFeatured, $limit);

        // How many featured items were consumed by all previous pages.
        $featuredUsedBefore = min($featuredTotal, $pageIndex * $perPage);

        // Featured available for this page (0..$perPage).
        $featuredTake = max(0, min($perPage, $featuredTotal - $featuredUsedBefore));

        // Normal offset = total slots used by previous pages minus the featured
        // already consumed, so the normal pool stays gap-free across pages.
        $normalOffset = max(0, ($pageIndex * $limit) - $featuredUsedBefore);
        $normalTake = $limit - $featuredTake;

        return [
            'featured_offset' => $featuredUsedBefore,
            'featured_take' => $featuredTake,
            'normal_offset' => $normalOffset,
            'normal_take' => $normalTake,
        ];
    }

    public static function userHasPremiumAccess(int $userId): bool
    {
        try {
            return UserPackage::where('user_id', $userId)
                ->where('role_context', 'user')
                ->onlyActive()
                ->exists();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function userHasFeatureAccess(int $userId, string $featureType): bool
    {
        try {
            $featureId = self::getFeatureId($featureType);
            if (! $featureId) {
                return false;
            }

            $packageIds = UserPackage::where('user_id', $userId)
                ->where('role_context', 'user')
                ->onlyActive()
                ->pluck('package_id');

            if ($packageIds->isEmpty()) {
                return false;
            }

            return PackageFeature::whereIn('package_id', $packageIds)
                ->where('feature_id', $featureId)
                ->exists();
        } catch (Exception $e) {
            return false;
        }
    }
}
