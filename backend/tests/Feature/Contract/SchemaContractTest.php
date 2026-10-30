<?php

declare(strict_types=1);

namespace Tests\Feature\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SchemaInspector;
use Tests\TestCase;

/**
 * Contrato de esquema de las ocho entidades del sistema.
 *
 * La comprobación consulta `information_schema` cuando la conexión es MySQL,
 * que es el motor del despliegue, y el catálogo de SQLite cuando la suite se
 * ejecuta en memoria. En ambos casos se validan las tablas, las columnas con
 * su tipo y su nulabilidad, las claves primarias, las claves foráneas y los
 * índices de búsqueda del contrato.
 */
class SchemaContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tablas del contrato de esquema.
     *
     * @var list<string>
     */
    private const TABLES = [
        'VACCINE_REFERENCE',
        'CAMPUS',
        'APP_USER',
        'VACCINE_BATCH',
        'PHASE_TRANSITION',
        'LIMS_RESULT',
        'ALERT',
        'LOG_ENTRY',
    ];

    /**
     * Columnas del contrato: tabla => columna => tipo, longitud y nulabilidad.
     *
     * @var array<string, array<string, array{type: string|array<string, string>, length: int|null, nullable: bool}>>
     */
    private const COLUMNS = [
        'VACCINE_REFERENCE' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'name' => ['type' => 'string', 'length' => 150, 'nullable' => false],
            'family' => ['type' => 'string', 'length' => 100, 'nullable' => false],
            'created_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
            'updated_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
        ],
        'CAMPUS' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'name' => ['type' => 'string', 'length' => 100, 'nullable' => false],
            'created_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
            'updated_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
        ],
        'APP_USER' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'name' => ['type' => 'string', 'length' => 150, 'nullable' => false],
            'email' => ['type' => 'string', 'length' => 255, 'nullable' => false],
            'password' => ['type' => 'string', 'length' => 255, 'nullable' => false],
            'role' => ['type' => 'string', 'length' => 50, 'nullable' => false],
            'created_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
            'updated_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
        ],
        'VACCINE_BATCH' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'reference_id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'phase' => ['type' => 'string', 'length' => 50, 'nullable' => false],
            'status' => ['type' => 'string', 'length' => 50, 'nullable' => false],
            'current_campus_id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'created_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
            'updated_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
        ],
        'PHASE_TRANSITION' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'batch_id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'from_phase' => ['type' => 'string', 'length' => 50, 'nullable' => false],
            'to_phase' => ['type' => 'string', 'length' => 50, 'nullable' => false],
            'transition_date' => ['type' => 'timestamp', 'length' => null, 'nullable' => false],
        ],
        'LIMS_RESULT' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'batch_id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'test_type' => ['type' => 'string', 'length' => 100, 'nullable' => false],
            'result' => ['type' => 'string', 'length' => 50, 'nullable' => false],
            'approved_by' => ['type' => 'string', 'length' => 150, 'nullable' => true],
            'result_date' => ['type' => 'timestamp', 'length' => null, 'nullable' => false],
        ],
        'ALERT' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'batch_id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'alert_type' => ['type' => 'string', 'length' => 100, 'nullable' => false],
            'severity' => ['type' => 'string', 'length' => 50, 'nullable' => false],
            'message' => ['type' => 'text', 'length' => null, 'nullable' => false],
            'created_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
        ],
        'LOG_ENTRY' => [
            'id' => ['type' => 'string', 'length' => 36, 'nullable' => false],
            'batch_id' => ['type' => 'string', 'length' => 36, 'nullable' => true],
            'user_id' => ['type' => 'string', 'length' => 36, 'nullable' => true],
            'event_type' => ['type' => 'string', 'length' => 100, 'nullable' => false],
            'payload' => ['type' => ['mysql' => 'json', 'sqlite' => 'text'], 'length' => null, 'nullable' => false],
            'created_at' => ['type' => 'timestamp', 'length' => null, 'nullable' => true],
        ],
    ];

    /**
     * Claves foráneas del contrato: tabla => columna => tabla referenciada.
     *
     * @var array<string, array<string, string>>
     */
    private const FOREIGN_KEYS = [
        'VACCINE_BATCH' => [
            'reference_id' => 'VACCINE_REFERENCE',
            'current_campus_id' => 'CAMPUS',
        ],
        'PHASE_TRANSITION' => [
            'batch_id' => 'VACCINE_BATCH',
        ],
        'LIMS_RESULT' => [
            'batch_id' => 'VACCINE_BATCH',
        ],
        'ALERT' => [
            'batch_id' => 'VACCINE_BATCH',
        ],
        'LOG_ENTRY' => [
            'batch_id' => 'VACCINE_BATCH',
            'user_id' => 'APP_USER',
        ],
    ];

    /**
     * Índices de búsqueda del contrato: tabla => índice => columnas.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const INDEXES = [
        'VACCINE_REFERENCE' => [
            'idx_vaccine_reference_family' => ['family'],
        ],
        'APP_USER' => [
            'idx_app_user_role' => ['role'],
        ],
        'VACCINE_BATCH' => [
            'idx_vaccine_batch_reference' => ['reference_id'],
            'idx_vaccine_batch_phase' => ['phase'],
            'idx_vaccine_batch_status' => ['status'],
            'idx_vaccine_batch_campus' => ['current_campus_id'],
            'idx_vaccine_batch_created_at' => ['created_at'],
            'idx_vaccine_batch_phase_status' => ['phase', 'status'],
            'idx_vaccine_batch_phase_created' => ['phase', 'created_at', 'id'],
            'idx_vaccine_batch_reference_phase_created' => ['reference_id', 'phase', 'created_at'],
        ],
        'PHASE_TRANSITION' => [
            'idx_phase_transition_batch' => ['batch_id'],
            'idx_phase_transition_date' => ['transition_date'],
            'idx_phase_transition_batch_date' => ['batch_id', 'transition_date'],
            'idx_phase_transition_batch_phase_date' => ['batch_id', 'from_phase', 'transition_date'],
        ],
        'LIMS_RESULT' => [
            'idx_lims_result_batch' => ['batch_id'],
            'idx_lims_result_batch_test' => ['batch_id', 'test_type'],
        ],
        'ALERT' => [
            'idx_alert_batch' => ['batch_id'],
            'idx_alert_severity' => ['severity'],
            'idx_alert_batch_severity' => ['batch_id', 'severity'],
            'idx_alert_batch_severity_created' => ['batch_id', 'severity', 'created_at'],
        ],
        'LOG_ENTRY' => [
            'idx_log_entry_batch' => ['batch_id'],
            'idx_log_entry_user' => ['user_id'],
            'idx_log_entry_event' => ['event_type'],
            'idx_log_entry_created_at' => ['created_at'],
        ],
    ];

    private SchemaInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inspector = SchemaInspector::forDefaultConnection();
    }

    public function test_it_creates_the_tables_of_the_schema_contract(): void
    {
        $tables = $this->inspector->tables();

        foreach (self::TABLES as $table) {
            $this->assertContains($table, $tables, sprintf('Falta la tabla "%s" del contrato.', $table));
        }
    }

    public function test_every_table_has_exactly_the_columns_of_the_contract(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $this->assertSame(
                array_keys($columns),
                array_keys($this->inspector->columns($table)),
                sprintf('Las columnas de "%s" no coinciden con el contrato.', $table),
            );
        }
    }

    public function test_every_column_has_the_type_and_the_nullability_of_the_contract(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $actual = $this->inspector->columns($table);

            foreach ($columns as $column => $expectation) {
                $this->assertArrayHasKey($column, $actual, sprintf('Falta la columna "%s"."', $column));

                $this->assertSame(
                    $this->expectedType($expectation['type']),
                    $actual[$column]['type'],
                    sprintf('El tipo de "%s"."%s" no coincide con el contrato.', $table, $column),
                );

                $this->assertSame(
                    $expectation['nullable'],
                    $actual[$column]['nullable'],
                    sprintf('La nulabilidad de "%s"."%s" no coincide con el contrato.', $table, $column),
                );

                if ($actual[$column]['length'] !== null) {
                    $this->assertSame(
                        $expectation['length'],
                        $actual[$column]['length'],
                        sprintf('La longitud de "%s"."%s" no coincide con el contrato.', $table, $column),
                    );
                }
            }
        }
    }

    public function test_every_table_uses_a_uuid_primary_key(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertSame('id', $this->inspector->primaryKey($table), sprintf('La clave primaria de "%s" no es "id".', $table));
        }
    }

    public function test_the_foreign_keys_of_the_contract_exist(): void
    {
        foreach (self::FOREIGN_KEYS as $table => $keys) {
            $actual = $this->inspector->foreignKeys($table);

            foreach ($keys as $column => $referencedTable) {
                $this->assertArrayHasKey($column, $actual, sprintf('Falta la clave foránea "%s"."%s".', $table, $column));

                $this->assertSame($referencedTable, $actual[$column]['table']);
                $this->assertSame('id', $actual[$column]['column']);
            }
        }
    }

    public function test_the_search_indexes_of_the_contract_exist(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            $actual = $this->inspector->indexes($table);

            foreach ($indexes as $index => $columns) {
                $this->assertArrayHasKey($index, $actual, sprintf('Falta el índice "%s" de "%s".', $index, $table));

                $this->assertSame($columns, $actual[$index], sprintf('Las columnas del índice "%s" no coinciden.', $index));
            }
        }
    }

    public function test_the_catalogues_keep_their_unique_constraints(): void
    {
        $this->assertContains(['name'], array_values($this->inspector->uniqueIndexes('CAMPUS')));
        $this->assertContains(['email'], array_values($this->inspector->uniqueIndexes('APP_USER')));
    }

    /**
     * Tipo esperado para el motor inspeccionado.
     *
     * @param  string|array<string, string>  $type
     */
    private function expectedType(string|array $type): string
    {
        if (is_array($type)) {
            return $type[$this->inspector->driver()] ?? $type['mysql'];
        }

        return $type;
    }
}
