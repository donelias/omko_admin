<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 2 (T3): convierte latitude/longitude de varchar(191) a decimal(10,7)
     * para que el bounding-box y las comparaciones de radio sean eficientes e
     * indexables (en varchar los índices numéricos no ayudan).
     * Mantiene las columns nullable; se conserva la fórmula Haversine para la
     * distancia exacta, prefiltrada por bounding-box (T4).
     */
    public function up(): void
    {
        // En primer lugar, normaliza en la app cualquier valor que no sea numérico
        // para que la conversión a decimal no falle.
        DB::statement("UPDATE propertys SET latitude = NULL WHERE latitude IS NOT NULL AND latitude NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'");
        DB::statement("UPDATE propertys SET longitude = NULL WHERE longitude IS NOT NULL AND longitude NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'");
        DB::statement("UPDATE propertys SET latitude = NULL WHERE CAST(latitude AS DECIMAL(10,7)) = 0");
        DB::statement("UPDATE propertys SET longitude = NULL WHERE CAST(longitude AS DECIMAL(10,7)) = 0");

        Schema::table('propertys', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->change();
            $table->decimal('longitude', 10, 7)->nullable()->change();
            $table->index(['latitude', 'longitude'], 'idx_propertys_lat_lon');
        });
    }

    public function down(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->dropIndex('idx_propertys_lat_lon');
            $table->string('latitude', 191)->nullable()->change();
            $table->string('longitude', 191)->nullable()->change();
        });
    }
};
