<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyCommission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'property_commissions';

    protected $fillable = [
        'customer_id',
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

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
