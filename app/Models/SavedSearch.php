<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedSearch extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'filters',
        'frequency',
        'is_active',
        'last_checked_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PropertyAlert::class, 'saved_search_id');
    }
}
