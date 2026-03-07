<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class PropertyCommission extends Model
{
    use HasFactory, SoftDeletes, HasAppTimezone;

    protected $table = 'property_commissions';

    protected $fillable = [
        'property_id',
        'agent_user_id',
        'buyer_user_id',
        'seller_user_id',
        'transaction_type',
        'property_price',
        'commission_rate',
        'commission_amount',
        'status',
        'payment_status',
        'amount_paid',
        'notes',
        'rejection_reason',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'property_price' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'pending_amount',
        'is_overdue',
        'status_badge',
    ];

    /**
     * Get property relationship
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get agent relationship
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    /**
     * Get buyer relationship
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    /**
     * Get seller relationship
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    /**
     * Get payment logs
     */
    public function payment_logs()
    {
        return $this->hasMany(CommissionPaymentLog::class, 'property_commission_id');
    }

    /**
     * Get alerts
     */
    public function alerts()
    {
        return $this->hasMany(CommissionAlert::class, 'property_commission_id');
    }

    /**
     * Calculate pending amount
     */
    public function getPendingAmountAttribute(): float
    {
        return $this->commission_amount - $this->amount_paid;
    }

    /**
     * Check if commission is overdue (more than 30 days unpaid)
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->payment_status === 'paid') {
            return false;
        }
        return $this->created_at->addDays(30)->isPast();
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'paid' => 'primary',
            default => 'secondary',
        };
    }

    /**
     * Get pending commissions for agent
     */
    public static function pendingByAgent($userId)
    {
        return self::where('agent_user_id', $userId)
            ->where('payment_status', '!=', 'paid')
            ->where('status', 'approved')
            ->with('property', 'agent');
    }

    /**
     * Get unpaid commissions exceeding threshold
     */
    public static function getHighRiskCommissions($daysThreshold = 30)
    {
        return self::where('status', 'approved')
            ->where('payment_status', '!=', 'paid')
            ->whereDate('created_at', '<=', now()->subDays($daysThreshold))
            ->with('property', 'agent')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Mark commission as approved and create transaction
     */
    public function approve($notes = null)
    {
        if ($this->status !== 'pending') {
            throw new \Exception('Only pending commissions can be approved');
        }

        $this->update([
            'status' => 'approved',
            'notes' => $notes ?? $this->notes,
        ]);

        // Create log entry for audit (if spatie/laravel-activity-log is installed)
        try {
            if (class_exists('Spatie\ActivityLog\Facades\Activity')) {
                // @codingStandardsIgnoreStart
                call_user_func('activity')
                    ->causedBy(auth()->user())
                    ->performedOn($this)
                    ->log('Commission approved for property: ' . $this->property?->title);
                // @codingStandardsIgnoreEnd
            }
        } catch (\Exception $e) {
            Log::debug('Activity log not available: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * Mark commission as rejected
     */
    public function reject($reason)
    {
        if ($this->status !== 'pending') {
            throw new \Exception('Only pending commissions can be rejected');
        }

        if (empty($reason)) {
            throw new \Exception('Rejection reason is required');
        }

        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        // Create alert for agent
        CommissionAlert::create([
            'property_commission_id' => $this->id,
            'agent_user_id' => $this->agent_user_id,
            'alert_type' => 'rejected',
            'severity' => 'danger',
            'message' => "Commission for property '{$this->property?->title}' has been rejected: {$reason}",
        ]);

        return $this;
    }

    /**
     * Record partial or full payment
     */
    public function recordPayment($amount, $paymentMethod, $paymentReference = null, $notes = null)
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Only approved commissions can receive payments');
        }

        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than 0');
        }

        if ($amount > $this->pending_amount) {
            throw new \Exception('Payment amount cannot exceed pending amount of ' . $this->pending_amount);
        }

        // Record payment log
        CommissionPaymentLog::create([
            'property_commission_id' => $this->id,
            'processed_by_user_id' => auth()->id(),
            'amount_paid' => $amount,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'notes' => $notes,
        ]);

        // Update commission
        $newPaidAmount = $this->amount_paid + $amount;
        $this->update([
            'amount_paid' => $newPaidAmount,
            'payment_status' => $newPaidAmount >= $this->commission_amount ? 'paid' : 'partially_paid',
        ]);

        // Create alert if fully paid
        if ($this->payment_status === 'paid') {
            CommissionAlert::create([
                'property_commission_id' => $this->id,
                'agent_user_id' => $this->agent_user_id,
                'alert_type' => 'paid',
                'severity' => 'info',
                'message' => "Commission for property '{$this->property?->title}' has been fully paid",
                'status' => 'acknowledged',
            ]);
        }

        return $this;
    }

    /**
     * Scope: Get overdue commissions
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'approved')
            ->where('payment_status', '!=', 'paid')
            ->whereDate('created_at', '<=', now()->subDays(30));
    }

    /**
     * Scope: Get pending approvals
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending');
    }
}
