<?php

declare(strict_types=1);

namespace App\Infrastructure\Lims;

use App\Domain\Lims\Port\LimsLabwareClient;
use App\Domain\Shared\Exception\ConfigurationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adaptador HTTP del puerto de integración con LIMS Labware.
 *
 * Sólo emite peticiones de lectura: la integración no puede alterar el estado
 * del sistema externo (véase D7). La URL base y el token se toman de la
 * configuración, que los recibe desde el gestor de secretos del despliegue;
 * si faltan, la operación falla con un error explícito en lugar de usar un
 * valor de reserva.
 */
final class HttpLabwareLimsClient implements LimsLabwareClient
{
    public function fetchBatchResults(?string $batchId = null): array
    {
        $payload = $this->get(
            (string) config('lims.results_path'),
            $batchId === null ? [] : ['batch_id' => $batchId],
        );

        $batches = $payload[(string) config('lims.response_key', 'batches')] ?? null;

        if (! is_array($batches)) {
            return [];
        }

        $result = [];

        foreach ($batches as $batch) {
            if (is_array($batch)) {
                $result[] = $batch;
            }
        }

        return $result;
    }

    public function fetchMicrobiologyApproval(string $batchId): ?array
    {
        $payload = $this->get((string) config('lims.microbiology_path'), ['batch_id' => $batchId]);

        $approval = $payload['approval'] ?? null;

        return is_array($approval) ? $approval : null;
    }

    /**
     * Lee un recurso de LIMS Labware.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws ConfigurationException Si la integración está deshabilitada o le falta configuración.
     * @throws RuntimeException Si LIMS Labware no responde o responde con error.
     */
    private function get(string $path, array $query): array
    {
        if (! config('lims.enabled')) {
            throw ConfigurationException::limsIntegrationDisabled();
        }

        $baseUrl = $this->requiredConfiguration('base_url');
        $token = $this->requiredConfiguration('api_token');
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('lims.timeout', 10))
                ->get($url, $query);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'No se pudo conectar con LIMS Labware para leer los resultados microbiológicos.',
                0,
                $exception,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'LIMS Labware respondió con estado %d a la lectura de resultados microbiológicos.',
                $response->status(),
            ));
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Valor de configuración obligatorio de la integración.
     *
     * @throws ConfigurationException Si el valor falta o está vacío.
     */
    private function requiredConfiguration(string $key): string
    {
        $value = config("lims.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw ConfigurationException::missingLimsConfiguration($key);
        }

        return trim($value);
    }
}
