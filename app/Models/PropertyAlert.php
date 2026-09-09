<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyAlert extends Model
{
    protected $fillable = [
        'customer_id',
        'saved_search_id',
        'property_id',
        'email_sent',
        'push_sent',
        'whatsapp_sent',
    ];

    protected $casts = [
        'email_sent' => 'boolean',
        'push_sent' => 'boolean',
        'whatsapp_sent' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class, 'saved_search_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
