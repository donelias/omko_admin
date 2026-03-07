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
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->decimal('price', 15, 2);
            $table->decimal('price_per_sqm', 10, 2)->nullable();
            $table->enum('status', ['listed', 'sold', 'rented', 'price_changed', 'delisted'])->default('listed');
            $table->enum('transaction_type', ['sale', 'rental'])->default('sale');
            $table->integer('days_on_market')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Índices para búsquedas rápidas
            $table->index('property_id');
            $table->index('status');
            $table->index('transaction_type');
            $table->index('created_at');
            $table->index(['property_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
