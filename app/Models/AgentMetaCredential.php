<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentMetaCredential extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agent_id',
        'meta_app_id',
        'meta_app_secret',
        'meta_access_token',
        'meta_business_account_id',
        'meta_ad_account_id',
        'webhook_verify_token',
        'activa',
        'fecha_conexion',
        'fecha_expiracion_token',
        'nombre_cuenta',
        'email_cuenta',
    ];

    protected $hidden = [
        'meta_app_secret',
        'meta_access_token',
        'webhook_verify_token',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'fecha_conexion' => 'datetime',
        'fecha_expiracion_token' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========== RELACIONES ==========

    /**
     * El agente dueño de estas credenciales
     */
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    // ========== SCOPES ==========

    /**
     * Credenciales activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Credenciales de un agente específico
     */
    public function scopeDelAgente($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Credenciales con token próximo a expirar
     */
    public function scopeProximasAExpirar($query, $dias = 7)
    {
        return $query->where('fecha_expiracion_token', '<=', now()->addDays($dias));
    }

    // ========== MÉTODOS ==========

    /**
     * Verificar si el token está activo
     */
    public function tokenActivo()
    {
        if (!$this->fecha_expiracion_token) {
            return true; // Sin fecha de expiración = activo indefinidamente
        }

        return $this->fecha_expiracion_token->isFuture();
    }

    /**
     * Obtener días hasta expiración
     */
    public function diasHastaExpiracion()
    {
        if (!$this->fecha_expiracion_token) {
            return null;
        }

        return now()->diffInDays($this->fecha_expiracion_token, false);
    }

    /**
     * Desactivar credenciales
     */
    public function desactivar($razon = null)
    {
        $this->update(['activa' => false]);
        return $this;
    }

    /**
     * Activar credenciales
     */
    public function activar()
    {
        if (!$this->tokenActivo()) {
            return false; // No se puede activar si el token expiró
        }

        $this->update(['activa' => true]);
        return $this;
    }

    /**
     * Obtener credenciales desencriptadas (para uso en servicios)
     */
    public function obtenerCredencialesDecriptadas()
    {
        return [
            'meta_app_id' => $this->meta_app_id,
            'meta_app_secret' => $this->meta_app_secret,
            'meta_access_token' => $this->meta_access_token,
            'meta_business_account_id' => $this->meta_business_account_id,
            'webhook_verify_token' => $this->webhook_verify_token,
        ];
    }
}
