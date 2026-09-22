<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de preguntas del formulario de depuración de clientes.
 * Cada pregunta tiene un `weight` (ponderación 0-100) para el scoring
 * heurístico y opciones con puntos en el JSON `options`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_screening_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question_key', 120)->unique();
            $table->string('question_text', 500);
            $table->string('field_type', 30)->default('text'); // text, number, radio, checkbox, select, textarea
            $table->json('options')->nullable(); // [{"label":"Sí","value":"si","points":90}, ...]
            $table->string('placeholder', 255)->nullable();
            $table->decimal('weight', 5, 2)->default(5);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('rank')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_screening_questions');
    }
};