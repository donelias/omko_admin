<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 5 (Leads/CRM) — corregir las FK agent_id de crm_interactions y
     * crm_campaigns, que apuntaban a `users` (panel admin) cuando los agentes
     * reales viven en `customers` (is_agent=1). Mismo bug que ya se corrigió
     * para crm_leads.agent_id.
     */
    public function up(): void
    {
        Schema::table('crm_interactions', function (Blueprint $table) {
            $table->dropForeign('crm_interactions_agent_id_foreign');
            $table->foreign('agent_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
        });

        Schema::table('crm_campaigns', function (Blueprint $table) {
            $table->dropForeign('crm_campaigns_agent_id_foreign');
            $table->foreign('agent_id')
                ->references('id')
                ->on('customers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('crm_campaigns', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->foreign('agent_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('crm_interactions', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->foreign('agent_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
