<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectUnitInventoryMovement extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'project_unit_inventory_movements';

    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'property_id',
        'project_id',
        'event_type',
        'delta_units',
        'before_units',
        'after_units',
        'appointment_id',
        'actor_type',
        'actor_id',
        'notes',
    ];

    protected $casts = [
        'property_id' => 'integer',
        'project_id' => 'integer',
        'delta_units' => 'integer',
        'before_units' => 'integer',
        'after_units' => 'integer',
        'appointment_id' => 'integer',
        'actor_id' => 'integer',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(Projects::class, 'project_id', 'id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id', 'id');
    }
}
