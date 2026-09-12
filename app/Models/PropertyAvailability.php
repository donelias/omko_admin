<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyAvailability extends Model
{
    protected $table = 'property_availability';

    protected $fillable = [
        'property_id',
        'date_from',
        'date_to',
        'status',
        'nightly_price',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'status' => 'integer',
        'nightly_price' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
