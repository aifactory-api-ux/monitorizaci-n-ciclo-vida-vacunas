<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;

/**
 * Cola en memoria del proceso PHP.
 *
 * Es el driver `array` de la aplicación: conserva el trabajo en una estructura
 * en memoria del propio proceso, sin intermediario externo, de modo que el
 * entorno de pruebas no depende ni de MySQL ni de Redis. Los trabajos no se
 * ejecutan de forma automática; quedan registrados para poder inspeccionarlos.
 */
class ArrayQueue extends Queue implements QueueContract
{
    /**
     * Trabajo encolado en el proceso, en orden de inserción.
     *
     * @var list<array<string, mixed>>
     */
    private array $jobs = [];

    /**
     * Número de trabajos encolados.
     *
     * @param  string|null  $queue
     */
    public function size($queue = null): int
    {
        return count($this->jobs);
    }

    /**
     * Encola un trabajo.
     *
     * @param  \Closure|string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return string Identificador del trabajo encolado.
     */
    public function push($job, $data = '', $queue = null): string
    {
        $queue = $queue ?: 'default';

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue, $data),
            $queue,
            null,
            fn (string $payload, string $queue): string => $this->store($payload, $queue)
        );
    }

    /**
     * Encola una carga útil ya serializada.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array<string, mixed>  $options
     * @return string Identificador del trabajo encolado.
     */
    public function pushRaw($payload, $queue = null, array $options = []): string
    {
        return $this->store($payload, $queue ?: 'default', $options);
    }

    /**
     * Encola un trabajo para su ejecución posterior.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  \Closure|string|object  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return string Identificador del trabajo encolado.
     */
    public function later($delay, $job, $data = '', $queue = null): string
    {
        $queue = $queue ?: 'default';

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue, $data),
            $queue,
            $delay,
            fn (string $payload, string $queue, $delay): string => $this->store(
                $payload,
                $queue,
                ['available_at' => $this->availableAt($delay)]
            )
        );
    }

    /**
     * Encola varios trabajos.
     *
     * @param  iterable<mixed>  $jobs
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return list<string> Identificadores de los trabajos encolados.
     */
    public function bulk($jobs, $data = '', $queue = null): array
    {
        $ids = [];

        foreach ($jobs as $job) {
            $ids[] = $this->push($job, $data, $queue);
        }

        return $ids;
    }

    /**
     * Extrae el siguiente trabajo de la cola.
     *
     * La cola en memoria nunca ejecuta el trabajo, por lo que no hay nada que
     * extraer.
     *
     * @param  string|null  $queue
     * @return null
     */
    public function pop($queue = null)
    {
        return null;
    }

    /**
     * Trabajos encolados en el proceso.
     *
     * @return list<array<string, mixed>>
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    /**
     * Descarta los trabajos encolados.
     */
    public function flush(): void
    {
        $this->jobs = [];
    }

    /**
     * Registra un trabajo en la cola en memoria.
     *
     * @param  array<string, mixed>  $options
     */
    private function store(string $payload, string $queue, array $options = []): string
    {
        $id = (string) Str::uuid();

        $this->jobs[] = [
            'id' => $id,
            'queue' => $queue,
            'payload' => $payload,
            'available_at' => $options['available_at'] ?? $this->currentTime(),
        ];

        return $id;
    }
}
