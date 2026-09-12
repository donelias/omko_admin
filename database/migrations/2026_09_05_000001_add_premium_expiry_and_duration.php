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
        Schema::table('propertys', function (Blueprint $table) {
            $table->timestamp('premium_expiry_date')->nullable()->after('is_premium');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('premium_expiry_date')->nullable()->after('is_premium');
        });

        Schema::table('pay_as_you_gos', function (Blueprint $table) {
            $table->integer('duration_days')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->dropColumn('premium_expiry_date');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('premium_expiry_date');
        });

        Schema::table('pay_as_you_gos', function (Blueprint $table) {
            $table->dropColumn('duration_days');
        });
    }
};