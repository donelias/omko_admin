<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trazabilidad del análisis de zona dentro de la sugerencia de precio:
     * de qué snapshot proviene, cuándo se buscaron los datos, cuántas
     * propiedades comparables se usaron y cuáles fueron las fuentes.
     */
    public function up()
    {
        Schema::table('price_suggestions', function (Blueprint $table) {
            $table->unsignedBigInteger('market_analysis_id')->nullable()->after('algorithm_version');
            $table->string('market_zone_label')->nullable()->after('market_analysis_id');
            $table->dateTime('market_data_fetched_at')->nullable()->after('market_zone_label');
            $table->unsignedInteger('market_comparable_count')->default(0)->after('market_data_fetched_at');
            $table->json('market_sources')->nullable()->after('market_comparable_count');
            $table->decimal('market_price_per_sqm', 10, 2)->nullable()->after('market_sources');

            $table->index('market_analysis_id');
            $table->index('market_data_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('price_suggestions', function (Blueprint $table) {
            $table->dropIndex(['market_analysis_id']);
            $table->dropIndex(['market_data_fetched_at']);
            $table->dropColumn([
                'market_analysis_id',
                'market_zone_label',
                'market_data_fetched_at',
                'market_comparable_count',
                'market_sources',
                'market_price_per_sqm',
            ]);
        });
    }
};
