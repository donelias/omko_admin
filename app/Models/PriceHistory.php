<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PriceHistory extends Model
{
    use HasAppTimezone, HasFactory, SoftDeletes;

    protected $table = 'price_history';

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'property_id',
        'price',
        'price_per_sqm',
        'status',
        'transaction_type',
        'days_on_market',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_per_sqm' => 'decimal:2',
        'days_on_market' => 'integer',
    ];

    /**
     * Relaciones
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scopes
     */
    public function scopeForProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeSold($query)
    {
        return $query->where('status', 'sold');
    }

    public function scopeRented($query)
    {
        return $query->where('status', 'rented');
    }

    public function scopeByTransactionType($query, $transactionType)
    {
        return $query->where('transaction_type', $transactionType);
    }

    public function scopeWithinDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Precio promedio histórico de una propiedad dentro de un período.
     *
     * @return float|null
     */
    public static function getAveragePriceForProperty($propertyId, $days = 90)
    {
        return self::forProperty($propertyId)
            ->where('created_at', '>=', now()->subDays($days))
            ->avg('price');
    }

    /**
     * Tendencia de precios de una propiedad.
     * Retorna: percentage + comparison entre períodos recientes y previos.
     *
     * @return array
     */
    public static function getPriceTrendForProperty($propertyId)
    {
        $now = now();

        $recent = self::forProperty($propertyId)
            ->whereBetween('created_at', [$now->copy()->subDays(90), $now])
            ->avg('price');

        $previous = self::forProperty($propertyId)
            ->whereBetween('created_at', [$now->copy()->subDays(180), $now->copy()->subDays(90)])
            ->avg('price');

        if (empty($recent) || empty($previous)) {
            return [
                'percentage' => 0.0,
                'direction' => 'stable',
                'recent_average' => $recent ?? 0,
                'previous_average' => $previous ?? 0,
            ];
        }

        $percentage = (($recent - $previous) / $previous) * 100;

        return [
            'percentage' => round($percentage, 2),
            'direction' => $percentage > 5 ? 'increasing' : ($percentage < -5 ? 'decreasing' : 'stable'),
            'recent_average' => round($recent, 2),
            'previous_average' => round($previous, 2),
        ];
    }

    /**
     * Últimos 12 meses de historia para la propiedad.
     */
    public static function getHistoryForProperty($propertyId, $months = 12)
    {
        return self::forProperty($propertyId)
            ->where('created_at', '>=', now()->subMonths($months))
            ->orderBy('created_at', 'desc')
            ->get();
    }
}