<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices de búsqueda + normalización de tipos para empezar a escalar
     * las búsquedas de propertys (10K -> 100K -> 1M). FASE 2 (T1/T2/T6/T7).
     */
    public function up(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            // Índices de búsqueda/filtrado (T1)
            $table->index('city', 'idx_propertys_city');
            $table->index('state', 'idx_propertys_state');
            $table->index('country', 'idx_propertys_country');
            $table->index('price', 'idx_propertys_price');
            $table->index('propery_type', 'idx_propertys_propery_type');
            $table->index('status', 'idx_propertys_status');
            $table->index('request_status', 'idx_propertys_request_status');
            $table->index('created_at', 'idx_propertys_created_at');
            $table->index('added_by', 'idx_propertys_added_by');
        });
    }

    public function down(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->dropIndex('idx_propertys_city');
            $table->dropIndex('idx_propertys_state');
            $table->dropIndex('idx_propertys_country');
            $table->dropIndex('idx_propertys_price');
            $table->dropIndex('idx_propertys_propery_type');
            $table->dropIndex('idx_propertys_status');
            $table->dropIndex('idx_propertys_request_status');
            $table->dropIndex('idx_propertys_created_at');
            $table->dropIndex('idx_propertys_added_by');
        });
    }
};
