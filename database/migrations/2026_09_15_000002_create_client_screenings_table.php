<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de un formulario de depuración enviado por un cliente.
 * Un screening pertenece a una propiedad, un agente y opcionalmente a un lead CRM.
 * El análisis IA y la decisión final del agente se guardan aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_screenings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();

            // Datos del cliente (desnormalizados para auditoría)
            $table->string('customer_name', 191);
            $table->string('customer_email', 191)->nullable();
            $table->string('customer_phone', 60)->nullable();
            $table->string('origin', 40)->default('link'); // link, whatsapp, form, admin

            // Estado del proceso
            $table->string('status', 30)->default('pendiente'); // pendiente, en_evaluacion, aprobado, rechazado
            $table->unsignedInteger('score')->default(0);
            $table->string('nivel', 20)->nullable(); // alto, medio, bajo

            // Análisis IA
            $table->text('recomendacion_ia')->nullable();
            $table->json('ia_metadata')->nullable();

            // Decisión del agente (siempre humano)
            $table->string('decision_agente', 20)->nullable(); // aprobado, rechazado
            $table->text('notas_agente')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('propertys')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('lead_id')->references('id')->on('crm_leads')->onDelete('set null');

            $table->index(['agent_id', 'status']);
            $table->index(['property_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_screenings');
    }
};