<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Logging;

use App\Domain\Audit\Entity\LogEntry;
use App\Domain\Shared\ValueObject\Uuid;
use App\Infrastructure\Logging\JsonAuditLogger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

/**
 * Log de auditoría en JSON estructurado: emite la misma entrada persistida.
 */
class JsonAuditLoggerTest extends TestCase
{
    public function test_it_emits_the_entry_as_structured_json(): void
    {
        $handler = new TestHandler;
        $logger = new Logger('audit_json', [$handler]);

        $entry = LogEntry::record(
            'batch.created',
            [
                'phase' => 'Fase de cultivo',
                'status' => 'En curso',
                'current_campus_id' => Uuid::v4(),
                'reference_id' => Uuid::v4(),
            ],
            Uuid::v4(),
            Uuid::v4(),
        );

        (new JsonAuditLogger($logger))->emit($entry);

        $records = $handler->getRecords();

        $this->assertCount(1, $records);
        $this->assertSame('audit.entry', $records[0]->message);
        $this->assertSame($entry->id(), $records[0]->context['id']);
        $this->assertSame($entry->batchId(), $records[0]->context['batch_id']);
        $this->assertSame($entry->userId(), $records[0]->context['user_id']);
        $this->assertSame($entry->eventType(), $records[0]->context['event_type']);
        $this->assertSame($entry->payload(), $records[0]->context['payload']);
        $this->assertSame($entry->createdAt()->format(DATE_ATOM), $records[0]->context['created_at']);
    }

    public function test_the_channel_is_configured_to_emit_json(): void
    {
        $this->assertSame(JsonFormatter::class, config('logging.channels.audit_json.formatter'));
    }
}
