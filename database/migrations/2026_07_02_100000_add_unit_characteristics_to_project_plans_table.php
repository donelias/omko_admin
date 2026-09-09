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
        Schema::table('project_plans', function (Blueprint $table) {
            $table->integer('bedrooms')->nullable()->after('document');
            $table->integer('bathrooms')->nullable()->after('bedrooms');
            $table->integer('kitchen')->nullable()->after('bathrooms');
            $table->integer('dining_room')->nullable()->after('kitchen');
            $table->integer('living_room')->nullable()->after('dining_room');
            $table->decimal('build_area', 10, 2)->nullable()->after('living_room');
            $table->integer('closet')->nullable()->after('build_area');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_plans', function (Blueprint $table) {
            $table->dropColumn(['bedrooms', 'bathrooms', 'kitchen', 'dining_room', 'living_room', 'build_area', 'closet']);
        });
    }
};
