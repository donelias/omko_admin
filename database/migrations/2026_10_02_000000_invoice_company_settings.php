<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Datos fiscales/bancarios que el comprobante de crédito fiscal imprime en
     * el pie (nota de pago) y en el encabezado. Se insertan solo si faltan,
     * para no sobrescribir valores ya configurados en producción.
     */
    private array $defaults = [
        'company_rnc'         => '133337975',
        'company_website'     => 'omko.do',
        'bank_account'        => '1034398768',
        'bank_name'           => 'Asociación Popular (APAP)',
        'invoice_signer_name' => 'Elisvernith Herrera',
        'invoice_signer_role' => 'CEO Omko Bienes Raíces',
        // Email que aparece en la nota de pago (puede diferir del general).
        'invoice_payment_email' => 'elisvernithherrera@gmail.com',
    ];

    public function up(): void
    {
        foreach ($this->defaults as $type => $data) {
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
        // No se eliminan los settings: podrían haber sido editados desde el panel.
    }
};
