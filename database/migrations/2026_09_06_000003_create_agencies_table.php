<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FASE 8 (T5 - Multi-marca / dominio por agencia)
     *
     * Crea la entidad Agencia (marca/tenant). Cada agencia tiene un slug único,
     * dominios/hosts configurables, branding propio (logo, color primario) y, opcionalmente,
     * un dueño (customer). La resolución de marca será por host o header X-Brand.
     */
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('logo', 191)->nullable();
            $table->string('primary_color', 50)->nullable();
            $table->text('domains')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};