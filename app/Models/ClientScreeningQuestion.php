<?php

namespace App\Models;

use App\Traits\HasAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientScreeningQuestion extends Model
{
    use HasAppTimezone, HasFactory;

    protected $table = 'client_screening_questions';

    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $fillable = [
        'question_key',
        'question_text',
        'field_type',
        'options',
        'placeholder',
        'weight',
        'is_required',
        'rank',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'weight' => 'float',
        'is_required' => 'boolean',
        'status' => 'boolean',
    ];

    public function responses()
    {
        return $this->hasMany(ClientScreeningResponse::class, 'question_id');
    }

    public function isRequiredField(ClientScreeningResponse $response): bool
    {
        if (! $this->is_required) {
            return false;
        }

        $value = $response->value;

        return ! ($value === null || trim((string) $value) === '');
    }

    /**
     * Devuelve el puntaje ponderado para un valor dado (0-100).
     */
    public function pointsForValue($value): int
    {
        if ($value === null || $value === '' || ! is_array($this->options)) {
            return $this->pointsFromRule($value);
        }

        $normalized = mb_strtolower(trim((string) $value));

        foreach ($this->options as $option) {
            $optionValue = isset($option['value']) ? mb_strtolower(trim((string) $option['value'])) : null;
            if ($optionValue !== null && $normalized === $optionValue) {
                return (int) ($option['points'] ?? 50);
            }
        }

        return $this->pointsFromRule($value);
    }

    /**
     * Puntaje por reglas heurísticas para preguntas libres (texto/número).
     */
    private function pointsFromRule($value): int
    {
        return 50;
    }
}