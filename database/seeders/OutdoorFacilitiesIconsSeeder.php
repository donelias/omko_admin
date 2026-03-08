<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OutdoorFacilitiesIconsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Iconos de Lugares Cercanos (Outdoor Facilities)
        $places = [
            ['name' => 'Aeropuerto Internacional', 'image' => 'Airport-1723024820.svg'],
            ['name' => 'Hospital', 'image' => 'Hospital_1723024720.svg'],
            ['name' => 'Centro Comercial', 'image' => 'Mall_1723024861.svg'],
            ['name' => 'Supermercado', 'image' => 'Supermarket_1723024762.svg'],
            ['name' => 'Farmacia', 'image' => 'pharmacy-svgrepo-com.svg'],
            ['name' => 'Banco ATM', 'image' => 'BankATM_1723024776.svg'],
            ['name' => 'Estación de Gasolina', 'image' => 'Gas_Station_1723024845.svg'],
            ['name' => 'Gimnasio', 'image' => 'Fitness_1723024814.svg'],
            ['name' => 'Club Nocturno', 'image' => 'Night-club-1723024823.svg'],
            ['name' => 'Paradero de Bus', 'image' => 'Bus_Stop_1723024793.svg'],
            ['name' => 'Escuela', 'image' => 'School_1723024736.svg'],
            ['name' => 'Jardín', 'image' => 'Garden_1723024828.svg'],
            ['name' => 'Playa', 'image' => 'beach-area-svgrepo-com.svg'],
        ];

        // Registrar lugares cercanos
        foreach ($places as $place) {
            DB::table('outdoor_facilities')->updateOrInsert(
                ['name' => $place['name']],
                [
                    'image' => $place['image'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        echo "✅ Tabla 'outdoor_facilities' actualizada con iconos SVG\n";
    }
}
