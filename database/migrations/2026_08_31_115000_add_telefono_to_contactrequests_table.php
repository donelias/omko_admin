<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 5 (T2) — capturar teléfono en el formulario de contacto.
     */
    public function up(): void
    {
        Schema::table('contactrequests', function (Blueprint $table) {
            $table->string('telefono', 191)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('contactrequests', function (Blueprint $table) {
            $table->dropColumn('telefono');
        });
    }
};
