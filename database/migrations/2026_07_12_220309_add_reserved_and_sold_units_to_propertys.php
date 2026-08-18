<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->integer('sold_units')->nullable()->after('available_units');
            $table->integer('reserved_units')->nullable()->after('sold_units');
        });
    }

    public function down(): void
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->dropColumn(['sold_units', 'reserved_units']);
        });
    }
};
