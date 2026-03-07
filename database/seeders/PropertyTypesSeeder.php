<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar registros vacíos primero
        DB::table('categories')->whereNull('category')->orWhere('category', '')->delete();

        $types = [
            ['category' => 'Casa', 'image' => '1677671289.9696.svg'],
            ['category' => 'Apartamento', 'image' => '1677671427.9648.svg'],
            ['category' => 'Terreno', 'image' => '1677671549.2821.svg'],
            ['category' => 'Local Comercial', 'image' => '1677671674.2534.svg'],
            ['category' => 'Oficina', 'image' => '1677740868.9774.svg'],
            ['category' => 'Villa', 'image' => '1677741135.6339.svg'],
            ['category' => 'Penthouse', 'image' => '1677741196.5648.svg'],
            ['category' => 'Proyecto Inmobiliario', 'image' => '1677741226.1264.svg'],
            ['category' => 'Estudio', 'image' => '1677741271.8693.svg'],
            ['category' => 'Condominio', 'image' => '1677671289.9696.svg'],
            ['category' => 'Lote', 'image' => '1677671549.2821.svg'],
            ['category' => 'Casa de Campo', 'image' => '1677671427.9648.svg'],
            ['category' => 'Finca', 'image' => '1677740868.9774.svg'],
            ['category' => 'Chalet', 'image' => '1677741135.6339.svg'],
            ['category' => 'Townhouse', 'image' => '1677741196.5648.svg'],
            ['category' => 'Duplex', 'image' => '1677741226.1264.svg'],
            ['category' => 'Mansión', 'image' => '1677741271.8693.svg'],
            ['category' => 'Depósito', 'image' => '1677671674.2534.svg'],
            ['category' => 'Almacén', 'image' => '1677671674.2534.svg'],
            ['category' => 'Garaje', 'image' => '1677740460.1318.svg'],
        ];

        $sequence = 1;
        foreach ($types as $type) {
            // Solo insertar si no existe
            $exists = DB::table('categories')->where('category', $type['category'])->exists();
            
            if (!$exists) {
                DB::table('categories')->insert([
                    'category' => $type['category'],
                    'image' => $type['image'],
                    'slug_id' => Str::slug($type['category']),
                    'parameter_types' => 'property',
                    'status' => 1,
                    'sequence' => $sequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sequence++;
            }
        }

        echo "\n✅ Tipos de Propiedades creados exitosamente\n";
    }
}
