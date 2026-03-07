<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyCategory extends Model
{
    use SoftDeletes;

    protected $table = 'property_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sequence',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function properties()
    {
        return $this->hasMany(Property::class, 'category_id');
    }
}
