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
        // Tabla para credenciales de Meta de cada agente
        Schema::create('agent_meta_credentials', function (Blueprint $table) {
            $table->id();
            
            // Agente
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            
            // Credenciales Meta
            $table->string('meta_app_id');
            $table->string('meta_app_secret');
            $table->text('meta_access_token');
            $table->string('meta_business_account_id');
            $table->string('meta_ad_account_id')->nullable();
            
            // Webhook
            $table->string('webhook_verify_token')->unique();
            
            // Estado
            $table->boolean('activa')->default(true);
            $table->timestamp('fecha_conexion')->nullable();
            $table->timestamp('fecha_expiracion_token')->nullable();
            
            // Información
            $table->string('nombre_cuenta')->nullable();
            $table->string('email_cuenta')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('agent_id');
            $table->index('activa');
            $table->index('meta_business_account_id');
        });
        
        // Tabla para credenciales de WhatsApp de cada agente
        Schema::create('agent_whatsapp_credentials', function (Blueprint $table) {
            $table->id();
            
            // Agente
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            
            // Credenciales WhatsApp
            $table->string('phone_number_id');
            $table->string('business_account_id');
            $table->text('access_token');
            $table->string('phone_number');
            
            // Webhook
            $table->string('webhook_verify_token')->unique();
            
            // Estado
            $table->boolean('activa')->default(true);
            $table->timestamp('fecha_conexion')->nullable();
            $table->timestamp('fecha_expiracion_token')->nullable();
            
            // Información
            $table->string('nombre_cuenta')->nullable();
            $table->json('numeros_permitidos')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('agent_id');
            $table->index('activa');
            $table->index('business_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_whatsapp_credentials');
        Schema::dropIfExists('agent_meta_credentials');
    }
};
