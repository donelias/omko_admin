<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    public $timestamps = true;

    protected $fillable = ['ncf_type', 'next_sequence'];

    protected $casts = [
        'next_sequence' => 'integer',
    ];
}
