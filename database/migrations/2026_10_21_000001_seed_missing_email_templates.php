<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Plantillas de correo editables que estaban declaradas en
     * HelperService::getEmailTemplatesTypes() pero sin fila en `settings`.
     *
     * Sin estas filas:
     *  - subscription_expiring_soon -> el comando diario fallaba (TypeError).
     *  - user_verification_status   -> correo con texto plano de respaldo.
     *  - new_property_in_category_listing -> correo sin HTML.
     *
     * Si la fila ya existe no se sobrescribe (puede haber sido editada).
     */
    public function up(): void
    {
        $templates = [
            'subscription_expiring_soon_mail_template' => <<<'HTML'
<p>Hola <strong>{user_name}</strong>,</p>
<p>Te informamos que tu suscripción al plan <strong>{package_name}</strong> está próxima a vencer el <strong>{subscription_end_date}</strong>.</p>
<p>Para no perder el acceso a los beneficios de tu plan, te recomendamos renovarla antes de esa fecha.</p>
<p>Si tienes alguna pregunta, puedes contactar a nuestro equipo de soporte.</p>
<p>Gracias por confiar en <strong>{app_name}</strong>.</p>
<p>Saludos cordiales,<br>El equipo de <strong>{app_name}</strong></p>
HTML,
            'user_verification_status_mail_template' => <<<'HTML'
<p>Hola <strong>{user_name}</strong>,</p>
<p>Te informamos sobre el estado de la verificación de tu cuenta en <strong>{app_name}</strong>:</p>
<p><strong>Estado:</strong> {status}</p>
<p>Si tienes alguna pregunta o necesitas asistencia adicional, no dudes en contactar a nuestro equipo de soporte.</p>
<p>Gracias por tu paciencia y colaboración.</p>
<p>Saludos cordiales,<br>El equipo de <strong>{app_name}</strong></p>
HTML,
            'new_property_in_category_listing_mail_template' => <<<'HTML'
<p>Hola <strong>{user_name}</strong>,</p>
<p>¡Tenemos buenas noticias! Se ha publicado una nueva propiedad en la categoría <strong>{category_name}</strong> que podría interesarte.</p>
<p><strong>Propiedad:</strong> {property_name}</p>
<p>Ingresa a <strong>{app_name}</strong> para ver todos los detalles.</p>
<p>Saludos cordiales,<br>El equipo de <strong>{app_name}</strong></p>
HTML,
        ];

        foreach ($templates as $type => $data) {
            if (! DB::table('settings')->where('type', $type)->exists()) {
                DB::table('settings')->insert([
                    'type' => $type,
                    'data' => $data,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No se eliminan: pueden haber sido editadas desde el panel.
    }
};
