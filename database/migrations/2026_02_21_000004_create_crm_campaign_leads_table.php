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
        Schema::create('crm_campaign_leads', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('campaign_id')->constrained('crm_campaigns')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('crm_leads')->onDelete('cascade');
            
            // Estado en la campaña
            $table->enum('conversion_status', [
                'lead',
                'contactado',
                'calificado',
                'convertido',
                'perdido'
            ])->default('lead');
            
            // Métrica de respuesta
            $table->boolean('respondio')->default(false);
            $table->boolean('interesado')->default(false);
            $table->boolean('compro')->default(false);
            
            // Timestamps
            $table->timestamp('fecha_lead')->nullable();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->timestamp('fecha_conversion')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('campaign_id');
            $table->index('lead_id');
            $table->index('conversion_status');
            $table->unique(['campaign_id', 'lead_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_leads');
    }
};
