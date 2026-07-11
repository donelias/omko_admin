<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_qualification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_qualification_id')->constrained('pre_qualifications')->cascadeOnDelete();
            $table->string('type', 50)->comment('bank_statements, paystubs, taxes, passport, license_id, other');
            $table->string('label')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_qualification_documents');
    }
};
