<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryIconsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Iconos de Categorías de Propiedades
        $categories = [
            [
                'category' => 'Casa',
                'image' => '1677671289.9696.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 0,
            ],
            [
                'category' => 'Apartamento',
                'image' => '1677671427.9648.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 1,
            ],
            [
                'category' => 'Terreno',
                'image' => '1677671549.2821.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 2,
            ],
            [
                'category' => 'Local',
                'image' => '1677671674.2534.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 3,
            ],
            [
                'category' => 'Villa',
                'image' => '1677740868.9774.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 4,
            ],
            [
                'category' => 'Penthouse',
                'image' => '1677741135.6339.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 5,
            ],
            [
                'category' => 'Proyecto',
                'image' => '1677741226.1264.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 6,
            ],
            [
                'category' => 'Otro',
                'image' => '1677741271.8693.svg',
                'parameter_types' => 'property_type',
                'status' => 1,
                'sequence' => 7,
            ],
        ];

        // Registrar categorías con iconos
        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(
                ['category' => $cat['category'], 'parameter_types' => $cat['parameter_types']],
                [
                    'image' => $cat['image'],
                    'status' => $cat['status'],
                    'sequence' => $cat['sequence'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        echo "✅ Tablero 'categories' actualizado con iconos SVG\n";
    }
}
