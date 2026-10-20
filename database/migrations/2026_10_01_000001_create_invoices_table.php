<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Factura de crédito fiscal.
 *
 * Cubre los dos casos del negocio: comisiones/servicios (concepto libre,
 * como el referimiento de préstamo hipotecario) y ventas de paquetes desde
 * payment_transactions.
 *
 * El RNC del cliente vive AQUÍ y no en customers: se teclea al facturar y
 * cada factura conserva el RNC que tenía en el momento de emitirse, aunque
 * el cliente lo cambie después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Comprobante fiscal
            $table->string('ncf_type', 10)->default('B01'); // B01 crédito fiscal
            $table->string('invoice_number', 40)->unique(); // NCF completo, ej. B0100000006
            $table->unsignedBigInteger('sequence')->unique(); // 000006, de invoice_sequences

            // Enlace opcional con la venta de paquetes (null si es comisión suelta)
            $table->unsignedBigInteger('payment_transaction_id')->nullable();

            // Destinatario, desnormalizado para que el PDF sea auditable
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('client_name', 191);
            $table->string('client_rnc', 40)->nullable();
            $table->string('client_phone', 60)->nullable();
            $table->string('client_city', 120)->nullable();
            $table->string('client_address', 255)->nullable();
            $table->string('client_email', 191)->nullable();

            // Concepto y magnitudes
            $table->text('concept');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);

            // ITBIS: el total del ejemplo es subtotal + itbis_amount
            $table->decimal('itbis_rate', 6, 2)->default(18);
            $table->decimal('itbis_amount', 14, 2)->default(0);

            // Retención de ITBIS: se calcula SOBRE el ITBIS, no sobre el subtotal
            $table->decimal('retention_rate', 6, 2)->default(0);
            $table->decimal('retention_amount', 14, 2)->default(0);

            // total = subtotal + itbis_amount - retention_amount
            $table->decimal('total', 14, 2)->default(0);
            $table->string('currency', 3)->default('DOP');

            // Fechas
            $table->date('issue_date');
            $table->date('due_date')->nullable();

            $table->string('status', 30)->default('emitida'); // borrador, emitida, pagada, anulada
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->onDelete('set null');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status']);
            $table->index(['issue_date']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
