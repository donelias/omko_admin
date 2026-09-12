<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contactrequests extends Model
{
    use HasAppTimezone, HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'telefono',
        'subject',
        'message',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
}
