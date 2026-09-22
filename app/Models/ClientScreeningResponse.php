<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientScreeningResponse extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'client_screening_responses';

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'client_screening_id',
        'question_id',
        'value',
        'points',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function screening()
    {
        return $this->belongsTo(ClientScreening::class, 'client_screening_id');
    }

    public function question()
    {
        return $this->belongsTo(ClientScreeningQuestion::class, 'question_id');
    }
}