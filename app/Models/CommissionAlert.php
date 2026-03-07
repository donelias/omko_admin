<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommissionAlert extends Model
{
    use HasFactory, SoftDeletes, HasAppTimezone;

    protected $table = 'commission_alerts';

    protected $fillable = [
        'property_commission_id',
        'agent_user_id',
        'admin_user_id',
        'alert_type',
        'severity',
        'message',
        'status',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get property commission
     */
    public function propertyCommission()
    {
        return $this->belongsTo(PropertyCommission::class, 'property_commission_id');
    }

    /**
     * Get agent
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    /**
     * Get admin
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Scope: Unread alerts
     */
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    /**
     * Scope: Critical alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Scope: For specific agent
     */
    public function scopeForAgent($query, $userId)
    {
        return $query->where('agent_user_id', $userId);
    }

    /**
     * Mark as read
     */
    public function markAsRead()
    {
        $this->update(['status' => 'read']);
        return $this;
    }

    /**
     * Mark as acknowledged
     */
    public function markAsAcknowledged()
    {
        $this->update(['status' => 'acknowledged']);
        return $this;
    }

    /**
     * Resolve alert
     */
    public function resolve($adminUserId = null)
    {
        $this->update([
            'status' => 'resolved',
            'admin_user_id' => $adminUserId ?? auth()->id(),
        ]);
        return $this;
    }
}
