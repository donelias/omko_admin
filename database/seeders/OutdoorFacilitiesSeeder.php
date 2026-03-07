<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OutdoorFacilitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Primero eliminar todos los registros anteriores
        DB::table('outdoor_facilities')->truncate();
        
        $facilities = [
            // Transporte
            ['name' => 'Aeropuerto Internacional', 'image' => '1677741289.9696.svg'],
            ['name' => 'Aeropuerto Doméstico', 'image' => '1677741427.9648.svg'],
            ['name' => 'Terminal de Autobús', 'image' => '1677741549.2821.svg'],
            ['name' => 'Estación de Metro', 'image' => '1677741674.2534.svg'],
            ['name' => 'Puerto', 'image' => '1677740868.9774.svg'],
            
            // Comercio
            ['name' => 'Centro Comercial Principal', 'image' => '1677741135.6339.svg'],
            ['name' => 'Centro Comercial', 'image' => '1677741196.5648.svg'],
            ['name' => 'Supermercado', 'image' => '1677741226.1264.svg'],
            ['name' => 'Mercado', 'image' => '1677741271.8693.svg'],
            ['name' => 'Outlet', 'image' => '1677741289.9696.svg'],
            
            // Salud
            ['name' => 'Hospital Principal', 'image' => '1677741427.9648.svg'],
            ['name' => 'Clínica Médica', 'image' => '1677741549.2821.svg'],
            ['name' => 'Centro de Salud', 'image' => '1677741674.2534.svg'],
            ['name' => 'Farmacia', 'image' => '1677740868.9774.svg'],
            ['name' => 'Dentista', 'image' => '1677741135.6339.svg'],
            ['name' => 'Oftalmólogo', 'image' => '1677741196.5648.svg'],
            ['name' => 'Veterinaria', 'image' => '1677741226.1264.svg'],
            
            // Educación
            ['name' => 'Escuela Primaria', 'image' => '1677741271.8693.svg'],
            ['name' => 'Escuela Secundaria', 'image' => '1677741289.9696.svg'],
            ['name' => 'Universidad', 'image' => '1677741427.9648.svg'],
            ['name' => 'Academia de Idiomas', 'image' => '1677741549.2821.svg'],
            ['name' => 'Guardería', 'image' => '1677741674.2534.svg'],
            
            // Religión
            ['name' => 'Iglesia', 'image' => '1677740868.9774.svg'],
            ['name' => 'Sinagoga', 'image' => '1677741135.6339.svg'],
            ['name' => 'Mezquita', 'image' => '1677741196.5648.svg'],
            ['name' => 'Templo', 'image' => '1677741226.1264.svg'],
            
            // Recreación
            ['name' => 'Parque Temático', 'image' => '1677741271.8693.svg'],
            ['name' => 'Cine', 'image' => '1677741289.9696.svg'],
            ['name' => 'Teatro', 'image' => '1677741427.9648.svg'],
            ['name' => 'Museo', 'image' => '1677741549.2821.svg'],
            ['name' => 'Galería de Arte', 'image' => '1677741674.2534.svg'],
            ['name' => 'Biblioteca', 'image' => '1677740868.9774.svg'],
            ['name' => 'Estadio', 'image' => '1677741135.6339.svg'],
            ['name' => 'Piscina Pública', 'image' => '1677741196.5648.svg'],
            ['name' => 'Cancha de Golf', 'image' => '1677741226.1264.svg'],
            ['name' => 'Club Deportivo', 'image' => '1677741271.8693.svg'],
            ['name' => 'Playa', 'image' => '1677741289.9696.svg'],
            ['name' => 'Marina', 'image' => '1677741427.9648.svg'],
            
            // Servicios
            ['name' => 'Banco', 'image' => '1677741549.2821.svg'],
            ['name' => 'Comisaría de Policía', 'image' => '1677741674.2534.svg'],
            ['name' => 'Estación de Bomberos', 'image' => '1677740868.9774.svg'],
            ['name' => 'Correo', 'image' => '1677741135.6339.svg'],
            ['name' => 'Notaría', 'image' => '1677741196.5648.svg'],
            ['name' => 'Abogado', 'image' => '1677741226.1264.svg'],
            ['name' => 'Contador', 'image' => '1677741271.8693.svg'],
            ['name' => 'Agencia de Viajes', 'image' => '1677741289.9696.svg'],
            
            // Restaurantes y Comida
            ['name' => 'Restaurante de Lujo', 'image' => '1677741427.9648.svg'],
            ['name' => 'Restaurante Casual', 'image' => '1677741549.2821.svg'],
            ['name' => 'Pizzería', 'image' => '1677741674.2534.svg'],
            ['name' => 'Hamburguesería', 'image' => '1677740868.9774.svg'],
            ['name' => 'Cafetería', 'image' => '1677741135.6339.svg'],
            ['name' => 'Bar', 'image' => '1677741196.5648.svg'],
            ['name' => 'Discoteca', 'image' => '1677741226.1264.svg'],
            ['name' => 'Karaoke', 'image' => '1677741271.8693.svg'],
            ['name' => 'Panadería', 'image' => '1677741289.9696.svg'],
            ['name' => 'Pastelería', 'image' => '1677741427.9648.svg'],
            
            // Servicios de Belleza
            ['name' => 'Salón de Belleza', 'image' => '1677741549.2821.svg'],
            ['name' => 'Peluquería', 'image' => '1677741674.2534.svg'],
            ['name' => 'Spa', 'image' => '1677740868.9774.svg'],
            ['name' => 'Gimnasio', 'image' => '1677741135.6339.svg'],
            ['name' => 'Yoga Studio', 'image' => '1677741196.5648.svg'],
            
            // Servicios para el Hogar
            ['name' => 'Ferretería', 'image' => '1677741226.1264.svg'],
            ['name' => 'Mueblería', 'image' => '1677741271.8693.svg'],
            ['name' => 'Tienda de Decoración', 'image' => '1677741289.9696.svg'],
            ['name' => 'Lavandera', 'image' => '1677741427.9648.svg'],
            ['name' => 'Taller Mecánico', 'image' => '1677741549.2821.svg'],
            ['name' => 'Estación de Gasolina', 'image' => '1677741674.2534.svg'],
            ['name' => 'Parking Público', 'image' => '1677740868.9774.svg'],
            
            // Verde y Naturaleza
            ['name' => 'Parque Principal', 'image' => '1677741135.6339.svg'],
            ['name' => 'Bosque', 'image' => '1677741196.5648.svg'],
            ['name' => 'Jardín Botánico', 'image' => '1677741226.1264.svg'],
            ['name' => 'Reserva Natural', 'image' => '1677741271.8693.svg'],
        ];

        // Insertar los lugares cercanos
        foreach ($facilities as $facility) {
            DB::table('outdoor_facilities')->insert(
                ['name' => $facility['name'], 'image' => $facility['image'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        echo "\n✅ Outdoor Facilities (Nearby Places) creadas exitosamente - 68 lugares cercanos\n";
    }
}
