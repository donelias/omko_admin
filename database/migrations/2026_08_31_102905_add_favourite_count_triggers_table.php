<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FASE 2 (T6): triggers que mantienen propertys.favourite_count sincronizado
     * con favourites, de forma robusta (independiente del código de la app).
     * propertys.total_click ya es el contador de vistas denormalizado (se
     * incrementa al crear property_views) — no se duplica.
     */
    public function up(): void
    {
        // Procedimiento idempotente: elimina triggers existentes con este nombre
        // para permitir re-ejecución sin duplicados.
        DB::unprepared('DROP PROCEDURE IF EXISTS _omko_drop_triggers');
        DB::unprepared('
            CREATE PROCEDURE _omko_drop_triggers()
            BEGIN
                IF EXISTS (SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = "trg_fav_ai") THEN
                    DROP TRIGGER trg_fav_ai;
                END IF;
                IF EXISTS (SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = "trg_fav_ad") THEN
                    DROP TRIGGER trg_fav_ad;
                END IF;
            END
        ');
        DB::unprepared('CALL _omko_drop_triggers()');
        DB::unprepared('DROP PROCEDURE IF EXISTS _omko_drop_triggers');

        // AFTER INSERT en favourites => +1
        DB::unprepared('
            CREATE TRIGGER trg_fav_ai AFTER INSERT ON favourites FOR EACH ROW
            BEGIN
                UPDATE propertys SET favourite_count = favourite_count + 1
                WHERE id = NEW.property_id;
            END
        ');

        // AFTER DELETE en favourites => -1
        DB::unprepared('
            CREATE TRIGGER trg_fav_ad AFTER DELETE ON favourites FOR EACH ROW
            BEGIN
                UPDATE propertys SET favourite_count = GREATEST(favourite_count - 1, 0)
                WHERE id = OLD.property_id;
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_fav_ai');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_fav_ad');
    }
};
