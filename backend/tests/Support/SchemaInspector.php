<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inspección del esquema de la base de datos.
 *
 * Consulta `information_schema` cuando la conexión es MySQL (el motor del
 * despliegue) y el catálogo de SQLite cuando la suite se ejecuta en memoria.
 * En ambos casos expone el mismo contrato: tablas, columnas con su tipo y su
 * nulabilidad, índices y claves foráneas.
 */
final class SchemaInspector
{
    public function __construct(private readonly string $driver) {}

    /**
     * Inspector de la conexión por defecto.
     */
    public static function forDefaultConnection(): self
    {
        return new self(DB::connection()->getDriverName());
    }

    /**
     * Motor de la base de datos inspeccionada.
     */
    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * Tablas existentes, en mayúsculas.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $rows = $this->isMySql()
            ? DB::select(
                'SELECT TABLE_NAME AS name FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'',
            )
            : DB::select(
                'SELECT name FROM sqlite_master WHERE type = \'table\' AND name NOT LIKE \'sqlite_%\'',
            );

        $tables = array_map(fn (object $row): string => strtoupper($this->firstValue($row)), $rows);
        sort($tables);

        return array_values($tables);
    }

    /**
     * Columnas de una tabla con su tipo, longitud y nulabilidad.
     *
     * @return array<string, array{type: string, length: int|null, nullable: bool}>
     */
    public function columns(string $table): array
    {
        $table = $this->assertTableName($table);

        if ($this->isMySql()) {
            $rows = DB::select(
                'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            );

            $columns = [];
            foreach ($rows as $row) {
                $values = array_values((array) $row);
                $columns[(string) $values[0]] = [
                    'type' => $this->normalizeType((string) $values[1]),
                    'length' => $values[2] === null ? null : (int) $values[2],
                    'nullable' => strtoupper((string) $values[3]) === 'YES',
                ];
            }

            return $columns;
        }

        $columns = [];
        foreach (DB::select(sprintf('PRAGMA table_info("%s")', $table)) as $row) {
            $values = array_values((array) $row);
            $columns[(string) $values[1]] = [
                'type' => $this->normalizeType((string) $values[2]),
                'length' => null,
                'nullable' => (int) $values[3] === 0,
            ];
        }

        return $columns;
    }

    /**
     * Índices de una tabla con sus columnas, en el orden de definición.
     *
     * @return array<string, list<string>>
     */
    public function indexes(string $table): array
    {
        $table = $this->assertTableName($table);

        if ($this->isMySql()) {
            $rows = DB::select(
                'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 ORDER BY INDEX_NAME, SEQ_IN_INDEX',
                [$table],
            );

            $indexes = [];
            foreach ($rows as $row) {
                $values = array_values((array) $row);
                $indexes[(string) $values[0]][] = (string) $values[1];
            }

            return $indexes;
        }

        $indexes = [];
        foreach (DB::select(sprintf('PRAGMA index_list("%s")', $table)) as $row) {
            $values = array_values((array) $row);
            $name = (string) $values[1];

            $columns = [];
            foreach (DB::select(sprintf('PRAGMA index_info("%s")', $name)) as $indexRow) {
                $indexValues = array_values((array) $indexRow);
                $columns[] = (string) $indexValues[2];
            }

            $indexes[$name] = $columns;
        }

        return $indexes;
    }

    /**
     * Índices únicos de una tabla con sus columnas.
     *
     * @return array<string, list<string>>
     */
    public function uniqueIndexes(string $table): array
    {
        $table = $this->assertTableName($table);

        if ($this->isMySql()) {
            $rows = DB::select(
                'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
                 ORDER BY INDEX_NAME, SEQ_IN_INDEX',
                [$table],
            );

            $indexes = [];
            foreach ($rows as $row) {
                $values = array_values((array) $row);
                $indexes[(string) $values[0]][] = (string) $values[1];
            }

            return $indexes;
        }

        $unique = [];
        foreach ($this->indexes($table) as $name => $columns) {
            $isUnique = false;

            foreach (DB::select(sprintf('PRAGMA index_list("%s")', $table)) as $row) {
                $values = array_values((array) $row);

                if ((string) $values[1] === $name) {
                    $isUnique = (int) $values[2] === 1;
                }
            }

            if ($isUnique) {
                $unique[$name] = $columns;
            }
        }

        return $unique;
    }

    /**
     * Clave foránea de una tabla: columna => tabla y columna referenciadas.
     *
     * @return array<string, array{table: string, column: string}>
     */
    public function foreignKeys(string $table): array
    {
        $table = $this->assertTableName($table);

        if ($this->isMySql()) {
            $rows = DB::select(
                'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                [$table],
            );

            $keys = [];
            foreach ($rows as $row) {
                $values = array_values((array) $row);
                $keys[(string) $values[0]] = [
                    'table' => strtoupper((string) $values[1]),
                    'column' => (string) $values[2],
                ];
            }

            return $keys;
        }

        $keys = [];
        foreach (DB::select(sprintf('PRAGMA foreign_key_list("%s")', $table)) as $row) {
            $values = array_values((array) $row);
            $keys[(string) $values[3]] = [
                'table' => strtoupper((string) $values[2]),
                'column' => (string) $values[4],
            ];
        }

        return $keys;
    }

    /**
     * Columna de la clave primaria de una tabla.
     */
    public function primaryKey(string $table): ?string
    {
        $table = $this->assertTableName($table);

        if ($this->isMySql()) {
            $rows = DB::select(
                'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = \'PRIMARY\'',
                [$table],
            );

            if ($rows === []) {
                return null;
            }

            $values = array_values((array) $rows[0]);

            return (string) $values[0];
        }

        foreach (DB::select(sprintf('PRAGMA table_info("%s")', $table)) as $row) {
            $values = array_values((array) $row);

            if ((int) $values[5] > 0) {
                return (string) $values[1];
            }
        }

        return null;
    }

    /**
     * Indica si la conexión es MySQL.
     */
    public function isMySql(): bool
    {
        return $this->driver === 'mysql';
    }

    /**
     * Traduce el tipo declarado por el motor a la categoría del contrato.
     */
    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'varchar', 'char', 'string' => 'string',
            'text', 'mediumtext', 'longtext' => 'text',
            'timestamp', 'datetime' => 'timestamp',
            'json' => 'json',
            'int', 'integer', 'bigint', 'smallint' => 'integer',
            default => strtolower($type),
        };
    }

    /**
     * Primer valor de una fila consultada, sea cual sea el nombre de la columna.
     */
    private function firstValue(object $row): string
    {
        $values = array_values((array) $row);

        return (string) $values[0];
    }

    /**
     * Valida el nombre de la tabla consultado.
     *
     * @throws InvalidArgumentException Si el nombre no es válido.
     */
    private function assertTableName(string $table): string
    {
        if (preg_match('/^[A-Z_]+$/', $table) !== 1) {
            throw new InvalidArgumentException(sprintf('Nombre de tabla no válido: "%s".', $table));
        }

        return $table;
    }
}
