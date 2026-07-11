<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectPlans extends Model
{
    use HasAppTimezone, HasFactory;

    protected $casts = [
        'features' => 'array',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'title',
        'document',
        'project_id',
        'bedrooms',
        'bathrooms',
        'kitchen',
        'dining_room',
        'living_room',
        'build_area',
        'closet',
        'features',
        'created_at',
        'updated_at',
    ];

    public function getDocumentAttribute($name)
    {
        $path = $name ? config('global.PROJECT_DOCUMENT_PATH').$name : null;

        return ! empty($path) ? FileService::getFileUrl($path) : null;
    }
}
