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
        Schema::create('commission_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('property_commission_id')->nullable();
            $table->unsignedBigInteger('agent_user_id');
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->string('alert_type'); // unpaid, overdue, suspicious_transaction, high_commission, missing_documentation
            $table->string('severity')->default('warning'); // info, warning, danger, critical
            $table->text('message');
            $table->string('status')->default('unread'); // unread, read, acknowledged, resolved
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('property_commission_id')->references('id')->on('property_commissions')->onDelete('set null');
            $table->foreign('agent_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('admin_user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('property_commission_id');
            $table->index('agent_user_id');
            $table->index('admin_user_id');
            $table->index('alert_type');
            $table->index('severity');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_alerts');
    }
};
