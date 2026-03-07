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
        Schema::create('commission_payment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_commission_id');
            $table->unsignedBigInteger('processed_by_user_id');
            $table->decimal('amount_paid', 15, 2);
            $table->string('payment_method'); // bank_transfer, check, cash, etc.
            $table->string('payment_reference')->nullable(); // Receipt number, transaction ID, etc.
            $table->string('status')->default('success'); // success, failed, pending
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('property_commission_id')->references('id')->on('property_commissions')->onDelete('cascade');
            $table->foreign('processed_by_user_id')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index('property_commission_id');
            $table->index('processed_by_user_id');
            $table->index('created_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_payment_logs');
    }
};
