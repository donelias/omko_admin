<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega al cuestionario de depuración (client screening) la pregunta
     * sobre el teléfono actual del propietario o encargado de la vivienda
     * del cliente.
     *
     * Idempotente: si la pregunta ya existe (por question_key) no se toca.
     */
    public function up(): void
    {
        $exists = DB::table('client_screening_questions')
            ->where('question_key', 'telefono_propietario_actual')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('client_screening_questions')->insert([
            'question_key' => 'telefono_propietario_actual',
            'question_text' => '¿Número de teléfono actual del propietario o encargado de la propiedad donde vive ahora?',
            'field_type' => 'text',
            'options' => null,
            'placeholder' => 'Ej: 809-555-1234',
            'weight' => 3,
            'is_required' => false,
            'rank' => 18,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('client_screening_questions')
            ->where('question_key', 'telefono_propietario_actual')
            ->delete();
    }
};
