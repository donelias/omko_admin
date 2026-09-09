<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega campos para tipologias de proyecto a la tabla propertys.
     *
     * Cada tipologia de proyecto se materializa como una propiedad hija vinculada
     * al proyecto padre mediante project_id. El identificador estable unit_code
     * junto con project_id garantiza unicidad y evita duplicados al editar.
     */
    public function up(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            // Vinculo con el proyecto padre
            $table->unsignedBigInteger('project_id')
                ->nullable()
                ->after('id')
                ->comment('ID del proyecto al que pertenece esta tipologia. NULL para propiedades regulares.');

            // Bandera para distinguir propiedades normales de tipologias de proyecto
            $table->boolean('is_project_unit')
                ->default(false)
                ->after('project_id')
                ->comment('true = es una tipologia/unidad de proyecto; false = propiedad regular.');

            // Identificador estable de la tipologia dentro del proyecto
            $table->string('unit_code', 100)
                ->nullable()
                ->after('is_project_unit')
                ->comment('Codigo unico de tipologia dentro del proyecto. Ej: TIPO-A, TIPO-B. Combinado con project_id es unico.');

            // Stock
            $table->unsignedInteger('total_units')
                ->nullable()
                ->after('unit_code')
                ->comment('Total de unidades disponibles para esta tipologia.');

            $table->unsignedInteger('available_units')
                ->nullable()
                ->after('total_units')
                ->comment('Unidades actualmente disponibles para reservar. Decrementado al crear reserva.');

            // Estado visible de disponibilidad
            $table->enum('unit_status', ['available', 'low_stock', 'sold_out', 'inactive'])
                ->nullable()
                ->after('available_units')
                ->comment('available: disponible; low_stock: pocas unidades (1-3); sold_out: agotado (0); inactive: desactivada manualmente.');

            // Nota: campo 'currency' ya existe en propertys (no se agrega aqui).

            // Indice para busquedas eficientes de unidades por proyecto
            $table->index(['project_id', 'is_project_unit'], 'idx_project_units');

            // Indice para filtrar por estado de disponibilidad
            $table->index(['is_project_unit', 'unit_status'], 'idx_unit_status');

            // Restriccion de unicidad: un unit_code por proyecto
            $table->unique(['project_id', 'unit_code'], 'uq_project_unit_code');

            // FK al proyecto padre
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('set null');
        });

        // Restriccion CHECK a nivel DB: available_units no puede superar total_units
        // y ninguno puede ser negativo. Se aplica via DB raw para compatibilidad MySQL 8+
        \DB::statement('ALTER TABLE propertys ADD CONSTRAINT chk_units_valid CHECK (
            (available_units IS NULL AND total_units IS NULL)
            OR (available_units >= 0 AND total_units >= 0 AND available_units <= total_units)
        )');
    }

    public function down(): void
    {
        // Eliminar constraint CHECK primero (MySQL no lo elimina automaticamente al dropColumn)
        try {
            \DB::statement('ALTER TABLE propertys DROP CONSTRAINT chk_units_valid');
        } catch (\Exception $e) {
            // Si no existe (MySQL < 8.0.16) ignorar
        }

        Schema::table('propertys', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropUnique('uq_project_unit_code');
            $table->dropIndex('idx_project_units');
            $table->dropIndex('idx_unit_status');
            $table->dropColumn([
                'project_id',
                'is_project_unit',
                'unit_code',
                'total_units',
                'available_units',
                'unit_status',
            ]);
        });
    }
};
