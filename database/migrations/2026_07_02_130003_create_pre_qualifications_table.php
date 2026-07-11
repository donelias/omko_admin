<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('propertys')->nullOnDelete()->comment('Unidad/tipologia a reservar');
            $table->morphs('financial_entity', 'pre_qual_fin_entity_idx'); // banks or cooperatives
            $table->foreignId('financial_advisor_id')->nullable()->constrained('bank_financial_advisors')->nullOnDelete();
            $table->string('currency', 10)->default('DOP');
            $table->decimal('monthly_income', 12, 2)->nullable()->comment('Ingreso mensual declarado');
            $table->string('status', 30)->default('pending')->comment('pending, approved, rejected');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('customers')->nullOnDelete()->comment('Agente que envio en nombre del cliente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_qualifications');
    }
};
