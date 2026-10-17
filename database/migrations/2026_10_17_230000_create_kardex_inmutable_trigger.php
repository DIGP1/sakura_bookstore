<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Impide desde la propia base de datos que el kardex se modifique o se borre,
     * aunque se intente desde pgAdmin o con SQL directo.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_kardex_inmutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'El kardex no se puede modificar ni eliminar. Registra un movimiento de ajuste.';
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_kardex_inmutable ON tbmovimientos_inventario;

            CREATE TRIGGER trg_kardex_inmutable
            BEFORE UPDATE OR DELETE ON tbmovimientos_inventario
            FOR EACH ROW EXECUTE FUNCTION fn_kardex_inmutable();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_kardex_inmutable ON tbmovimientos_inventario;
            DROP FUNCTION IF EXISTS fn_kardex_inmutable();
        SQL);
    }
};
