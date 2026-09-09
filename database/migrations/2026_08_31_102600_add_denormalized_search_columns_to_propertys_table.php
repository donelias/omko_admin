<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 2 (T5/T6/T7):
     *  - Garantiza la columna currency (DOP/USD/EUR) — idempotente.
     *  - Denormaliza contador favorite_count (T6) y atributos EAV estructurales
     *    bedrooms / bathrooms / build_area / land_area (T7) como columnas indexadas,
     *    pobladas desde assign_parameters para no romper el acceso existente.
     */
    public function up(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            if (! Schema::hasColumn('propertys', 'currency')) {
                $table->string('currency', 3)->default('DOP')->after('price');
                $table->index('currency', 'idx_propertys_currency');
            }

            if (! Schema::hasColumn('propertys', 'favourite_count')) {
                $table->unsignedInteger('favourite_count')->default(0)->after('total_click');
            }

            foreach (['bedrooms', 'bathrooms', 'build_area', 'land_area'] as $col) {
                if (! Schema::hasColumn('propertys', $col)) {
                    $table->unsignedInteger($col)->nullable()->after('price');
                }
            }
        });

        // Índices para filtrar/ordenar por los nuevos atributos estructurales (T7)
        Schema::table('propertys', function (Blueprint $table) {
            foreach (['bedrooms', 'bathrooms', 'build_area', 'land_area', 'favourite_count'] as $col) {
                if (Schema::hasColumn('propertys', $col)) {
                    $table->index($col, 'idx_propertys_'.$col);
                }
            }
        });

        // Backfill liturgy (T6 + T7) a partir de los datos existentes.
        DB::table('propertys')->update(['favourite_count' => 0]);
        if (Schema::hasTable('favourites')) {
            DB::statement('
                UPDATE propertys p
                JOIN (SELECT property_id, COUNT(*) AS c FROM favourites GROUP BY property_id) f
                  ON f.property_id = p.id
                SET p.favourite_count = f.c
            ');
        }

        $buildAreaIds = implode(',', DB::table('parameters')->where('name', 'Build Area')->pluck('id')->toArray());
        $landAreaIds = implode(',', DB::table('parameters')->where('name', 'Land Area')->pluck('id')->toArray());
        $bedroomIds = implode(',', DB::table('parameters')->where('name', 'Bedrooms')->pluck('id')->toArray());
        $bathroomIds = implode(',', DB::table('parameters')->where('name', 'Bathrooms')->pluck('id')->toArray());

        $this->backfillNumericColumn('build_area', $buildAreaIds);
        $this->backfillNumericColumn('land_area', $landAreaIds);
        $this->backfillNumericColumn('bedrooms', $bedroomIds);
        $this->backfillNumericColumn('bathrooms', $bathroomIds);
    }

    /**
     * Copia el primer valor numérico válido de assign_parameters a propertys.<col>
     * assign_parameters usa modal_type/modal_id (morph) o property_id como ref.
     */
    protected function backfillNumericColumn(string $column, ?string $parameterIds): void
    {
        if (empty($parameterIds)) {
            return;
        }
        DB::statement("
            UPDATE propertys p
            LEFT JOIN assign_parameters ap
              ON (ap.property_id = p.id OR (ap.modal_type = 'property' AND ap.modal_id = p.id))
             AND ap.parameter_id IN ({$parameterIds})
             AND ap.value REGEXP '^[0-9]+([.][0-9]+)?$'
            SET p.{$column} = CAST(ap.value AS UNSIGNED)
        ");
    }

    public function down(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            foreach (['idx_propertys_bedrooms', 'idx_propertys_bathrooms', 'idx_propertys_build_area', 'idx_propertys_land_area', 'idx_propertys_favourite_count', 'idx_propertys_currency'] as $idx) {
                if (Schema::hasIndex('propertys', $idx)) {
                    $table->dropIndex($idx);
                }
            }
            foreach (['favourite_count', 'bedrooms', 'bathrooms', 'build_area', 'land_area'] as $col) {
                if (Schema::hasColumn('propertys', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
