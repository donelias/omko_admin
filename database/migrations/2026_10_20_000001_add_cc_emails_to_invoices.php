<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia (CC) adicional por factura. Correos extra que reciben la factura
 * aparte del cliente y de la copia interna de Omko (setting `info_email`).
 * Se guarda como texto libre separado por comas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->text('cc_emails')->nullable()->after('client_email');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('cc_emails');
        });
    }
};
