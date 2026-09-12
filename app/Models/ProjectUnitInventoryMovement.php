<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectUnitInventoryMovement extends Model
{
    protected $table = 'project_unit_inventory_movements';

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
        'delta_units' => 'integer',
        'before_units' => 'integer',
        'after_units' => 'integer',
    ];

    public const RESERVE = 'reserve';
    public const CONFIRM = 'confirm';
    public const CANCEL = 'cancel';
    public const EXPIRE = 'expire';
    public const REJECT = 'reject';
    public const MANUAL_ADJUST = 'manual_adjust';

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Projects::class, 'project_id');
    }
}
