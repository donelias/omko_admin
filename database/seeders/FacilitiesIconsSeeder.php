<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacilitiesIconsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Amenidades/Facilidades interiores con iconos
        $facilities = [
            ['name' => 'Aire Acondicionado', 'icon' => '1677733120.443.svg', 'category' => 'Clima'],
            ['name' => 'Calefacción', 'icon' => '1677733120.444.svg', 'category' => 'Clima'],
            ['name' => 'Piscina', 'icon' => '1677733164.2028.svg', 'category' => 'Entretenimiento'],
            ['name' => 'Jacuzzi', 'icon' => '1677733164.2028.svg', 'category' => 'Entretenimiento'],
            ['name' => 'Balcón', 'icon' => '1677740510.7362.svg', 'category' => 'Exteriores'],
            ['name' => 'Teraza', 'icon' => '1677740477.8671.svg', 'category' => 'Exteriores'],
            ['name' => 'Sala de Estar', 'icon' => 'livingroom-svgrepo-com.svg', 'category' => 'Interiores'],
            ['name' => 'Comedor', 'icon' => '1677740667.3054.svg', 'category' => 'Interiores'],
            ['name' => 'Cocina', 'icon' => '1691468174.2614.svg', 'category' => 'Servicios'],
            ['name' => 'Amueblado', 'icon' => '1691468108.8814.svg', 'category' => 'Servicios'],
            ['name' => 'WiFi Gratis', 'icon' => '1690463042.317.svg', 'category' => 'Servicios'],
            ['name' => 'Lavandería', 'icon' => '1677740638.383.svg', 'category' => 'Servicios'],
            ['name' => 'Garaje', 'icon' => '1691468254.1887.svg', 'category' => 'Estacionamiento'],
            ['name' => 'Estacionamiento', 'icon' => '1691468778.2773.svg', 'category' => 'Estacionamiento'],
        ];

        // Registrar en tabla 'facilities' si existe
        if (Schema::hasTable('facilities')) {
            foreach ($facilities as $fac) {
                DB::table('facilities')->updateOrInsert(
                    ['name' => $fac['name']],
                    [
                        'icon' => $fac['icon'],
                        'category' => $fac['category'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
            echo "✅ Tabla 'facilities' actualizada con iconos SVG\n";
        }

        // Registrar en tabla 'parameters' si existe (alternativa/complemento)
        if (Schema::hasTable('parameters')) {
            foreach ($facilities as $fac) {
                DB::table('parameters')->updateOrInsert(
                    ['name' => $fac['name'], 'parameter_types' => 'facility'],
                    [
                        'image' => $fac['icon'],
                        'parameter_types' => 'facility',
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
            echo "✅ Tabla 'parameters' actualizada con iconos SVG\n";
        }
    }
}
