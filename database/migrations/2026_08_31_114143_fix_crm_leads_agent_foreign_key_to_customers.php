<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 5 (Leads/CRM) — corregir la FK de crm_leads.agent_id.
     *
     * Los "agentes" del negocio son registros de la tabla `customers`
     * (is_agent=1), igual que en appointments/agent_availabilities.
     * La FK original apuntaba erróneamente a `users.id` (usuarios del panel
     * admin), lo que rompe los leads generados en propiedades de agentes
     * (propertys.added_by = customers.id). Se re-apunta a customers(id).
     */
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->foreign('agent_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->foreign('agent_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
