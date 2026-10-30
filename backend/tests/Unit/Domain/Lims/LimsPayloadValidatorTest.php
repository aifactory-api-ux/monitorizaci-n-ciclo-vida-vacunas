<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Lims;

use App\Domain\Lims\Entity\LimsResult;
use App\Domain\Lims\Service\LimsPayloadValidator;
use App\Domain\Shared\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de consistencia de las cargas de LIMS Labware (véase D8).
 */
class LimsPayloadValidatorTest extends TestCase
{
    private LimsPayloadValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new LimsPayloadValidator;
    }

    public function test_it_accepts_a_consistent_payload(): void
    {
        $validation = $this->validator->validate($this->consistentPayload());

        $this->assertTrue($validation->isValid());
        $this->assertNull($validation->errorCode());
        $this->assertNull($validation->errorDetail());
    }

    public function test_it_rejects_a_payload_without_a_batch(): void
    {
        $payload = $this->consistentPayload();
        unset($payload['batch_id']);

        $validation = $this->validator->validate($payload);

        $this->assertFalse($validation->isValid());
        $this->assertSame(LimsPayloadValidator::ERROR_MISSING_BATCH_REFERENCE, $validation->errorCode());
        $this->assertNotNull($validation->errorDetail());
    }

    public function test_it_rejects_a_payload_with_an_invalid_batch_identifier(): void
    {
        $payload = $this->consistentPayload();
        $payload['batch_id'] = 'lote-1234';

        $validation = $this->validator->validate($payload);

        $this->assertFalse($validation->isValid());
        $this->assertSame(LimsPayloadValidator::ERROR_INVALID_BATCH_REFERENCE, $validation->errorCode());
    }

    public function test_it_rejects_a_payload_without_a_vaccine_reference(): void
    {
        $payload = $this->consistentPayload();
        unset($payload['reference_id']);

        $validation = $this->validator->validate($payload);

        $this->assertFalse($validation->isValid());
        $this->assertSame(LimsPayloadValidator::ERROR_MISSING_VACCINE_REFERENCE, $validation->errorCode());
    }

    public function test_it_rejects_a_payload_without_a_microbiology_result(): void
    {
        $payload = $this->consistentPayload();
        $payload['results'] = [
            [
                'test_type' => 'Pureza',
                'result' => 'Conforme',
                'result_date' => '2026-02-10T08:30:00+00:00',
            ],
        ];

        $validation = $this->validator->validate($payload);

        $this->assertFalse($validation->isValid());
        $this->assertSame(LimsPayloadValidator::ERROR_MISSING_MICROBIOLOGY_RESULT, $validation->errorCode());
    }

    public function test_it_rejects_a_payload_with_an_empty_result(): void
    {
        $payload = $this->consistentPayload();
        $payload['results'][0]['result'] = '   ';

        $validation = $this->validator->validate($payload);

        $this->assertFalse($validation->isValid());
        $this->assertSame(LimsPayloadValidator::ERROR_EMPTY_RESULT, $validation->errorCode());
    }

    public function test_it_rejects_a_payload_with_an_unparsable_result_date(): void
    {
        $payload = $this->consistentPayload();
        $payload['results'][0]['result_date'] = 'sin-fecha';

        $validation = $this->validator->validate($payload);

        $this->assertFalse($validation->isValid());
        $this->assertSame(LimsPayloadValidator::ERROR_INVALID_RESULT_DATE, $validation->errorCode());
    }

    /**
     * Carga consistente de LIMS Labware.
     *
     * @return array<string, mixed>
     */
    private function consistentPayload(): array
    {
        return [
            'batch_id' => Uuid::v4(),
            'reference_id' => Uuid::v4(),
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
