<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar todas las 32 provincias de República Dominicana
        $provinces = [
            ['category' => 'Azua', 'parameter_types' => 'location', 'slug_id' => 'azua', 'sequence' => 1],
            ['category' => 'Bahoruco', 'parameter_types' => 'location', 'slug_id' => 'bahoruco', 'sequence' => 2],
            ['category' => 'Barahona', 'parameter_types' => 'location', 'slug_id' => 'barahona', 'sequence' => 3],
            ['category' => 'La Altagracia', 'parameter_types' => 'location', 'slug_id' => 'la-altagracia', 'sequence' => 4],
            ['category' => 'La Romana', 'parameter_types' => 'location', 'slug_id' => 'la-romana', 'sequence' => 5],
            ['category' => 'La Vega', 'parameter_types' => 'location', 'slug_id' => 'la-vega', 'sequence' => 6],
            ['category' => 'María Trinidad Sánchez', 'parameter_types' => 'location', 'slug_id' => 'maria-trinidad-sanchez', 'sequence' => 7],
            ['category' => 'Monseñor Nouel', 'parameter_types' => 'location', 'slug_id' => 'monsenor-nouel', 'sequence' => 8],
            ['category' => 'Monte Plata', 'parameter_types' => 'location', 'slug_id' => 'monte-plata', 'sequence' => 9],
            ['category' => 'Pedernales', 'parameter_types' => 'location', 'slug_id' => 'pedernales', 'sequence' => 10],
            ['category' => 'Peravia', 'parameter_types' => 'location', 'slug_id' => 'peravia', 'sequence' => 11],
            ['category' => 'Puerto Plata', 'parameter_types' => 'location', 'slug_id' => 'puerto-plata', 'sequence' => 12],
            ['category' => 'Salcedo', 'parameter_types' => 'location', 'slug_id' => 'salcedo', 'sequence' => 13],
            ['category' => 'Samaná', 'parameter_types' => 'location', 'slug_id' => 'samana', 'sequence' => 14],
            ['category' => 'San Cristóbal', 'parameter_types' => 'location', 'slug_id' => 'san-cristobal', 'sequence' => 15],
            ['category' => 'San Juan', 'parameter_types' => 'location', 'slug_id' => 'san-juan', 'sequence' => 16],
            ['category' => 'San Pedro de Macorís', 'parameter_types' => 'location', 'slug_id' => 'san-pedro-de-macoris', 'sequence' => 17],
            ['category' => 'Sánchez Ramírez', 'parameter_types' => 'location', 'slug_id' => 'sanchez-ramirez', 'sequence' => 18],
            ['category' => 'Santiago', 'parameter_types' => 'location', 'slug_id' => 'santiago', 'sequence' => 19],
            ['category' => 'Santiago Rodríguez', 'parameter_types' => 'location', 'slug_id' => 'santiago-rodriguez', 'sequence' => 20],
            ['category' => 'Santo Domingo', 'parameter_types' => 'location', 'slug_id' => 'santo-domingo', 'sequence' => 21],
            ['category' => 'Santo Domingo Este', 'parameter_types' => 'location', 'slug_id' => 'santo-domingo-este', 'sequence' => 22],
            ['category' => 'Santo Domingo Oeste', 'parameter_types' => 'location', 'slug_id' => 'santo-domingo-oeste', 'sequence' => 23],
            ['category' => 'Santo Domingo Norte', 'parameter_types' => 'location', 'slug_id' => 'santo-domingo-norte', 'sequence' => 24],
            ['category' => 'Valverde', 'parameter_types' => 'location', 'slug_id' => 'valverde', 'sequence' => 25],
            ['category' => 'Duarte', 'parameter_types' => 'location', 'slug_id' => 'duarte', 'sequence' => 26],
            ['category' => 'Espaillat', 'parameter_types' => 'location', 'slug_id' => 'espaillat', 'sequence' => 27],
            ['category' => 'Independencia', 'parameter_types' => 'location', 'slug_id' => 'independencia', 'sequence' => 28],
            ['category' => 'Elías Piña', 'parameter_types' => 'location', 'slug_id' => 'elias-pina', 'sequence' => 29],
            ['category' => 'Monte Cristi', 'parameter_types' => 'location', 'slug_id' => 'monte-cristi', 'sequence' => 30],
            ['category' => 'Distrito Nacional', 'parameter_types' => 'location', 'slug_id' => 'distrito-nacional', 'sequence' => 31],
            ['category' => 'Punta Cana', 'parameter_types' => 'location', 'slug_id' => 'punta-cana', 'sequence' => 32],
        ];

        // Insertar con timestamp
        foreach ($provinces as $province) {
            $province['status'] = 1;
            $province['image'] = '';
            $province['created_at'] = now();
            $province['updated_at'] = now();
            
            DB::table('categories')->insertOrIgnore($province);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar las provincias agregadas
        DB::table('categories')->whereIn('slug_id', [
            'azua', 'bahoruco', 'barahona', 'la-altagracia', 'la-romana', 'la-vega',
            'maria-trinidad-sanchez', 'monsenor-nouel', 'monte-plata', 'pedernales',
            'peravia', 'puerto-plata', 'salcedo', 'samana', 'san-cristobal', 'san-juan',
            'san-pedro-de-macoris', 'sanchez-ramirez', 'santiago', 'santiago-rodriguez',
            'santo-domingo', 'santo-domingo-este', 'santo-domingo-oeste', 'santo-domingo-norte',
            'valverde', 'duarte', 'espaillat', 'independencia', 'elias-pina', 'monte-cristi',
            'distrito-nacional', 'punta-cana'
        ])->delete();
    }
};
