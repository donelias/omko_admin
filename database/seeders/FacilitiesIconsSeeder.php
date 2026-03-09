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
            ['name' => 'Aire Acondicionado', 'icon' => '1677733120.443.svg'],
            ['name' => 'Calefacción', 'icon' => '1677733120.444.svg'],
            ['name' => 'Piscina', 'icon' => '1677733164.2028.svg'],
            ['name' => 'Jacuzzi', 'icon' => '1677733164.2028.svg'],
            ['name' => 'Balcón', 'icon' => '1677740510.7362.svg'],
            ['name' => 'Teraza', 'icon' => '1677740477.8671.svg'],
            ['name' => 'Sala de Estar', 'icon' => 'livingroom-svgrepo-com.svg'],
            ['name' => 'Comedor', 'icon' => '1677740667.3054.svg'],
            ['name' => 'Cocina', 'icon' => '1691468174.2614.svg'],
            ['name' => 'Amueblado', 'icon' => '1691468108.8814.svg'],
            ['name' => 'WiFi Gratis', 'icon' => '1690463042.317.svg'],
            ['name' => 'Lavandería', 'icon' => '1677740638.383.svg'],
            ['name' => 'Garaje', 'icon' => '1691468254.1887.svg'],
            ['name' => 'Estacionamiento', 'icon' => '1691468778.2773.svg'],
        ];

        // Registrar en tabla 'parameters' como facilities
        foreach ($facilities as $fac) {
            DB::table('parameters')->updateOrInsert(
                ['name' => $fac['name']],
                [
                    'name' => $fac['name'],
                    'image' => $fac['icon'],
                    'type_of_parameter' => 'facility',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        echo "✅ Tabla 'parameters' actualizada con iconos de facilidades SVG\n";
    }
}
