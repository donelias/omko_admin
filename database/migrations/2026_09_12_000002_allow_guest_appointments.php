<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite citas de visitantes (guest-to-appointment) sin forzar login:
     * - user_id pasa a ser nullable (el visitante no tiene cuenta).
     * - Se agregan columnas para los datos del visitante y el lead CRM asociado.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->unsignedBigInteger('lead_id')->nullable()->after('guest_phone')->index();

            $table->foreign('user_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('crm_leads')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['guest_name', 'guest_email', 'guest_phone', 'lead_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }
};