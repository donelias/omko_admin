<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionPaymentLog extends Model
{
    use HasFactory;

    protected $table = 'commission_payment_logs';

    public $timestamps = true;

    protected $fillable = [
        'customer_id',
        'property_commission_id',
        'processed_by_user_id',
        'amount_paid',
        'payment_method',
        'payment_reference',
        'status',
        'notes',
    ];

    public function commission()
    {
        return $this->belongsTo(PropertyCommission::class, 'property_commission_id');
    }
}
