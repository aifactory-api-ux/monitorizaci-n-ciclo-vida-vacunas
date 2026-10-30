<?php

declare(strict_types=1);

namespace App\Infrastructure\Backup;

use App\Domain\Backup\Port\CriticalDataStore;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Adaptador del almacén de datos críticos sobre la conexión de base de datos.
 *
 * Exporta las filas de las tablas críticas mediante el constructor de
 * consultas y las incorpora en el destino respetando el orden de dependencia
 * declarado. La incorporación no borra nada: una fila que el destino ya
 * contiene se conserva, de modo que el log inmutable de auditoría nunca se
 * altera y la restauración es idempotente (véase D19).
 */
final class EloquentCriticalDataStore implements CriticalDataStore
{
    /**
     * Número de filas que se insertan en cada consulta.
     */
    private const CHUNK_SIZE = 200;

    /**
     * @param  list<string>  $tables  Tablas críticas en orden de dependencia.
     */
    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly ?string $connectionName,
        private readonly array $tables,
    ) {}

    public function exportTables(): array
    {
        $connection = $this->connection();

        $exported = [];

        foreach ($this->tables as $table) {
            $rows = $connection->table($table)->get();

            $exported[$table] = array_map(
                static fn (object $row): array => (array) $row,
                $rows->all(),
            );
        }

        return $exported;
    }

    public function importTables(array $tables): void
    {
        $connection = $this->connection();

        foreach ($this->tables as $table) {
            $rows = $tables[$table] ?? [];

            if (! is_array($rows) || $rows === []) {
                continue;
            }

            $existing = $this->existingKeys($connection, $table);

            $pending = array_values(array_filter(
                $rows,
                static fn (mixed $row): bool => ! is_array($row)
                    || ! array_key_exists('id', $row)
                    || ! isset($existing[(string) $row['id']]),
            ));

            foreach (array_chunk($pending, self::CHUNK_SIZE) as $chunk) {
                $connection->table($table)->insert($chunk);
            }
        }
    }

    /**
     * Claves primarias ya presentes en la tabla indicada.
     *
     * @return array<string, true>
     */
    private function existingKeys(ConnectionInterface $connection, string $table): array
    {
        $keys = [];

        foreach ($connection->table($table)->pluck('id')->all() as $key) {
            $keys[(string) $key] = true;
        }

        return $keys;
    }

    /**
     * Conexión sobre la que se exporta e importa el datastore.
     */
    private function connection(): ConnectionInterface
    {
        return $this->connections->connection($this->connectionName);
    }
}
