<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->decimal('price', 15, 2);
            $table->decimal('price_per_sqm', 10, 2)->nullable();
            $table->enum('status', ['listed', 'sold', 'rented', 'price_changed', 'delisted'])->default('listed');
            $table->enum('transaction_type', ['sale', 'rental'])->default('sale');
            $table->integer('days_on_market')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('property_id');
            $table->index('status');
            $table->index('transaction_type');
            $table->index('created_at');
            $table->index(['property_id', 'created_at']);

            $table->foreign('property_id')->references('id')->on('propertys')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_history');
    }
};