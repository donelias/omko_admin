#!/bin/bash
# OMKO - Ejecuta el scheduler de Laravel cada minuto (dispara el comando
# price:fetch-exchange-rates (diario 08:00) y el resto de tareas programadas).
export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
cd '/Users/hectorgalindez/Proyectos Completos/omko/omko-agosto-2027 /Omko-Admin-Panel-v1.6.0/Omko-Admin ' || exit 1
/opt/homebrew/bin/php artisan schedule:run >> /tmp/omko-scheduler.log 2>&1