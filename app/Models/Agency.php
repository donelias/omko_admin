<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'logo',
        'primary_color',
        'domains',
        'owner_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Lista de dominios/hosts configurados (separados por coma o salto de línea).
     */
    public function getDomainListAttribute(): array
    {
        $raw = '';
        if (isset($this->attributes['domains'])) {
            $raw = $this->attributes['domains'];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', (string) $raw) ?: []),
            fn ($d) => $d !== ''
        ));
    }

    public function owner()
    {
        return $this->belongsTo(Customer::class, 'owner_id');
    }

    public function properties()
    {
        return $this->hasMany(Property::class, 'agency_id');
    }

    public function projects()
    {
        return $this->hasMany(Projects::class, 'agency_id');
    }
}