<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuesta individual de una pregunta del screening.
 * `points` es el puntaje normalizado 0-100 otorgado a esa respuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_screening_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_screening_id');
            $table->unsignedBigInteger('question_id');
            $table->text('value')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();

            $table->foreign('client_screening_id')->references('id')->on('client_screenings')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('client_screening_questions')->onDelete('cascade');

            $table->index('client_screening_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_screening_responses');
    }
};