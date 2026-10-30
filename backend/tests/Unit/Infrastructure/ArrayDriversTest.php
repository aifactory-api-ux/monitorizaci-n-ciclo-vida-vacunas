<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Broadcasting\ArrayBroadcaster;
use App\Infrastructure\Queue\ArrayQueue;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verifica los drivers `array` de cola y de broadcasting, que permiten que la
 * suite de pruebas no dependa de MySQL ni de Redis.
 */
final class ArrayDriversTest extends TestCase
{
    public function test_la_cola_en_memoria_conserva_el_trabajo_sin_ejecutarlo(): void
    {
        $queue = Queue::connection('array');

        $this->assertInstanceOf(ArrayQueue::class, $queue);
        $this->assertSame(0, $queue->size());

        $id = $queue->push('App\\Application\\Batches\\AvanzarFase', ['lote' => 'L-1'], 'default');

        $this->assertSame(1, $queue->size());
        $this->assertCount(1, $queue->jobs());

        $job = $queue->jobs()[0];

        $this->assertSame($id, $job['id']);
        $this->assertSame('default', $job['queue']);
        $this->assertStringContainsString('L-1', $job['payload']);
    }

    public function test_la_cola_en_memoria_descarta_los_trabajos_al_vaciarla(): void
    {
        $queue = Queue::connection('array');
        $queue->push('App\\Application\\Batches\\AvanzarFase', ['lote' => 'L-1']);

        $queue->flush();

        $this->assertSame(0, $queue->size());
        $this->assertSame([], $queue->jobs());
    }

    public function test_el_emisor_en_memoria_registra_las_difusiones(): void
    {
        $broadcaster = Broadcast::connection('array');

        $this->assertInstanceOf(ArrayBroadcaster::class, $broadcaster);
        $this->assertSame([], $broadcaster->broadcasts());

        $broadcaster->broadcast(['private-batch-monitoring'], 'batch.status.changed', ['id' => 'L-1']);

        $this->assertCount(1, $broadcaster->broadcasts());
        $this->assertSame(['private-batch-monitoring'], $broadcaster->broadcasts()[0]['channels']);
        $this->assertSame('batch.status.changed', $broadcaster->broadcasts()[0]['event']);
        $this->assertSame(['id' => 'L-1'], $broadcaster->broadcasts()[0]['payload']);
    }
}
