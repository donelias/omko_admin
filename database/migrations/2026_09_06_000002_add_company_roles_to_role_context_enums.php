<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FASE 8 (T6 restante): ampliar los enums de role_context para soportar
     * los roles de empresa developer/agencia (planes Developer/Agencia).
     * Nota: en MySQL, insertar un valor no contemplado en un ENUM con sql_mode
     * no estricto se convierte silenciosamente a '' — por ello hay que ampliarlos.
     */
    public function up(): void
    {
        foreach (['user_packages', 'payment_transactions', 'advertisements', 'propertys', 'projects'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY COLUMN role_context ENUM('user','agent','general','developer','agencia') NOT NULL DEFAULT 'user'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertimos para no arriesgar regresión; los roles extra son inofensivos.
    }
};