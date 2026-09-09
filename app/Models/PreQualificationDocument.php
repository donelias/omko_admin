<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreQualificationDocument extends Model
{
    use HasAppTimezone, HasFactory;

    protected $dates = [];

    protected $fillable = [
        'pre_qualification_id',
        'type',
        'label',
        'file_path',
        'original_name',
        'file_size',
    ];

    public function preQualification()
    {
        return $this->belongsTo(PreQualification::class, 'pre_qualification_id');
    }
}
