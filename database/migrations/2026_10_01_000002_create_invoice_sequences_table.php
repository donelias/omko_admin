<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador inmutable de NCF por tipo de comprobante.
 *
 * El número fiscal nunca se reutiliza: se reserva con lockForUpdate dentro
 * de la misma transacción que inserta la factura, de modo que dos facturas
 * emitidas en paralelo nunca se adjudican el mismo NCF.
 *
 * Se mantiene separado de `invoices` para que borrar o anular una factura
 * no pueda hacer retroceder la secuencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('ncf_type', 10)->unique();
            $table->unsignedBigInteger('next_sequence')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
