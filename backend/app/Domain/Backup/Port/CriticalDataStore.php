<?php

declare(strict_types=1);

namespace App\Domain\Backup\Port;

/**
 * Puerto del almacén de datos críticos de la aplicación.
 *
 * El puerto expone la exportación y la incorporación de las filas de las
 * tablas críticas, sin que el dominio conozca el motor de base de datos ni la
 * sintaxis SQL (véase D19).
 */
interface CriticalDataStore
{
    /**
     * Exporta las filas de todas las tablas críticas.
     *
     * @return array<string, list<array<string, mixed>>> Filas indexadas por tabla.
     */
    public function exportTables(): array;

    /**
     * Incorpora las filas de las tablas críticas en el almacén de destino.
     *
     * La incorporación no borra el historial: cada fila del respaldo se
     * incorpora cuando el destino no la contiene todavía, de modo que la
     * restauración es idempotente y compatible con el log inmutable.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables  Filas indexadas por tabla.
     */
    public function importTables(array $tables): void;
}
