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
        Schema::create('crm_interactions', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('lead_id')->constrained('crm_leads')->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            
            // Tipo de interacción
            $table->enum('type', [
                'llamada',
                'mensaje_texto',
                'email',
                'whatsapp',
                'facebook',
                'instagram',
                'visita_personal',
                'video_llamada',
                'nota_interna'
            ])->default('nota_interna');
            
            // Contenido
            $table->text('contenido');
            $table->text('resultado')->nullable();
            
            // Duración (para llamadas)
            $table->integer('duracion_segundos')->nullable();
            
            // Cambios de estado
            $table->enum('status_anterior', [
                'nuevo',
                'contactado',
                'interesado',
                'calificado',
                'ganado',
                'perdido',
                'descartado'
            ])->nullable();
            
            $table->enum('status_nuevo', [
                'nuevo',
                'contactado',
                'interesado',
                'calificado',
                'ganado',
                'perdido',
                'descartado'
            ])->nullable();
            
            // Próxima acción
            $table->text('proxima_accion')->nullable();
            $table->timestamp('fecha_proxima_accion')->nullable();
            
            // IDs externos
            $table->string('external_id')->nullable(); // ID de Meta/WhatsApp
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('lead_id');
            $table->index('agent_id');
            $table->index('type');
            $table->index('created_at');
            $table->index('fecha_proxima_accion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_interactions');
    }
};
