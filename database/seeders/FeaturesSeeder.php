<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            // Características de Propiedad - Clima y Confort
            ['name' => 'Aire Acondicionado', 'type' => 'property_feature'],
            ['name' => 'Calefacción', 'type' => 'property_feature'],
            ['name' => 'Ventilación Natural', 'type' => 'property_feature'],
            ['name' => 'Luz Natural', 'type' => 'property_feature'],
            ['name' => 'Chimenea', 'type' => 'property_feature'],
            
            // Características de Propiedad - Agua y Energía
            ['name' => 'Agua Caliente', 'type' => 'property_feature'],
            ['name' => 'Energía Solar', 'type' => 'property_feature'],
            ['name' => 'Generador Eléctrico', 'type' => 'property_feature'],
            ['name' => 'Cisterna', 'type' => 'property_feature'],
            ['name' => 'Pozo', 'type' => 'property_feature'],
            
            // Características de Propiedad - Recreación
            ['name' => 'Piscina', 'type' => 'property_feature'],
            ['name' => 'Piscina Privada', 'type' => 'property_feature'],
            ['name' => 'Jacuzzi', 'type' => 'property_feature'],
            ['name' => 'Sauna', 'type' => 'property_feature'],
            ['name' => 'Cancha Deportiva', 'type' => 'property_feature'],
            ['name' => 'Cancha de Tenis', 'type' => 'property_feature'],
            ['name' => 'Cancha de Golf', 'type' => 'property_feature'],
            ['name' => 'Gimnasio', 'type' => 'property_feature'],
            ['name' => 'Billar', 'type' => 'property_feature'],
            ['name' => 'Sala de Cine', 'type' => 'property_feature'],
            
            // Características de Propiedad - Exteriores
            ['name' => 'Terraza', 'type' => 'property_feature'],
            ['name' => 'Terraza Amplia', 'type' => 'property_feature'],
            ['name' => 'Balcón', 'type' => 'property_feature'],
            ['name' => 'Balcón Amplio', 'type' => 'property_feature'],
            ['name' => 'Patio', 'type' => 'property_feature'],
            ['name' => 'Patio Grande', 'type' => 'property_feature'],
            ['name' => 'Jardín', 'type' => 'property_feature'],
            ['name' => 'Jardín Privado', 'type' => 'property_feature'],
            ['name' => 'Áreas Verdes', 'type' => 'property_feature'],
            ['name' => 'Huerta', 'type' => 'property_feature'],
            ['name' => 'Porch', 'type' => 'property_feature'],
            ['name' => 'Veranda', 'type' => 'property_feature'],
            
            // Características de Propiedad - Vistas
            ['name' => 'Vista al Mar', 'type' => 'property_feature'],
            ['name' => 'Vista a la Montaña', 'type' => 'property_feature'],
            ['name' => 'Vista al Lago', 'type' => 'property_feature'],
            ['name' => 'Vista al Rio', 'type' => 'property_feature'],
            ['name' => 'Vista Panorámica', 'type' => 'property_feature'],
            ['name' => 'Acceso Playa', 'type' => 'property_feature'],
            ['name' => 'Frente al Mar', 'type' => 'property_feature'],
            
            // Características de Propiedad - Estacionamiento
            ['name' => 'Garaje', 'type' => 'property_feature'],
            ['name' => 'Garaje Techado', 'type' => 'property_feature'],
            ['name' => 'Garaje Doble', 'type' => 'property_feature'],
            ['name' => 'Estacionamiento', 'type' => 'property_feature'],
            ['name' => 'Estacionamiento Cubierto', 'type' => 'property_feature'],
            ['name' => 'Múltiples Estacionamientos', 'type' => 'property_feature'],
            ['name' => 'Parqueo Techado', 'type' => 'property_feature'],
            
            // Características de Propiedad - Circulación
            ['name' => 'Ascensor', 'type' => 'property_feature'],
            ['name' => 'Escaleras', 'type' => 'property_feature'],
            ['name' => 'Escaleras de Servicio', 'type' => 'property_feature'],
            ['name' => 'Escalera de Caracol', 'type' => 'property_feature'],
            ['name' => 'Acceso Discapacitados', 'type' => 'property_feature'],
            ['name' => 'Puerta Automática', 'type' => 'property_feature'],
            ['name' => 'Puertas Corredizas', 'type' => 'property_feature'],
            
            // Características de Propiedad - Seguridad
            ['name' => 'Seguridad 24/7', 'type' => 'property_feature'],
            ['name' => 'Vigilancia', 'type' => 'property_feature'],
            ['name' => 'Cámaras de Seguridad', 'type' => 'property_feature'],
            ['name' => 'Sistema de Alarma', 'type' => 'property_feature'],
            ['name' => 'Cerco Perimetral', 'type' => 'property_feature'],
            ['name' => 'Portón de Seguridad', 'type' => 'property_feature'],
            ['name' => 'Control de Acceso', 'type' => 'property_feature'],
            ['name' => 'Intercomunicador', 'type' => 'property_feature'],
            ['name' => 'Puerta Blindada', 'type' => 'property_feature'],
            ['name' => 'Rejas de Seguridad', 'type' => 'property_feature'],
            
            // Características de Propiedad - Espacios Interiores
            ['name' => 'Sala de Estar', 'type' => 'property_feature'],
            ['name' => 'Comedor', 'type' => 'property_feature'],
            ['name' => 'Cocina', 'type' => 'property_feature'],
            ['name' => 'Cocina Abierta', 'type' => 'property_feature'],
            ['name' => 'Cocina Equipada', 'type' => 'property_feature'],
            ['name' => 'Despensa', 'type' => 'property_feature'],
            ['name' => 'Lavandería', 'type' => 'property_feature'],
            ['name' => 'Cuarto de Servicio', 'type' => 'property_feature'],
            ['name' => 'Cuarto de Lavado', 'type' => 'property_feature'],
            ['name' => 'Bodega', 'type' => 'property_feature'],
            ['name' => 'Sótano', 'type' => 'property_feature'],
            ['name' => 'Ático', 'type' => 'property_feature'],
            ['name' => 'Biblioteca', 'type' => 'property_feature'],
            ['name' => 'Oficina', 'type' => 'property_feature'],
            ['name' => 'Sala de Juegos', 'type' => 'property_feature'],
            ['name' => 'Cuarto Multiusos', 'type' => 'property_feature'],
            ['name' => 'Bar', 'type' => 'property_feature'],
            ['name' => 'Wine Cellar', 'type' => 'property_feature'],
            ['name' => 'SPA', 'type' => 'property_feature'],
            
            // Características de Propiedad - Dormitorios
            ['name' => 'Dormitorio Principal', 'type' => 'property_feature'],
            ['name' => 'Dormitorio Principal con Suite', 'type' => 'property_feature'],
            ['name' => 'Dormitorio Secundario', 'type' => 'property_feature'],
            ['name' => 'Dormitorio de Servicio', 'type' => 'property_feature'],
            ['name' => 'Múltiples Dormitorios', 'type' => 'property_feature'],
            
            // Características de Propiedad - Baños
            ['name' => 'Baño Principal', 'type' => 'property_feature'],
            ['name' => 'Baño Completo', 'type' => 'property_feature'],
            ['name' => 'Baño Medio', 'type' => 'property_feature'],
            ['name' => 'Múltiples Baños', 'type' => 'property_feature'],
            ['name' => 'Baño de Servicio', 'type' => 'property_feature'],
            ['name' => 'Tina Jacuzzi', 'type' => 'property_feature'],
            ['name' => 'Ducha con Vapor', 'type' => 'property_feature'],
            ['name' => 'Ducha Lluvia', 'type' => 'property_feature'],
            
            // Características de Propiedad - Acabados
            ['name' => 'Acabados de Lujo', 'type' => 'property_feature'],
            ['name' => 'Pisos de Mármol', 'type' => 'property_feature'],
            ['name' => 'Pisos de Cerámicas', 'type' => 'property_feature'],
            ['name' => 'Pisos de Madera', 'type' => 'property_feature'],
            ['name' => 'Pisos Flotantes', 'type' => 'property_feature'],
            ['name' => 'Techos Altos', 'type' => 'property_feature'],
            ['name' => 'Techos de Catedrales', 'type' => 'property_feature'],
            ['name' => 'Molduras', 'type' => 'property_feature'],
            ['name' => 'Cristales de Seguridad', 'type' => 'property_feature'],
            ['name' => 'Puertas Interiores De Madera', 'type' => 'property_feature'],
            
            // Características de Propiedad - Servicios
            ['name' => 'WiFi Gratis', 'type' => 'property_feature'],
            ['name' => 'Servicio de Internet', 'type' => 'property_feature'],
            ['name' => 'Servicios de Limpieza', 'type' => 'property_feature'],
            ['name' => 'Concierge', 'type' => 'property_feature'],
            ['name' => 'Recepcionista', 'type' => 'property_feature'],
            ['name' => 'Personal de Mantenimiento', 'type' => 'property_feature'],
            
            // Características de Propiedad - Otros
            ['name' => 'Amueblado', 'type' => 'property_feature'],
            ['name' => 'Mascotas Permitidas', 'type' => 'property_feature'],
            ['name' => 'Prohibido Mascotas', 'type' => 'property_feature'],
            ['name' => 'Permiso de Inquilino', 'type' => 'property_feature'],
            ['name' => 'Prohibido Inquilino', 'type' => 'property_feature'],
            ['name' => 'Permitido Dividir', 'type' => 'property_feature'],
            ['name' => 'Listo para Habitar', 'type' => 'property_feature'],
            ['name' => 'Requiere Renovación', 'type' => 'property_feature'],
            ['name' => 'Nuevo', 'type' => 'property_feature'],
            
            // Características de Proyecto - Servicios
            ['name' => 'Áreas Comunes', 'type' => 'project_feature'],
            ['name' => 'Salón Social', 'type' => 'project_feature'],
            ['name' => 'Salón de Eventos', 'type' => 'project_feature'],
            ['name' => 'Salón de Conferencias', 'type' => 'project_feature'],
            ['name' => 'Recepción', 'type' => 'project_feature'],
            ['name' => 'Conserjería', 'type' => 'project_feature'],
            
            // Características de Proyecto - Recreación
            ['name' => 'Piscina Común', 'type' => 'project_feature'],
            ['name' => 'Piscina Infantil', 'type' => 'project_feature'],
            ['name' => 'Jacuzzi Común', 'type' => 'project_feature'],
            ['name' => 'Sauna Común', 'type' => 'project_feature'],
            ['name' => 'Cancha de Tenis', 'type' => 'project_feature'],
            ['name' => 'Cancha de Baloncesto', 'type' => 'project_feature'],
            ['name' => 'Cancha de Balonmano', 'type' => 'project_feature'],
            ['name' => 'Cancha de Raquetbol', 'type' => 'project_feature'],
            ['name' => 'Zona de Juegos', 'type' => 'project_feature'],
            ['name' => 'Parque Infantil', 'type' => 'project_feature'],
            ['name' => 'Gimnasio Común', 'type' => 'project_feature'],
            ['name' => 'Yoga/Meditación', 'type' => 'project_feature'],
            ['name' => 'Billar', 'type' => 'project_feature'],
            ['name' => 'Ping Pong', 'type' => 'project_feature'],
            
            // Características de Proyecto - Seguridad
            ['name' => 'Seguridad 24/7', 'type' => 'project_feature'],
            ['name' => 'Control de Acceso', 'type' => 'project_feature'],
            ['name' => 'Cámaras de Seguridad', 'type' => 'project_feature'],
            ['name' => 'Portón Automático', 'type' => 'project_feature'],
            ['name' => 'Vigilancia Permanente', 'type' => 'project_feature'],
            ['name' => 'Sistema de Alarma', 'type' => 'project_feature'],
            
            // Características de Proyecto - Estacionamiento
            ['name' => 'Parqueo Techado', 'type' => 'project_feature'],
            ['name' => 'Parqueo Descubierto', 'type' => 'project_feature'],
            ['name' => 'Múltiples Niveles de Parqueo', 'type' => 'project_feature'],
            ['name' => 'Carga de Autos Eléctricos', 'type' => 'project_feature'],
            
            // Características de Proyecto - Infraestructura
            ['name' => 'Áreas Verdes', 'type' => 'project_feature'],
            ['name' => 'Jardines Paisajísticos', 'type' => 'project_feature'],
            ['name' => 'Fuentes de Agua', 'type' => 'project_feature'],
            ['name' => 'Senderos', 'type' => 'project_feature'],
            ['name' => 'Iluminación LED', 'type' => 'project_feature'],
            ['name' => 'Sistemas de Riego Automático', 'type' => 'project_feature'],
            
            // Características de Proyecto - Servicios Comerciales
            ['name' => 'Centro Comercial', 'type' => 'project_feature'],
            ['name' => 'Tiendas', 'type' => 'project_feature'],
            ['name' => 'Restaurante', 'type' => 'project_feature'],
            ['name' => 'Cafetería', 'type' => 'project_feature'],
            ['name' => 'Supermercado', 'type' => 'project_feature'],
            ['name' => 'Farmacia', 'type' => 'project_feature'],
            ['name' => 'Clínica Médica', 'type' => 'project_feature'],
            ['name' => 'Peluquería', 'type' => 'project_feature'],
            ['name' => 'Spa y Masajes', 'type' => 'project_feature'],
            
            // Características de Proyecto - Servicios Financieros
            ['name' => 'Banco', 'type' => 'project_feature'],
            ['name' => 'Cajero Automático', 'type' => 'project_feature'],
            
            // Características de Proyecto - Ubicación
            ['name' => 'Cerca de Escuelas', 'type' => 'project_feature'],
            ['name' => 'Cerca de Universidades', 'type' => 'project_feature'],
            ['name' => 'Cerca de Hospital', 'type' => 'project_feature'],
            ['name' => 'Cerca de Iglesia', 'type' => 'project_feature'],
            ['name' => 'Transporte Público', 'type' => 'project_feature'],
            ['name' => 'Acceso a Autopista', 'type' => 'project_feature'],
            ['name' => 'Frente a Parque', 'type' => 'project_feature'],
            ['name' => 'Vista al Mar', 'type' => 'project_feature'],
            ['name' => 'Zona Turística', 'type' => 'project_feature'],
            ['name' => 'Zona Residencial', 'type' => 'project_feature'],
            ['name' => 'Zona Comercial', 'type' => 'project_feature'],
            ['name' => 'Centro de la Ciudad', 'type' => 'project_feature'],
            
            // Características de Proyecto - Amenidades Especiales
            ['name' => 'Biblioteca', 'type' => 'project_feature'],
            ['name' => 'Sala de Música', 'type' => 'project_feature'],
            ['name' => 'Sala de Cine', 'type' => 'project_feature'],
            ['name' => 'Auditorio', 'type' => 'project_feature'],
            ['name' => 'Coworking', 'type' => 'project_feature'],
            ['name' => 'Centro de Negocios', 'type' => 'project_feature'],
            ['name' => 'WiFi en Áreas Comunes', 'type' => 'project_feature'],
        ];

        // Insertar solo si no existen
        foreach ($features as $feature) {
            DB::table('features')->updateOrInsert(
                ['name' => $feature['name'], 'type' => $feature['type']],
                ['name' => $feature['name'], 'type' => $feature['type']]
            );
        }

        echo "\n✅ Features creados exitosamente\n";
    }
}
