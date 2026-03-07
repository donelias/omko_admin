#!/bin/bash

# Script para Diagnosticar y Ejecutar Migraciones

cd "/Users/mac/Documents/Omko/omko/Nueva version/Omko-Admin"

echo "=========================================="
echo "🔍 DIAGNÓSTICO DE MIGRACIONES"
echo "=========================================="
echo ""

# 1. Verificar PHP
echo "✓ Versión de PHP:"
php -v
echo ""

# 2. Verificar Laravel
echo "✓ Versión de Laravel:"
php artisan --version
echo ""

# 3. Verificar BD
echo "✓ Probando conexión a BD..."
php artisan db:show
echo ""

# 4. Instalación de tabla migrations (si no existe)
echo "✓ Asegurando tabla 'migrations'..."
php artisan migrate:install
echo ""

# 5. Ver estado de migraciones
echo "✓ Estado de migraciones:"
php artisan migrate:status
echo ""

# 6. Ejecutar migración de notificaciones
echo "=========================================="
echo "📦 EJECUTANDO MIGRACIÓN DE NOTIFICACIONES"
echo "=========================================="
php artisan migrate --path=database/migrations/2026_02_21_000008_create_meta_notifications_table.php --verbose

echo ""
echo "=========================================="
if [ $? -eq 0 ]; then
    echo "✅ MIGRACIONES EXITOSAS"
else
    echo "❌ ERROR EN MIGRACIONES - Revisa los logs en storage/logs/laravel.log"
fi
echo "=========================================="
