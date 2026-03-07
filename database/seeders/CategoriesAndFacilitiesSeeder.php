<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoriesAndFacilitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear categorías de propiedades como parámetros
        $categories = [
            [
                'category' => 'Casa',
                'icon' => '1677671289.9696.svg',
                'meta_title' => 'Casas en Venta y Alquiler',
                'meta_description' => 'Encuentra las mejores casas disponibles',
            ],
            [
                'category' => 'Apartamento',
                'icon' => '1677671427.9648.svg',
                'meta_title' => 'Apartamentos en Venta y Alquiler',
                'meta_description' => 'Descubre apartamentos modernos',
            ],
            [
                'category' => 'Terreno',
                'icon' => '1677671549.2821.svg',
                'meta_title' => 'Terrenos en Venta',
                'meta_description' => 'Terrenos con excelente ubicación',
            ],
            [
                'category' => 'Local',
                'icon' => '1677671674.2534.svg',
                'meta_title' => 'Locales Comerciales',
                'meta_description' => 'Locales para negocios',
            ],
            [
                'category' => 'Oficina',
                'icon' => '1677740868.9774.svg',
                'meta_title' => 'Oficinas en Alquiler',
                'meta_description' => 'Espacios de oficina profesionales',
            ],
            [
                'category' => 'Villa',
                'icon' => '1677741135.6339.svg',
                'meta_title' => 'Villas de Lujo',
                'meta_description' => 'Villas exclusivas y lujosas',
            ],
            [
                'category' => 'Penthouse',
                'icon' => '1677741196.5648.svg',
                'meta_title' => 'Penthouses Premium',
                'meta_description' => 'Penthouses de alta gama',
            ],
            [
                'category' => 'Proyecto',
                'icon' => '1677741226.1264.svg',
                'meta_title' => 'Proyectos Inmobiliarios',
                'meta_description' => 'Nuevos proyectos en desarrollo',
            ],
            [
                'category' => 'Otro',
                'icon' => '1677741271.8693.svg',
                'meta_title' => 'Otras Propiedades',
                'meta_description' => 'Otras opciones inmobiliarias',
            ],
        ];

        // Inserta solo si no existen
        foreach ($categories as $cat) {
            $exists = DB::table('categories')->where('category', $cat['category'])->exists();
            if (!$exists) {
                DB::table('categories')->insert([
                    'category' => $cat['category'],
                    'image' => $cat['icon'],
                    'parameter_types' => 'property_type',
                    'status' => 1,
                    'sequence' => 0,
                    'meta_title' => $cat['meta_title'],
                    'meta_description' => $cat['meta_description'],
                    'meta_keywords' => implode(',', explode(' ', $cat['meta_description'])),
                    'meta_image' => $cat['icon'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "✅ Categoría '{$cat['category']}' creada\n";
            }
        }

        // Crear amenidades (facilities)
        $facilities = [
            ['name' => 'Aire Acondicionado', 'icon' => '1677733120.443.svg', 'category' => 'Clima'],
            ['name' => 'Calefacción', 'icon' => '1677733120.444.svg', 'category' => 'Clima'],
            ['name' => 'Piscina', 'icon' => '1677733164.2028.svg', 'category' => 'Entretenimiento'],
            ['name' => 'Terraza', 'icon' => '16777331642028.svg', 'category' => 'Exterior'],
            ['name' => 'Balcón', 'icon' => '1677740426.0293.svg', 'category' => 'Exterior'],
            ['name' => 'Garaje', 'icon' => '1677740460.1318.svg', 'category' => 'Estacionamiento'],
            ['name' => 'Estacionamiento', 'icon' => '1677740477.8671.svg', 'category' => 'Estacionamiento'],
            ['name' => 'Ascensor', 'icon' => '1677740510.7362.svg', 'category' => 'Acceso'],
            ['name' => 'Puerta Automática', 'icon' => '1677740529.9557.svg', 'category' => 'Acceso'],
            ['name' => 'Seguridad 24/7', 'icon' => '1677740564.8168.svg', 'category' => 'Seguridad'],
            ['name' => 'Vigilancia', 'icon' => '1677740608.7743.svg', 'category' => 'Seguridad'],
            ['name' => 'Cerco Perimetral', 'icon' => '1677740623.3241.svg', 'category' => 'Seguridad'],
            ['name' => 'Jardín', 'icon' => '1677740638.383.svg', 'category' => 'Exterior'],
            ['name' => 'Áreas Verdes', 'icon' => '1677740667.3054.svg', 'category' => 'Exterior'],
            ['name' => 'Patio', 'icon' => '1677740685.435.svg', 'category' => 'Exterior'],
            ['name' => 'Cancha Deportiva', 'icon' => '1677740713.1518.svg', 'category' => 'Entretenimiento'],
            ['name' => 'Gimnasio', 'icon' => '1677740755.9703.svg', 'category' => 'Entretenimiento'],
            ['name' => 'WiFi Gratis', 'icon' => '1690463042.317.svg', 'category' => 'Servicios'],
            ['name' => 'Amueblado', 'icon' => '1691468108.8814.svg', 'category' => 'Servicios'],
            ['name' => 'Cocina', 'icon' => '1691468174.2614.svg', 'category' => 'Servicios'],
            ['name' => 'Comedor', 'icon' => 'dining-room-svgrepo-com.svg', 'category' => 'Interiores'],
            ['name' => 'Sala de Estar', 'icon' => 'livingroom-svgrepo-com.svg', 'category' => 'Interiores'],
            ['name' => 'Lavandería', 'icon' => 'laundry-svgrepo-com.svg', 'category' => 'Servicios'],
        ];

        foreach ($facilities as $facility) {
            $exists = DB::table('facilities')->where('name', $facility['name'])->exists();
            if (!$exists) {
                DB::table('facilities')->insert([
                    'name' => $facility['name'],
                    'icon' => $facility['icon'],
                    'category' => $facility['category'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                echo "✅ Amenidad '{$facility['name']}' creada\n";
            }
        }

        echo "\n✅ Categorías y Amenidades completadas\n";
    }
}
