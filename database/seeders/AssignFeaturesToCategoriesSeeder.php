<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignFeaturesToCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoryFeatures = [
            // CASA
            1 => [
                'Aire Acondicionado', 'Calefacción', 'Piscina Privada', 'Terraza Amplia', 'Balcón',
                'Garaje', 'Estacionamiento', 'Seguridad 24/7', 'Jardín Privado', 'Áreas Verdes',
                'Cocina Equipada', 'Sala de Estar', 'Comedor', 'Múltiples Dormitorios', 'Múltiples Baños',
                'Lavandería', 'Vista al Mar', 'Listo para Habitar', 'Amueblado'
            ],
            
            // APARTAMENTO
            2 => [
                'Aire Acondicionado', 'Ascensor', 'Balcón', 'Estacionamiento', 'Seguridad 24/7',
                'WiFi Gratis', 'Cocina Abierta', 'Sala de Estar', 'Comedor', 'Baño Completo',
                'Puerta Automática', 'Control de Acceso', 'Cámaras de Seguridad', 'Vista Panorámica',
                'Acabados de Lujo', 'Listo para Habitar', 'Piscina', 'Gimnasio'
            ],
            
            // TERRENO
            3 => [
                'Áreas Verdes', 'Cerca Perimetral', 'Acceso Playa', 'Servicios de Agua Potable',
                'Cisterna', 'Pozo', 'Árboles Frutales', 'Permiso de Dividir', 'Luz Natural'
            ],
            
            // LOCAL COMERCIAL
            4 => [
                'Estacionamiento', 'Ascensor', 'Puerta Automática', 'Control de Acceso',
                'Cámaras de Seguridad', 'Cocina Equipada', 'Baño Completo', 'Acceso Discapacitados',
                'Sistema de Alarma', 'Iluminación LED'
            ],
            
            // OFICINA
            5 => [
                'Aire Acondicionado', 'Ascensor', 'Estacionamiento', 'WiFi Gratis', 'Cocina',
                'Baño Completo', 'Control de Acceso', 'Cámaras de Seguridad', 'Oficina',
                'Acceso Discapacitados', 'Centro de Negocios', 'Coworking', 'Servicio de Internet'
            ],
            
            // VILLA
            6 => [
                'Aire Acondicionado', 'Calefacción', 'Piscina Privada', 'Jacuzzi', 'Terraza Amplia',
                'Balcón Amplio', 'Patio Grande', 'Garaje Doble', 'Seguridad 24/7', 'Vigilancia',
                'Cámaras de Seguridad', 'Jardín Privado', 'Áreas Verdes', 'Cocina Equipada', 'Comedor',
                'Sala de Estar', 'Biblioteca', 'SPA', 'Múltiples Dormitorios', 'Múltiples Baños',
                'Vista al Mar', 'Frente al Mar', 'Acceso Playa', 'Nuevo', 'Acabados de Lujo'
            ],
            
            // PENTHOUSE
            7 => [
                'Aire Acondicionado', 'Ascensor', 'Terraza Amplia', 'Balcón Amplio', 'Estacionamiento',
                'Seguridad 24/7', 'Control de Acceso', 'Cámaras de Seguridad', 'Cocina Equipada',
                'Sala de Estar', 'Comedor', 'Múltiples Dormitorios', 'Múltiples Baños', 'Piscina',
                'Gimnasio', 'Vista Panorámica', 'Vista al Mar', 'Techos Altos', 'Acabados de Lujo',
                'Piscina Privada', 'Jacuzzi'
            ],
            
            // PROYECTO INMOBILIARIO
            8 => [
                'Áreas Comunes', 'Salón Social', 'Piscina Común', 'Parqueo Techado', 'Seguridad 24/7',
                'Control de Acceso', 'Áreas Verdes', 'Cancha de Tenis', 'Cancha de Baloncesto',
                'Zona de Juegos', 'Centro Comercial', 'Restaurante', 'Cafetería', 'Cerca de Escuelas',
                'Transporte Público', 'Gimnasio Común', 'Iluminación LED', 'Sistemas de Riego Automático',
                'Jardinespaisajísticos', 'WiFi en Áreas Comunes'
            ],
            
            // ESTUDIO
            9 => [
                'Aire Acondicionado', 'Ascensor', 'Estacionamiento', 'WiFi Gratis', 'Cocina',
                'Baño Completo', 'Balcón', 'Control de Acceso', 'Listo para Habitar', 'Amueblado'
            ],
            
            // CONDOMINIO
            10 => [
                'Aire Acondicionado', 'Ascensor', 'Balcón', 'Estacionamiento', 'Seguridad 24/7',
                'Piscina Común', 'Áreas Verdes', 'Parqueo Techado', 'WiFi en Áreas Comunes',
                'Control de Acceso', 'Cocina', 'Sala de Estar', 'Comedor', 'Baño Completo',
                'Cancha de Tenis', 'Gimnasio Común', 'Salón Social', 'Área de Picnic'
            ],
            
            // LOTE
            11 => [
                'Áreas Verdes', 'Cerca Perimetral', 'Servicios de Agua Potable', 'Acceso a Autopista',
                'Permiso de Dividir'
            ],
            
            // CASA DE CAMPO
            12 => [
                'Patio Grande', 'Jardín', 'Árboles Frutales', 'Huerta', 'Pozo', 'Cisterna',
                'Piscina', 'Terraza', 'Calefacción', 'Cocina Equipada', 'Comedor', 'Sala de Estar',
                'Múltiples Dormitorios', 'Lavandería', 'Garaje', 'Vista a la Montaña', 'Áreas Verdes',
                'Permiso de Dividir'
            ],
            
            // FINCA
            13 => [
                'Terreno', 'Huerta', 'Árboles Frutales', 'Pozo', 'Cisterna', 'Jardín Privado',
                'Áreas Verdes', 'Pasto', 'Casa Principal', 'Cocina Equipada', 'Comedor', 'Sala de Estar',
                'Múltiples Dormitorios', 'Acceso Playa', 'Permiso de Dividir', 'Permiso de Inquilino'
            ],
            
            // CHALET
            14 => [
                'Aire Acondicionado', 'Calefacción', 'Piscina', 'Terraza Amplia', 'Balcón',
                'Garaje', 'Estacionamiento', 'Seguridad 24/7', 'Jardín Privado', 'Patio',
                'Cocina Equipada', 'Sala de Estar', 'Comedor', 'Múltiples Dormitorios', 'Lavandería',
                'Listo para Habitar', 'Vista Panorámica', 'Chimenea'
            ],
            
            // TOWNHOUSE
            15 => [
                'Aire Acondicionado', 'Ascensor', 'Terraza', 'Balcón', 'Garaje', 'Estacionamiento',
                'Seguridad 24/7', 'Patio', 'Cocina Equipada', 'Sala de Estar', 'Comedor',
                'Múltiples Dormitorios', 'Múltiples Baños', 'Lavandería', 'Control de Acceso',
                'Cámaras de Seguridad', 'Listo para Habitar'
            ],
            
            // DUPLEX
            16 => [
                'Aire Acondicionado', 'Terraza', 'Balcón', 'Garaje', 'Estacionamiento', 'Seguridad 24/7',
                'Vigilancia', 'Jardín', 'Cocina Equipada', 'Sala de Estar', 'Comedor', 'Múltiples Dormitorios',
                'Múltiples Baños', 'Lavandería', 'Control de Acceso', 'Listo para Habitar'
            ],
            
            // MANSIÓN
            17 => [
                'Aire Acondicionado', 'Calefacción', 'Piscina Privada', 'Jacuzzi', 'Sauna', 'Terraza Amplia',
                'Balcón Amplio', 'Patio Grande', 'Garaje Doble', 'Seguridad 24/7', 'Vigilancia',
                'Cámaras de Seguridad', 'Sistema de Alarma', 'Jardín Paisajístico', 'Cocina Equipada',
                'Comedor', 'Sala de Estar', 'Biblioteca', 'Oficina', 'Sala de Cine', 'SPA', 'Bar',
                'Wine Cellar', 'Múltiples Dormitorios', 'Múltiples Baños', 'Vista al Mar', 'Frente al Mar',
                'Acabados de Lujo', 'Nuevo'
            ],
            
            // DEPÓSITO
            18 => [
                'Estacionamiento', 'Seguridad 24/7', 'Cámaras de Seguridad', 'Acceso Discapacitados',
                'Sistema de Alarma', 'Iluminación LED'
            ],
            
            // ALMACÉN
            19 => [
                'Estacionamiento', 'Seguridad 24/7', 'Cámaras de Seguridad', 'Control de Acceso',
                'Sistema de Alarma', 'Iluminación LED', 'Acceso Discapacitados'
            ],
            
            // GARAJE
            20 => [
                'Seguridad 24/7', 'Cámaras de Seguridad', 'Control de Acceso', 'Iluminación LED',
                'Puerta Automática', 'Sistema de Alarma'
            ]
        ];
        
        // Mapear nombres a IDs de features
        $featureIds = DB::table('features')
            ->whereIn('type', ['property_feature', 'project_feature'])
            ->pluck('id', 'name')
            ->toArray();
        
        $assignmentCount = 0;
        
        // Para cada categoría, asignar sus features
        foreach ($categoryFeatures as $categoryId => $features) {
            foreach ($features as $featureName) {
                if (isset($featureIds[$featureName])) {
                    // Usar updateOrInsert para evitar duplicados
                    DB::table('assign_parameters')->updateOrInsert(
                        ['category_id' => $categoryId, 'parameter_id' => $featureIds[$featureName]],
                        ['category_id' => $categoryId, 'parameter_id' => $featureIds[$featureName]]
                    );
                    $assignmentCount++;
                }
            }
        }
        
        echo "\n✅ Features asignadas a Categorías exitosamente - $assignmentCount asignaciones\n";
    }
}
