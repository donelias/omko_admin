<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot del análisis real de mercado de una zona, obtenido con
     * Google Search Grounding sobre Gemini.
     *
     * Una fila = una zona (radio en km alrededor de un punto) para un tipo de
     * propiedad. Se conserva el histórico completo: sólo una fila por zona
     * tiene is_current = true (la vigente que usa el motor de precios).
     */
    public function up()
    {
        Schema::create('price_market_analyses', function (Blueprint $table) {
            $table->id();

            // Identidad de la zona
            $table->string('zone_key', 64)->comment('Grilla de lat/lng + radio');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('radius_km', 5, 1)->default(3.0);
            $table->string('zone_label')->nullable()->comment('Dirección legible de la zona');
            $table->string('property_type')->nullable()->comment('Slug de la categoría');
            $table->string('transaction_type', 20)->default('sale');

            // Estado
            $table->string('status', 20)->default('pending')->comment('pending|completed|partial|failed');
            $table->text('error_message')->nullable();

            // Estadísticas de mercado (moneda base DOP)
            $table->decimal('median_price', 15, 2)->nullable();
            $table->decimal('average_price', 15, 2)->nullable();
            $table->decimal('price_per_sqm', 10, 2)->nullable()->comment('Referencia ajustada por zona');
            $table->decimal('price_per_sqm_min', 10, 2)->nullable()->comment('Percentil 25');
            $table->decimal('price_per_sqm_max', 10, 2)->nullable()->comment('Percentil 75');
            $table->decimal('min_price', 15, 2)->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            $table->string('market_condition', 20)->default('balanced')->comment('hot_market|balanced|slow_market');

            // Evidencia
            $table->unsignedInteger('comparable_count')->default(0);
            $table->unsignedInteger('internal_comparable_count')->default(0);
            $table->json('comparables')->nullable()->comment('Comparables web detectados');
            $table->json('sources')->nullable()->comment('Fuentes consultadas (uri, title)');
            $table->text('summary')->nullable()->comment('Resumen narrativo de Gemini');

            // Calidad
            $table->decimal('confidence_score', 5, 2)->default(0)->comment('0-100');
            $table->string('confidence_level', 20)->default('low')->comment('low|medium|high');
            $table->string('model')->nullable();

            // Frescura
            $table->dateTime('data_fetched_at')->nullable();
            $table->dateTime('next_refresh_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('algorithm_version', 20)->default('1.0');

            $table->timestamps();

            $table->index(['zone_key', 'is_current'], 'idx_pma_zone_current');
            $table->index(['zone_key', 'property_type', 'transaction_type'], 'idx_pma_zone_type');
            $table->index('next_refresh_at', 'idx_pma_next_refresh');
            $table->index('data_fetched_at', 'idx_pma_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('price_market_analyses');
    }
};
