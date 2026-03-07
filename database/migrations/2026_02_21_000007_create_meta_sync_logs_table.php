<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meta_sync_logs', function (Blueprint $table) {
            $table->id();
            
            // Relación con agente
            $table->foreignId('agent_id')
                ->constrained('users')
                ->onDelete('cascade');
            
            // Tipo de evento
            $table->enum('tipo', [
                'webhook_received',
                'webhook_error',
                'lead_created',
                'lead_updated',
                'lead_sync_error',
                'facebook_lead_created',
                'campaign_created_in_meta',
                'campaign_creation_error',
                'sync_completed',
                'sync_error',
                'sync_exception',
                'message_sent',
                'token_renewed',
                'error',
            ])->index();
            
            // Datos del evento (JSON)
            $table->json('datos')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices para queries rápidas
            $table->index(['agent_id', 'created_at']);
            $table->index(['agent_id', 'tipo']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_sync_logs');
    }
};
