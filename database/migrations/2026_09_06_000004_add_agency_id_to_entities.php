<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 8 (T5 - Multi-marca / dominio por agencia)
     *
     * Añade agency_id (nullable, FK -> agencies) a las entidades que se aislan por marca:
     * customers (agentes), propertys, projects, crm_leads, sliders y articles.
     * null = marca global (visible en todos los dominios).
     */
    public function up(): void
    {
        $tables = ['customers', 'propertys', 'projects', 'crm_leads', 'sliders', 'articles'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('agency_id')->nullable()->after('id');
                $table->index('agency_id');
                $table->foreign('agency_id')->references('id')->on('agencies')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['customers', 'propertys', 'projects', 'crm_leads', 'sliders', 'articles'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['agency_id']);
                $table->dropIndex(['agency_id']);
                $table->dropColumn('agency_id');
            });
        }
    }
};