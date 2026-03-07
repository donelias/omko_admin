<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SeoSettings extends Model
{
    use HasFactory, HasAppTimezone;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = ['page', 'image', 'title', 'description', 'keywords', 'schema_markup'];
    public function getImageAttribute($image)
    {
        $path = $image ? config('global.SEO_IMG_PATH') . $image : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }
}
