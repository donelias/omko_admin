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
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            
            // Lead básico
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('whatsapp')->nullable();
            
            // Relaciones
            $table->foreignId('property_id')->nullable()->constrained('properties')->onDelete('set null');
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            
            // Estado
            $table->enum('status', [
                'nuevo',
                'contactado',
                'interesado',
                'calificado',
                'ganado',
                'perdido',
                'descartado'
            ])->default('nuevo');
            
            // Origen
            $table->enum('origin', [
                'meta',
                'whatsapp',
                'formulario',
                'manual',
                'otro'
            ])->default('manual');
            
            // IDs externos
            $table->string('meta_lead_id')->nullable()->unique();
            $table->string('meta_campaign_id')->nullable();
            $table->string('whatsapp_number_id')->nullable();
            
            // Datos adicionales
            $table->text('notas')->nullable();
            $table->json('metadata')->nullable();
            
            // Scoring
            $table->tinyInteger('score')->default(0)->min(0)->max(100);
            
            // Timestamps
            $table->timestamp('fecha_primer_contacto')->nullable();
            $table->timestamp('fecha_ultima_interaccion')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('agent_id');
            $table->index('status');
            $table->index('origin');
            $table->index('property_id');
            $table->index('score');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};
