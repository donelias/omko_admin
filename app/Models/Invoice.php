<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'ncf_type',
        'invoice_number',
        'sequence',
        'payment_transaction_id',
        'customer_id',
        'client_name',
        'client_rnc',
        'client_phone',
        'client_city',
        'client_address',
        'client_email',
        'concept',
        'quantity',
        'unit_price',
        'subtotal',
        'itbis_rate',
        'itbis_amount',
        'retention_rate',
        'retention_amount',
        'total',
        'currency',
        'issue_date',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'itbis_rate' => 'decimal:2',
        'itbis_amount' => 'decimal:2',
        'retention_rate' => 'decimal:2',
        'retention_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    protected $appends = ['formatted_number'];

    protected static function booted(): void
    {
        // Los totales nunca se aceptan del cliente: se derivan siempre de
        // subtotal + tasas, así el PDF y la BD no pueden divergir.
        static::saving(function (self $invoice) {
            $invoice->subtotal = round((float) $invoice->subtotal, 2);
            $invoice->itbis_amount = round($invoice->subtotal * ((float) $invoice->itbis_rate) / 100, 2);
            $invoice->retention_amount = round($invoice->itbis_amount * ((float) $invoice->retention_rate) / 100, 2);
            $invoice->total = round($invoice->subtotal + $invoice->itbis_amount - $invoice->retention_amount, 2);
        });
    }

    public function getFormattedNumberAttribute(): string
    {
        return $this->sequence !== null ? str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT) : '';
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * NCF con el relleno fiscal: tipo + 8 dígitos (B0100000006), igual que
     * el comprobante de referencia de la DGII.
     */
    public static function formatNcf(string $type, int $sequence): string
    {
        return $type.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Formato fiscal dominicano: 45.000,00 (punto de miles, coma decimal).
     */
    public static function money(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }

    /**
     * Como money() pero rellenado por delante con espacios hasta 13 caracteres,
     * que es como el comprobante de referencia alinea la columna de importes
     * ("$      8.100,00" vs "$    45.000,00"). Las celdas que lo usan aplican
     * white-space:pre para que HTML no colapse los espacios.
     */
    public static function moneyPad(float|string|null $amount): string
    {
        return str_pad(self::money($amount), 13, ' ', STR_PAD_LEFT);
    }

    public function isVoided(): bool
    {
        return $this->status === 'anulada';
    }
}
