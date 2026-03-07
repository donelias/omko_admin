<?php

namespace App\Traits;

/**
 * Trait para validaciones y utilidades de República Dominicana
 */
trait DominicanRepublicHelper
{
    /**
     * Validar cédula dominicana
     */
    public function validateCedula(?string $cedula): bool
    {
        if (empty($cedula)) {
            return false;
        }
        
        $cedula = preg_replace('/[^0-9]/', '', $cedula) ?? '';
        
        if (strlen($cedula) !== 11) {
            return false;
        }
        
        // Verificar que no todos los dígitos sean iguales
        $digits = str_split($cedula);
        if (count(array_unique($digits)) === 1) {
            return false;
        }
        
        $weights = [1, 2, 1, 2, 1, 2, 1, 2, 1, 2];
        $sum = 0;
        
        for ($i = 0; $i < 10; $i++) {
            if (!isset($cedula[$i])) {
                return false;
            }
            
            $digit = (int)$cedula[$i];
            $product = $digit * $weights[$i];
            
            if ($product > 9) {
                $product = $product - 9;
            }
            
            $sum += $product;
        }
        
        if (!isset($cedula[10])) {
            return false;
        }
        
        $remainder = $sum % 10;
        $verifier = $remainder === 0 ? 0 : 10 - $remainder;
        
        return $verifier === (int)$cedula[10];
    }

    /**
     * Formato cédula a XXX-XXXXXXX-X
     */
    public function formatCedula(?string $cedula): string
    {
        if (empty($cedula)) {
            return '';
        }
        
        $cedula = preg_replace('/[^0-9]/', '', $cedula) ?? '';
        
        if (strlen($cedula) === 11) {
            return substr($cedula, 0, 3) . '-' . substr($cedula, 3, 7) . '-' . substr($cedula, 10, 1);
        }
        
        return $cedula;
    }

    /**
     * Obtener lista de provincias de RD
     */
    public function getRDProvinces(): array
    {
        return [
            'azua' => 'Azua',
            'bahoruco' => 'Bahoruco',
            'barahona' => 'Barahona',
            'distrito-nacional' => 'Distrito Nacional',
            'duarte' => 'Duarte',
            'elias-pina' => 'Elías Piña',
            'espaillat' => 'Espaillat',
            'independencia' => 'Independencia',
            'la-altagracia' => 'La Altagracia',
            'la-romana' => 'La Romana',
            'la-vega' => 'La Vega',
            'maria-trinidad-sanchez' => 'María Trinidad Sánchez',
            'monsenor-nouel' => 'Monseñor Nouel',
            'monte-cristi' => 'Monte Cristi',
            'monte-plata' => 'Monte Plata',
            'pedernales' => 'Pedernales',
            'peravia' => 'Peravia',
            'puerto-plata' => 'Puerto Plata',
            'punta-cana' => 'Punta Cana',
            'salcedo' => 'Salcedo',
            'samana' => 'Samaná',
            'san-cristobal' => 'San Cristóbal',
            'san-juan' => 'San Juan',
            'san-pedro-de-macoris' => 'San Pedro de Macorís',
            'sanchez-ramirez' => 'Sánchez Ramírez',
            'santiago' => 'Santiago',
            'santiago-rodriguez' => 'Santiago Rodríguez',
            'santo-domingo' => 'Santo Domingo',
            'santo-domingo-este' => 'Santo Domingo Este',
            'santo-domingo-norte' => 'Santo Domingo Norte',
            'santo-domingo-oeste' => 'Santo Domingo Oeste',
            'valverde' => 'Valverde',
        ];
    }

    /**
     * Obtener horarios comerciales típicos de RD
     */
    public function getRDBusinessHours(): array
    {
        return [
            'monday' => ['start' => '09:00', 'end' => '18:00'],
            'tuesday' => ['start' => '09:00', 'end' => '18:00'],
            'wednesday' => ['start' => '09:00', 'end' => '18:00'],
            'thursday' => ['start' => '09:00', 'end' => '18:00'],
            'friday' => ['start' => '09:00', 'end' => '18:00'],
            'saturday' => ['start' => '09:00', 'end' => '14:00'],
            'sunday' => ['start' => null, 'end' => null], // Cerrado
        ];
    }

    /**
     * Obtener días festivos de RD
     */
    public function getRDHolidays(): array
    {
        return [
            '01-01' => 'Año Nuevo',
            '01-06' => 'Epifanía',
            '02-27' => 'Independencia',
            '04-14' => 'Viernes Santo',
            '05-01' => 'Día del Trabajo',
            '08-16' => 'Restauración',
            '11-19' => 'Acción de Gracias (Batalla de la Providencia)',
            '12-25' => 'Navidad',
        ];
    }

    /**
     * Validar número de teléfono dominicano
     * Formatos válidos: +1-809-XXXXXXX, (809) XXXXXXX, 809XXXXXXX
     */
    public function validateRDPhoneNumber(?string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
        
        if (empty($phone)) {
            return false;
        }
        
        $length = strlen($phone);
        
        // Debe tener 10 dígitos (sin el código de país)
        if ($length === 10) {
            $areaCode = substr($phone, 0, 3);
            return in_array($areaCode, ['809', '829', '849']);
        }
        
        // o 11 dígitos (incluyendo el 1 de EEUU)
        if ($length === 11) {
            if ($phone[0] !== '1') {
                return false;
            }
            $areaCode = substr($phone, 1, 3);
            return in_array($areaCode, ['809', '829', '849']);
        }
        
        return false;
    }

    /**
     * Formatear número de teléfono dominicano
     */
    public function formatRDPhoneNumber(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
        
        if (empty($phone)) {
            return '';
        }
        
        $length = strlen($phone);
        
        // Si empieza con 1, remover
        if ($length === 11 && $phone[0] === '1') {
            $phone = substr($phone, 1);
        }
        
        // Formato: +1-809-XXXXXXX
        if (strlen($phone) === 10) {
            return '+1-' . substr($phone, 0, 3) . '-' . substr($phone, 3);
        }
        
        return $phone;
    }
}
