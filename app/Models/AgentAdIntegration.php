<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Integraciones de marketing por agente.
 * Los tokens (CAPI / WhatsApp) se guardan cifrados; los accesors/mutators se encargan
 * de cifrar al setear y descifrar al leer, de modo que nunca viajan en claro en la BD.
 */
class AgentAdIntegration extends Model
{
    use HasFactory;

    protected $table = 'agent_ad_integrations';

    protected $fillable = [
        'agent_id',
        'pixel_id',
        'ad_account_id',
        'capi_access_token',
        'capi_test_event_code',
        'whatsapp_mode',
        'whatsapp_token',
        'whatsapp_phone_id',
        'whatsapp_sender_number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function agent()
    {
        return $this->belongsTo(Customer::class, 'agent_id');
    }

    public function getCapiAccessTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setCapiAccessTokenAttribute(?string $value): void
    {
        $this->attributes['capi_access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getWhatsAppTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setWhatsAppTokenAttribute(?string $value): void
    {
        $this->attributes['whatsapp_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Devuelve los datos enmascarados para el frontend (nunca tokens completos).
     */
    public function toMaskedArray(): array
    {
        return [
            'agent_id' => $this->agent_id,
            'pixel_id' => $this->pixel_id,
            'ad_account_id' => $this->ad_account_id,
            'capi_access_token' => $this->capi_access_token
                ? '••••••••' . substr($this->capi_access_token, -4)
                : null,
            'capi_token_configured' => (bool) $this->capi_access_token,
            'capi_test_event_code' => $this->capi_test_event_code,
            'whatsapp_mode' => $this->whatsapp_mode,
            'whatsapp_token' => $this->whatsapp_token
                ? '••••••••' . substr($this->whatsapp_token, -4)
                : null,
            'whatsapp_token_configured' => (bool) $this->whatsapp_token,
            'whatsapp_phone_id' => $this->whatsapp_phone_id,
            'whatsapp_sender_number' => $this->whatsapp_sender_number,
            'is_active' => $this->is_active,
        ];
    }
}