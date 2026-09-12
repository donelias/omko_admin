<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'commission_settings';

    protected $fillable = [
        'commission_type',
        'commission_value',
        'currency',
        'description',
        'is_active',
        'apply_to_rentals',
        'apply_to_sales',
    ];
}
