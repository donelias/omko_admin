<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

// class Customer extends Authenticatable implements JWTSubject
class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'auth_id',
        'mobile',
        'country_code',
        'default_language',
        'profile',
        'address',
        'fcm_id',
        'logintype',
        'is_admin_added',
        'is_email_verified',
        'isActive',
        'slug_id',
        'notification',
        'about_me',
        'facebook_id',
        'twiiter_id',
        'instagram_id',
        'youtube_id',
        'latitude',
        'longitude',
        'city',
        'state',
        'country',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'api_token'
    ];

    protected static function boot() {
        parent::boot();
        static::deleting(static function ($customer) {
            if(collect($customer)->isNotEmpty()){
                // before delete() method call this
                $userId = $customer->id;

                /** Delete Directly with delete query */
                Projects::where('added_by', $userId)->delete();
                Notifications::where('customers_id', $userId)->delete();
                Advertisement::where('customer_id', $userId)->delete();
                UserPackage::where('user_id', $userId)->delete();

                /** Delete Payment Transactions */
                $paymentTransactions = PaymentTransaction::where('user_id', $userId)->get();
                foreach($paymentTransactions as $paymentTransaction){
                    $paymentTransaction->delete();
                }


                /** Delete with modal boot events */
                $properties = Property::where('added_by', $userId)->get();
                foreach ($properties as $property) {
                    if(!empty($property)){
                        $property->delete(); // This will trigger the deleting and deleted events in modal
                    }
                }
                $chats = Chats::where('sender_id', $userId)->orWhere('receiver_id', $userId)->get();
                foreach ($chats as $chat) {
                    if(collect($chat)->isNotEmpty()){
                        $chat->delete(); // This will trigger the deleting and deleted events in modal
                    }
                }
                user_reports::where('customer_id', $userId)->delete();
                Usertokens::where('customer_id', $userId)->delete();
                Favourite::where('user_id', $userId)->delete();
                InterestedUser::where('customer_id', $userId)->delete();
            }
        });
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'customer_id' => $this->id
        ];
    }
    public function user_purchased_package()
    {
        return $this->hasMany(UserPackage::class, 'user_id');
    }

    public function getTotalPropertiesAttribute()
    {
        return Property::where('added_by', $this->id)->get()->count();
    }
    public function getTotalProjectsAttribute()
    {
        return Projects::where('added_by', $this->id)->get()->count();
    }
    public function favourite()
    {
        return $this->hasMany(Favourite::class, 'user_id');
    }
    public function property()
    {
        return $this->hasMany(Property::class, 'added_by');
    }
    public function projects()
    {
        return $this->hasMany(Projects::class, 'added_by');
    }
    public function getProfileAttribute($image)
    {
        // Check if $image is a valid URL
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image; // If $image is already a URL, return it as it is
        } else {
            $path = $image ? config('global.USER_IMG_PATH') . $image : null;
            return !empty($path) ? FileService::getFileUrl($path) : null;
        }
    }
    public function getMobileAttribute($mobile)
    {
        if (env('DEMO_MODE')) {
            if (env('DEMO_MODE') && Auth::check() != false && Auth::user()->email == 'superadmin@gmail.com') {
                return $mobile;
            } else {
                return '****************************';
            }
        }
        return $mobile;
    }
    public function getPhoneNumberAttribute(){
        if(!empty($this->country_code) && !empty($this->mobile)){
            return $this->country_code.$this->mobile;
        }
        return null;
    }

    public function usertokens()
    {
        return $this->hasMany(Usertokens::class, 'customer_id');
    }

    public function agent_availabilities()
    {
        return $this->hasMany(AgentAvailability::class, 'agent_id');
    }

    public function agent_booking_preferences()
    {
        return $this->hasOne(AgentBookingPreference::class, 'agent_id');
    }

    /**
     * Get the user associated with the Customer
     *
     */
    public function verify_customer()
    {
        return $this->hasOne(VerifyCustomer::class, 'user_id');
    }

    public function getIsUserVerifiedAttribute(){
        return $this->whereHas('verify_customer',function($query){
            $query->where(['user_id' => $this->id, 'status' => 'success']);
        })->count() ? true : false;
    }

    public function getIsDemoUserAttribute(){
        return env('DEMO_MODE') && $this->email == 'wrteamdemo@gmail.com' && $this->getRawOriginal('mobile') == '1234567890' && $this->country_code == '91' && $this->logintype == '1' ? true : false;
    }

    public function getIsAgentAttribute(){
        // Check Property List and Project List Feature is available
        $propertyListType = config('constants.FEATURES.PROPERTY_LIST.TYPE');
        $projectListType = config('constants.FEATURES.PROJECT_LIST.TYPE');
        $listingFeatureId = Feature::whereIn('type', [$propertyListType, $projectListType])->pluck('id');
        $packageIds = PackageFeature::whereIn('feature_id', $listingFeatureId)->pluck('package_id');
        $hasListingPackage = $this->user_purchased_package()
            ->whereIn('package_id', $packageIds)
            ->exists();
        // Check if the user has a property or project
        $propertyExists = $this->property()->where(['status' => 1, 'request_status' => 'approved'])->whereIn('propery_type',[0,1])->exists();
        // Check if the user has a project
        $projectExists = $this->projects()->where(['status' => 1, 'request_status' => 'approved'])->exists();
        // Check if the user has a listing feature packages or property or project
        return $hasListingPackage || $propertyExists || $projectExists ? true : false;
    }

    public function getIsAppointmentAvailableAttribute(){
        $status = false;
        if($this->is_agent){
            $propertyExists = $this->property()->where(['status' => 1, 'request_status' => 'approved'])->whereIn('propery_type',[0,1])->exists();
            $appointmentScheduleExists = $this->agent_availabilities()->where('is_active',1)->exists();
            $status = $propertyExists && $appointmentScheduleExists ? true : false;
        }
        return $status;
    }

    public function getTimezone($getForAgent = false){
        if($getForAgent){
            $agentBookingPreference = $this->agent_booking_preferences;
            if($agentBookingPreference){
                return $agentBookingPreference->timezone ?? 'UTC';
            }
        }
        $adminTimezone = HelperService::getSettingData('timezone') ?? config('app.timezone');
        return $adminTimezone;
    }
}
