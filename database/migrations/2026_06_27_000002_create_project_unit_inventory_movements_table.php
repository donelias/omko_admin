<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de trazabilidad de movimientos de inventario por tipologia de proyecto.
     *
     * Registra cada cambio de available_units con su causa, actor y estado antes/despues.
     * Permite auditar sobreventa, cancelaciones, devoluciones y ajustes manuales.
     */
    public function up(): void
    {
        Schema::create('project_unit_inventory_movements', function (Blueprint $table) {
            $table->id();

            // Propiedad hija (tipologia) a la que pertenece el movimiento
            $table->unsignedBigInteger('property_id')
                ->comment('ID de la propiedad hija (tipologia) afectada.');

            // Proyecto padre (desnormalizado para consultas rapidas sin join)
            $table->unsignedBigInteger('project_id')
                ->comment('ID del proyecto padre. Desnormalizado para reportes.');

            // Tipo de evento que origino el movimiento
            $table->enum('event_type', [
                'reserve',         // usuario crea reserva -> descuento
                'confirm',         // agente confirma reserva (sin cambio de stock, solo auditoria)
                'cancel',          // cancelacion de reserva -> devolucion
                'expire',          // reserva expirada automaticamente -> devolucion
                'reject',          // agente rechaza -> devolucion
                'manual_adjust',   // ajuste manual por admin/agente
            ])->comment('Tipo de evento que origino el movimiento de inventario.');

            // Cambio neto de unidades (negativo = descuento, positivo = devolucion)
            $table->integer('delta_units')
                ->comment('Cambio neto de unidades. Negativo = descuento. Positivo = devolucion.');

            // Estado antes y despues del movimiento (para auditoria y reconciliacion)
            $table->unsignedInteger('before_units')
                ->comment('Unidades disponibles antes del movimiento.');

            $table->unsignedInteger('after_units')
                ->comment('Unidades disponibles despues del movimiento.');

            // Referencia al appointment/reserva que origino el movimiento (si aplica)
            $table->unsignedBigInteger('appointment_id')
                ->nullable()
                ->comment('ID del appointment relacionado. NULL para ajustes manuales.');

            // Actor que genero el movimiento
            $table->string('actor_type', 50)
                ->nullable()
                ->comment('Tipo de actor: user, agent, admin, system.');

            $table->unsignedBigInteger('actor_id')
                ->nullable()
                ->comment('ID del usuario/agente/admin que genero el movimiento.');

            // Metadata adicional (razon de ajuste manual, notas, etc.)
            $table->text('notes')
                ->nullable()
                ->comment('Notas adicionales. Ej: razon de ajuste manual, contexto del sistema.');

            $table->timestamps();

            // Indices para consultas frecuentes
            $table->index('property_id', 'idx_inv_property');
            $table->index('project_id', 'idx_inv_project');
            $table->index('event_type', 'idx_inv_event_type');
            $table->index('appointment_id', 'idx_inv_appointment');
            $table->index('created_at', 'idx_inv_created_at');

            // FK a propertys
            $table->foreign('property_id')
                ->references('id')
                ->on('propertys')
                ->onDelete('cascade');

            // FK a projects
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('cascade');

            // FK a appointments (nullable, no cascade para preservar historial)
            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_unit_inventory_movements');
    }
};
