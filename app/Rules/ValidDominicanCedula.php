<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validar formato de Cédula de Identidad Dominicana
 * Formato: XXX-XXXXXXX-X
 */
class ValidDominicanCedula implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // Validar que no sea null o vacío
        if (empty($value)) {
            $fail('La cédula es requerida');
            return;
        }
        
        // Convertir a string si es necesario
        $value = (string)$value;
        
        // Remover caracteres especiales
        $cedula = preg_replace('/[^0-9]/', '', $value) ?? '';
        
        // Debe tener 11 dígitos
        if (strlen($cedula) !== 11) {
            $fail('El formato de cédula no es válido. Use el formato: XXX-XXXXXXX-X');
            return;
        }
        
        // Verificar que no sean todos los mismos dígitos
        $digits = str_split($cedula);
        if (count(array_unique($digits)) === 1) {
            $fail('El formato de cédula no es válido. Use el formato: XXX-XXXXXXX-X');
            return;
        }
        
        // Algoritmo de validación de cédula dominicana
        $weights = [1, 2, 1, 2, 1, 2, 1, 2, 1, 2];
        $sum = 0;
        
        for ($i = 0; $i < 10; $i++) {
            if (!isset($cedula[$i])) {
                $fail('El formato de cédula no es válido. Use el formato: XXX-XXXXXXX-X');
                return;
            }
            
            $digit = (int)$cedula[$i];
            $product = $digit * $weights[$i];
            
            if ($product > 9) {
                $product = $product - 9;
            }
            
            $sum += $product;
        }
        
        if (!isset($cedula[10])) {
            $fail('El formato de cédula no es válido. Use el formato: XXX-XXXXXXX-X');
            return;
        }
        
        $remainder = $sum % 10;
        $verifier = $remainder === 0 ? 0 : 10 - $remainder;
        $cedulaVerifier = (int)$cedula[10];
        
        if ($verifier !== $cedulaVerifier) {
            $fail('El formato de cédula no es válido. Use el formato: XXX-XXXXXXX-X');
        }
    }
}
