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
        Schema::create('property_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('agent_user_id'); // Agent receiving commission
            $table->unsignedBigInteger('buyer_user_id')->nullable(); // Buyer (if applicable)
            $table->unsignedBigInteger('seller_user_id')->nullable(); // Seller (if applicable)
            $table->string('transaction_type'); // sale, rental
            $table->decimal('property_price', 15, 2); // Price of property/rental
            $table->decimal('commission_rate', 5, 2); // Commission percentage (e.g., 5.00 for 5%)
            $table->decimal('commission_amount', 15, 2); // Calculated commission amount
            $table->string('status')->default('pending'); // pending, approved, rejected, paid
            $table->string('payment_status')->default('unpaid'); // unpaid, partially_paid, paid
            $table->decimal('amount_paid', 15, 2)->default(0); // Amount already paid
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('property_id')->references('id')->on('propertys')->onDelete('cascade');
            $table->foreign('agent_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('buyer_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('seller_user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('property_id');
            $table->index('agent_user_id');
            $table->index('buyer_user_id');
            $table->index('seller_user_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('transaction_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_commissions');
    }
};
