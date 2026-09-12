<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('propertys')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->tinyInteger('status')->default(1)->comment('1=disponible, 0=bloqueado');
            $table->decimal('nightly_price', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['property_id', 'date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_availability');
    }
};
