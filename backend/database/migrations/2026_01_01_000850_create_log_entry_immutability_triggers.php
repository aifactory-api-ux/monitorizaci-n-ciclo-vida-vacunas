<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inmutabilidad del log estructurado en la base de datos (véase D10).
 *
 * La aplicación sólo ofrece la operación de alta del log, pero la inmutabilidad
 * se refuerza en la propia base de datos: los disparadores bloquean cualquier
 * `UPDATE` o `DELETE` sobre `LOG_ENTRY`, de modo que ni una escritura manual
 * ni una migración accidental puedan alterar el historial de auditoría.
 *
 * El disparador aborta la operación con SQLSTATE 45000 y el marcador
 * `log_entry_immutable`, que la capa Api traduce a la respuesta HTTP
 * documentada.
 */
return new class extends Migration
{
    /**
     * Nombre de los disparadores de bloqueo.
     */
    private const TRIGGER_UPDATE = 'log_entry_block_update';

    /**
     * Nombre del disparador de bloqueo de borrado.
     */
    private const TRIGGER_DELETE = 'log_entry_block_delete';

    /**
     * Marcador que identifica el rechazo de la base de datos.
     */
    private const IMMUTABLE_MARKER = 'log_entry_immutable';

    public function up(): void
    {
        $statements = DB::connection()->getDriverName() === 'mysql'
            ? $this->mysqlStatements()
            : $this->sqliteStatements();

        foreach ($statements as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        foreach ([self::TRIGGER_UPDATE, self::TRIGGER_DELETE] as $trigger) {
            DB::unprepared(sprintf('DROP TRIGGER IF EXISTS %s', $trigger));
        }
    }

    /**
     * Disparadores con la sintaxis de MySQL 8.
     *
     * @return list<string>
     */
    private function mysqlStatements(): array
    {
        return [
            sprintf(
                "CREATE TRIGGER %s BEFORE UPDATE ON LOG_ENTRY FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '%s'",
                self::TRIGGER_UPDATE,
                self::IMMUTABLE_MARKER,
            ),
            sprintf(
                "CREATE TRIGGER %s BEFORE DELETE ON LOG_ENTRY FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '%s'",
                self::TRIGGER_DELETE,
                self::IMMUTABLE_MARKER,
            ),
        ];
    }

    /**
     * Disparadores con la sintaxis de SQLite.
     *
     * @return list<string>
     */
    private function sqliteStatements(): array
    {
        return [
            sprintf(
                "CREATE TRIGGER %s BEFORE UPDATE ON LOG_ENTRY BEGIN SELECT RAISE(ABORT, '%s'); END",
                self::TRIGGER_UPDATE,
                self::IMMUTABLE_MARKER,
            ),
            sprintf(
                "CREATE TRIGGER %s BEFORE DELETE ON LOG_ENTRY BEGIN SELECT RAISE(ABORT, '%s'); END",
                self::TRIGGER_DELETE,
                self::IMMUTABLE_MARKER,
            ),
        ];
    }
};
