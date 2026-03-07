<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crm_lead_scoring', function (Blueprint $table) {
            $table->id();
            
            // Relación
            $table->foreignId('lead_id')->constrained('crm_leads')->onDelete('cascade')->unique();
            
            // Puntuación general
            $table->tinyInteger('score_total')->default(0)->min(0)->max(100);
            
            // Desglose de puntuación
            $table->tinyInteger('score_engagement')->default(0);      // Interacciones
            $table->tinyInteger('score_interes')->default(0);        // Interés en propiedad
            $table->tinyInteger('score_tiempo')->default(0);         // Tiempo desde contacto
            $table->tinyInteger('score_comportamiento')->default(0); // Patrón de respuesta
            $table->tinyInteger('score_capacidad')->default(0);      // Capacidad de compra
            
            // Factores
            $table->json('factores')->nullable();
            
            // Recomendación
            $table->enum('recomendacion', [
                'muy_caliente',
                'caliente',
                'tibio',
                'frio',
                'descartado'
            ])->default('frio');
            
            // Timestamps
            $table->timestamp('ultima_actualizacion')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('lead_id');
            $table->index('score_total');
            $table->index('recomendacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_lead_scoring');
    }
};
