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
        // Guardará 'USD' o 'DOP'. Por defecto dejamos 'USD' para no dañar las propiedades viejas.
        $table->string('currency', 3)->default('USD')->after('price'); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('propertys', function (Blueprint $table) {
        $table->dropColumn('currency');
    });
}
};
