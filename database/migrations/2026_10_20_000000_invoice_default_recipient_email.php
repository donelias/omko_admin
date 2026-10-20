<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correo de copia interna (Omko) al que llegan las notificaciones de la
     * plataforma. Es independiente del correo oficial (company_email) que se
     * muestra al público en todo el sitio. Si ya existe, no se sobrescribe.
     */
    public function up(): void
    {
        if (! DB::table('settings')->where('type', 'info_email')->exists()) {
            DB::table('settings')->insert([
                'type' => 'info_email',
                'data' => 'omkobr@gmail.com',
            ]);
        }
    }

    public function down(): void
    {
        // No se elimina: puede haber sido editado desde el panel.
    }
};
