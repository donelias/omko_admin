<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentWhatsAppCredential extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'agent_id',
        'phone_number_id',
        'business_account_id',
        'access_token',
        'phone_number',
        'webhook_verify_token',
        'activa',
        'fecha_conexion',
        'fecha_expiracion_token',
        'nombre_cuenta',
        'numeros_permitidos',
    ];

    protected $hidden = [
        'access_token',
        'webhook_verify_token',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'numeros_permitidos' => 'array',
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
     * Verificar si un número está permitido
     */
    public function esNumeroPermitido($numero)
    {
        if (!$this->numeros_permitidos || empty($this->numeros_permitidos)) {
            return true; // Si no hay restricciones, permite todos
        }

        return in_array($numero, $this->numeros_permitidos);
    }

    /**
     * Obtener credenciales desencriptadas (para uso en servicios)
     */
    public function obtenerCredencialesDecriptadas()
    {
        return [
            'phone_number_id' => $this->phone_number_id,
            'business_account_id' => $this->business_account_id,
            'access_token' => $this->access_token,
            'webhook_verify_token' => $this->webhook_verify_token,
        ];
    }

    /**
     * Obtener número de teléfono formateado
     */
    public function obtenerNumerFormateado()
    {
        // Formato internacional: +1 234 567 8900
        $numero = preg_replace('/[^0-9]/', '', $this->phone_number);
        
        if (strlen($numero) >= 10) {
            return '+' . substr($numero, -10, 1) . ' ' . 
                   substr($numero, -9, 3) . ' ' . 
                   substr($numero, -6, 3) . ' ' . 
                   substr($numero, -3);
        }

        return $this->phone_number;
    }
}
