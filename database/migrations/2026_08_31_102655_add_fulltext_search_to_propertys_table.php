<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 2 (T2): motor de texto FULLTEXT sobre título y descripción
     * para búsquedas rápidas, en lugar de LIKE '%...%' en tablas grandes.
     */
    public function up(): void
    {
        if (Schema::hasTable('propertys')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->fullText(['title', 'description'], 'fulltext_propertys_title_description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('propertys')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->dropIndex('fulltext_propertys_title_description');
            });
        }
    }
};
