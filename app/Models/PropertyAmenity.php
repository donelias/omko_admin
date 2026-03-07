<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyAmenity extends Model
{
    use SoftDeletes;

    protected $table = 'property_amenities';

    protected $fillable = [
        'name',
        'slug',
        'category',
        'icon',
        'sequence',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function properties()
    {
        return $this->belongsToMany(Property::class, 'property_amenity', 'amenity_id', 'property_id');
    }
}
