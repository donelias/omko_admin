<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RDProvincesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = [
            ['category' => 'San Juan', 'parameter_types' => 'location', 'slug_id' => 'san-juan', 'sequence' => 16, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'San Pedro de Macorís', 'parameter_types' => 'location', 'slug_id' => 'san-pedro-de-macoris', 'sequence' => 17, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Sánchez Ramírez', 'parameter_types' => 'location', 'slug_id' => 'sanchez-ramirez', 'sequence' => 18, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Independencia', 'parameter_types' => 'location', 'slug_id' => 'independencia', 'sequence' => 28, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Elías Piña', 'parameter_types' => 'location', 'slug_id' => 'elias-pina', 'sequence' => 29, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Valverde', 'parameter_types' => 'location', 'slug_id' => 'valverde', 'sequence' => 25, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Espaillat', 'parameter_types' => 'location', 'slug_id' => 'espaillat', 'sequence' => 27, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Duarte', 'parameter_types' => 'location', 'slug_id' => 'duarte', 'sequence' => 26, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Monte Cristi', 'parameter_types' => 'location', 'slug_id' => 'monte-cristi', 'sequence' => 30, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
            ['category' => 'Punta Cana', 'parameter_types' => 'location', 'slug_id' => 'punta-cana', 'sequence' => 32, 'status' => 1, 'image' => '', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($provinces as $province) {
            DB::table('categories')->insertOrIgnore($province);
        }
    }
}
