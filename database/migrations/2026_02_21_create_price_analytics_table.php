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
        Schema::create('price_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type'); // 'market_avg', 'area_trend', 'seasonal', etc
            $table->string('location')->nullable(); // provincia o área específica
            $table->string('property_type')->nullable(); // casa, apartamento, etc
            $table->enum('transaction_type', ['sale', 'rental'])->default('sale');
            $table->decimal('average_price', 15, 2);
            $table->decimal('median_price', 15, 2);
            $table->decimal('price_per_sqm', 10, 2);
            $table->decimal('std_deviation', 10, 2)->comment('Desviación estándar');
            $table->integer('sample_count')->comment('Número de propiedades analizadas');
            $table->decimal('price_trend', 5, 2)->comment('Cambio % en últimos 30 días');
            $table->integer('avg_days_on_market')->nullable();
            $table->decimal('market_demand', 5, 2)->nullable()->comment('0-100, nivel de demanda');
            $table->json('price_distribution')->nullable()->comment('Histograma de precios');
            $table->json('top_amenities')->nullable()->comment('Amenidades más valoradas');
            $table->dateTime('analysis_period_start');
            $table->dateTime('analysis_period_end');
            $table->timestamps();

            // Índices
            $table->index('metric_type');
            $table->index('location');
            $table->index('property_type');
            $table->index('transaction_type');
            $table->index(['location', 'property_type', 'transaction_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_analytics');
    }
};
