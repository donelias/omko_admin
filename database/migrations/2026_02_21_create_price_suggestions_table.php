<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id')->unique();
            $table->decimal('suggested_price', 15, 2);
            $table->decimal('suggested_price_per_sqm', 10, 2)->nullable();
            $table->decimal('minimum_price', 15, 2);
            $table->decimal('maximum_price', 15, 2);
            $table->decimal('current_price', 15, 2);
            $table->decimal('price_difference', 15, 2);
            $table->decimal('confidence_score', 5, 2)->comment('0-100, higher is better');
            $table->enum('recommendation', ['increase', 'decrease', 'maintain', 'review_required'])->default('maintain');
            $table->text('reasoning')->comment('Explicación del algoritmo');
            $table->json('comparable_properties')->nullable()->comment('IDs de propiedades comparables');
            $table->enum('market_trend', ['hot_market', 'balanced', 'slow_market'])->default('balanced');
            $table->decimal('estimated_sales_probability', 5, 2)->nullable()->comment('Probabilidad de venta en 30 días');
            $table->boolean('is_ai_generated')->default(true);
            $table->string('algorithm_version')->default('1.0');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('property_id');
            $table->index('recommendation');
            $table->index('confidence_score');
            $table->index('created_at');

            $table->foreign('property_id')->references('id')->on('propertys')->onDelete('cascade');
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_suggestions');
    }
};