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
        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();
            
            // Información básica
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            
            // Plataforma
            $table->enum('platform', [
                'meta',
                'whatsapp',
                'email',
                'manual'
            ])->default('manual');
            
            // Estado
            $table->enum('status', [
                'borrador',
                'activa',
                'pausada',
                'completada',
                'cancelada'
            ])->default('borrador');
            
            // IDs externos
            $table->string('meta_campaign_id')->nullable()->unique();
            $table->string('meta_adset_id')->nullable();
            $table->string('meta_ad_id')->nullable();
            
            // Presupuesto (en RD$)
            $table->decimal('presupuesto', 15, 2)->default(0);
            $table->decimal('gastado', 15, 2)->default(0);
            
            // Métricas
            $table->integer('impresiones')->default(0);
            $table->integer('clics')->default(0);
            $table->integer('leads_generados')->default(0);
            $table->integer('conversiones')->default(0);
            $table->decimal('cpc', 10, 2)->nullable(); // Costo por clic
            $table->decimal('cpl', 10, 2)->nullable(); // Costo por lead
            $table->decimal('ctr', 5, 2)->nullable();  // Click-through rate
            
            // Audiencia objetivo
            $table->json('audiencia_target')->nullable();
            $table->json('exclusiones')->nullable();
            
            // Propiedades asociadas
            $table->json('property_ids')->nullable();
            
            // Fechas
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('agent_id');
            $table->index('status');
            $table->index('platform');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_campaigns');
    }
};
