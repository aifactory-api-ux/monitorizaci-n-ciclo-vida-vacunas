<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\Port\LimsLabwareClient;
use Tests\Doubles\FakeLabwareLimsClient;

/**
 * Utilidades de la integración con LIMS Labware para las pruebas de feature.
 *
 * Enlazan el doble programable del cliente de LIMS Labware en el contenedor
 * para poder preparar las cargas que la importación debe recoger (véase D7).
 */
trait InteractsWithLims
{
    /**
     * Enlaza el doble de LIMS Labware con las cargas indicadas.
     *
     * @param  array<string, mixed>  ...$payloads
     */
    protected function limsClientWith(array ...$payloads): FakeLabwareLimsClient
    {
        $client = new FakeLabwareLimsClient;
        $client->withPayloads($payloads);

        config(['lims.enabled' => true]);

        $this->app->instance(LimsLabwareClient::class, $client);

        return $client;
    }

    /**
     * Importa la aprobación microbiológica del lote desde LIMS Labware.
     */
    protected function importLimsApproval(string $batchId, string $referenceId): FakeLabwareLimsClient
    {
        $client = $this->limsClientWith($this->limsPayload($batchId, $referenceId));

        $this->artisan('lims:sync')->assertExitCode(0);

        return $client;
    }

    /**
     * Carga consistente de LIMS Labware con la aprobación microbiológica.
     *
     * @return array<string, mixed>
     */
    protected function limsPayload(string $batchId, string $referenceId): array
    {
        return [
            'batch_id' => $batchId,
            'reference_id' => $referenceId,
            'results' => [
                [
                    'test_type' => LimsResult::TEST_TYPE_MICROBIOLOGY,
                    'result' => LimsResult::RESULT_APPROVED,
                    'approved_by' => 'Responsable de LIMS Labware',
                    'result_date' => '2026-02-10T08:30:00+00:00',
                    'updated_at' => '2026-02-11T09:00:00+00:00',
                ],
            ],
        ];
    }
}
