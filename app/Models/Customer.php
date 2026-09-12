<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasAppTimezone, HasFactory;

    protected $table = 'customers';

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug_id',
        'auth_id',
        'email',
        'password',
        'full_mobile',
        'country_code',
        'default_language',
        'mobile',
        'profile',
        'address',
        'fcm_id',
        'logintype',
        'is_admin_added',
        'is_email_verified',
        'isActive',
        'is_agent',
        'is_agent_verified',
        'api_token',
        'notification',
        'subscription',
        'twiiter_id',
        'latitude',
        'longitude',
        'city',
        'state',
        'country',
        'is_premium',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    public function getProfileAttribute($image)
    {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $path = $image ? config('global.CUSTOMER_PROFILE_IMG_PATH').$image : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }

    /**
     * Resolve the public-facing (agent) identity for this customer.
     * Agent profile data takes priority, falling back to the customer's own
     * fields. Mutates the model so name/email/profile reflect the resolved
     * agent identity for serialization, and returns the resolved values.
     *
     * @return array{id: int|null, customer_id: int|null, agent_name: string|null, agent_email: string|null, agent_profile_photo: string|null, agent_address: string|null, agent_mobile: string|null, agent_country_code: string|null, about_me: string|null, facebook_id: string|null, twitter_id: string|null, youtube_id: string|null, instagram_id: string|null, linkedin_id: string|null, created_at: mixed, updated_at: mixed}
     */
    public function applyResolvedAgentProfile(): array
    {
        $agentProfile = $this->agent_profile;

        $name = $agentProfile?->agent_name ?: $this->getRawOriginal('name');
        $email = $agentProfile?->agent_email ?: $this->getRawOriginal('email');

        $photo = $agentProfile?->agent_profile_photo;
        if (! $photo) {
            $photo = $this->profile;
        }

        $this->name = $name;
        $this->email = $email;
        $this->profile = $photo;

        return [
            'id' => $agentProfile?->id,
            'customer_id' => $this->id,
            'agent_name' => $name,
            'agent_email' => $email,
            'agent_profile_photo' => $photo,
            'agent_address' => $agentProfile?->agent_address,
            'agent_mobile' => $agentProfile?->agent_mobile,
            'agent_country_code' => $agentProfile?->agent_country_code,
            'about_me' => $agentProfile?->about_me,
            'facebook_id' => $agentProfile?->facebook_id,
            'twitter_id' => $agentProfile?->twitter_id,
            'youtube_id' => $agentProfile?->youtube_id,
            'instagram_id' => $agentProfile?->instagram_id,
            'linkedin_id' => $agentProfile?->linkedin_id,
            'created_at' => $agentProfile?->created_at,
            'updated_at' => $agentProfile?->updated_at,
        ];
    }

    /**
     * Accessor alias for applyResolvedAgentProfile() so code that reads
     * ->resolved_agent_profile keeps working.
     */
    public function getResolvedAgentProfileAttribute(): array
    {
        return $this->applyResolvedAgentProfile();
    }

    public function usertokens()
    {
        return $this->hasMany(Usertokens::class, 'customer_id');
    }

    public function agent_profile()
    {
        return $this->hasOne(AgentProfile::class, 'customer_id');
    }

    public function agent_profiles()
    {
        return $this->hasMany(AgentProfile::class, 'customer_id');
    }

    public function verifyCustomer()
    {
        return $this->hasOne(VerifyCustomer::class, 'user_id');
    }

    public function verify_customer()
    {
        return $this->hasOne(VerifyCustomer::class, 'user_id');
    }

    public function verifyAgent()
    {
        return $this->hasOne(AgentVerification::class, 'customer_id')->where('form_type', 'verify_agent');
    }

    public function becomeAgent()
    {
        return $this->hasOne(AgentVerification::class, 'customer_id')->where('form_type', 'become_agent');
    }

    public function property()
    {
        return $this->hasMany(Property::class, 'added_by');
    }

    public function projects()
    {
        return $this->hasMany(Projects::class, 'added_by');
    }

    public function interested_users()
    {
        return $this->hasMany(InterestedUser::class, 'customer_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'agent_id');
    }

    public function ad_integration()
    {
        return $this->hasOne(AgentAdIntegration::class, 'agent_id');
    }

    public function agent_booking_preferences()
    {
        return $this->hasOne(AgentBookingPreference::class, 'agent_id');
    }

    public function agent_extra_time_slots()
    {
        return $this->hasMany(AgentExtraTimeSlot::class, 'agent_id');
    }

    public function agent_availabilities()
    {
        return $this->hasMany(AgentAvailability::class, 'agent_id');
    }

    public function saved_searches()
    {
        return $this->hasMany(SavedSearch::class, 'customer_id');
    }

    public function favourites()
    {
        return $this->hasMany(Favourite::class, 'user_id');
    }

    public function user_packages()
    {
        return $this->hasMany(UserPackage::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payments::class, 'customer_id');
    }

    public function payment_transactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'user_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function chats()
    {
        return $this->hasMany(Chats::class, 'sender_id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class, 'agent_id');
    }

    public function scopeWithStoryStatus($query)
    {
        return $query->addSelect([
            'story_status' => Story::query()
                ->selectRaw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END')
                ->whereColumn('stories.agent_id', 'customers.id')
                ->where('stories.is_active', true)
                ->where(function ($q) {
                    $q->whereNull('stories.expires_at')->orWhere('stories.expires_at', '>', now());
                }),
        ]);
    }
}