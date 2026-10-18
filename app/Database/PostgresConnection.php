<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Conexión a PostgreSQL que manda los booleanos como 'true'/'false'.
 *
 * Con PDO::ATTR_EMULATE_PREPARES cada consulta hace un solo viaje a la base
 * (en vez de dos: preparar y ejecutar). Pero Laravel convierte true/false a 1/0,
 * y PostgreSQL no acepta comparar un boolean con un integer. Aquí se corrige eso.
 */
class PostgresConnection extends BasePostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}