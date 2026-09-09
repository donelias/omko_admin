<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('saved_search_id')->constrained('saved_searches')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('propertys')->cascadeOnDelete();
            $table->boolean('email_sent')->default(false);
            $table->boolean('push_sent')->default(false);
            $table->timestamps();

            $table->unique(['customer_id', 'saved_search_id', 'property_id'], 'alert_unique');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_alerts');
    }
};
