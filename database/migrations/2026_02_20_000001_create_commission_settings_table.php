<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->string('commission_type')->default('percentage'); // percentage, fixed
            $table->decimal('commission_value', 10, 2); // 5.00 for 5% or 100.00 for fixed amount
            $table->string('currency')->default('RD$');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('apply_to_rentals')->default(true);
            $table->boolean('apply_to_sales')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_settings');
    }
};
