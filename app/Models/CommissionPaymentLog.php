<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommissionPaymentLog extends Model
{
    use HasFactory, HasAppTimezone;

    protected $table = 'commission_payment_logs';

    protected $fillable = [
        'property_commission_id',
        'processed_by_user_id',
        'amount_paid',
        'payment_method',
        'payment_reference',
        'status',
        'notes',
    ];

    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get property commission
     */
    public function propertyCommission()
    {
        return $this->belongsTo(PropertyCommission::class, 'property_commission_id');
    }

    /**
     * Get user who processed payment
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
