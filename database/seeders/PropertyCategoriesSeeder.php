<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear categorías de propiedades
        $propertyCategories = [
            [
                'name' => 'Casa',
                'icon' => '1677671289.9696.svg',
                'description' => 'Casas unifamiliares en venta y alquiler',
            ],
            [
                'name' => 'Apartamento',
                'icon' => '1677671427.9648.svg',
                'description' => 'Apartamentos y pisos en venta y alquiler',
            ],
            [
                'name' => 'Terreno',
                'icon' => '1677671549.2821.svg',
                'description' => 'Terrenos y lotes disponibles',
            ],
            [
                'name' => 'Local',
                'icon' => '1677671674.2534.svg',
                'description' => 'Locales comerciales y negocios',
            ],
            [
                'name' => 'Oficina',
                'icon' => '1677740868.9774.svg',
                'description' => 'Espacios de oficina y despachos',
            ],
            [
                'name' => 'Villa',
                'icon' => '1677741135.6339.svg',
                'description' => 'Villas y residencias de lujo',
            ],
            [
                'name' => 'Penthouse',
                'icon' => '1677741196.5648.svg',
                'description' => 'Penthouses y áticos premium',
            ],
            [
                'name' => 'Proyecto',
                'icon' => '1677741226.1264.svg',
                'description' => 'Proyectos inmobiliarios en desarrollo',
            ],
            [
                'name' => 'Otro',
                'icon' => '1677741271.8693.svg',
                'description' => 'Otras opciones inmobiliarias',
            ],
        ];

        foreach ($propertyCategories as $index => $cat) {
            DB::table('property_categories')->insert([
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'description' => $cat['description'],
                'icon' => $cat['icon'],
                'sequence' => $index,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Crear amenidades de propiedades
        $amenities = [
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
            ['name' => 'Cocina Integrada', 'icon' => '1691468174.2614.svg', 'category' => 'Servicios'],
            ['name' => 'Comedor', 'icon' => 'dining-room-svgrepo-com.svg', 'category' => 'Interiores'],
            ['name' => 'Sala de Estar', 'icon' => 'livingroom-svgrepo-com.svg', 'category' => 'Interiores'],
            ['name' => 'Lavandería', 'icon' => 'laundry-svgrepo-com.svg', 'category' => 'Servicios'],
        ];

        foreach ($amenities as $index => $amenity) {
            DB::table('property_amenities')->insert([
                'name' => $amenity['name'],
                'slug' => Str::slug($amenity['name']),
                'category' => $amenity['category'],
                'icon' => $amenity['icon'],
                'sequence' => $index,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        echo "\n✅ Categorías de propiedades y amenidades creadas exitosamente\n";
    }
}
