<?php

namespace App\Models;

use App\Services\FileService;
use App\Traits\HasAppTimezone;
use App\Services\HelperService;
use App\Traits\ManageTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Article extends Model
{
    use HasFactory, HasAppTimezone, ManageTranslations;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'title',
        'slug_id',
        'view_count',
        'image',
        'description',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'category_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public function getImageAttribute($image)
    {
        $path = $image ? config('global.ARTICLE_IMG_PATH') . $image : null;
        return !empty($path) ? FileService::getFileUrl($path) : null;
    }
    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }
    public function getTranslatedTitleAttribute()
    {
        return HelperService::getTranslatedData($this, $this->title, 'title');
    }
    public function getTranslatedDescriptionAttribute()
    {
        return HelperService::getTranslatedData($this, $this->description, 'description');
    }
    // public function getCreatedAtAttribute($value){
    //     return \Carbon\Carbon::parse($value)->diffForHumans();
    // }
}
