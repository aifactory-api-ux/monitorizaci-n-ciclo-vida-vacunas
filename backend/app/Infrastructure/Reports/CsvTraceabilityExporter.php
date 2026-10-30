<?php

declare(strict_types=1);

namespace App\Infrastructure\Reports;

use App\Domain\Report\Entity\TraceabilityReport;
use App\Domain\Report\Port\TraceabilityReportExporter;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Exportador CSV del modelo de trazabilidad (D15).
 *
 * Genera un CSV en UTF-8 con marca de orden de bytes (BOM) para que las
 * hojas de cálculo de los sistemas Windows interpreten correctamente los
 * acentos, en formato largo `seccion,campo,valor` que resulta legible tanto
 * para personas como para herramientas de auditoría.
 */
final class CsvTraceabilityExporter implements TraceabilityReportExporter
{
    /**
     * Marca de orden de bytes UTF-8 (BOM).
     */
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * Cabecera del CSV en formato largo.
     */
    private const HEADER = ['seccion', 'campo', 'valor'];

    public function export(TraceabilityReport $report): string
    {
        $rows = array_merge(
            [self::HEADER],
            $this->headerRows($report),
            $this->campusRows($report),
            $this->transitionRows($report),
            $this->limsRows($report),
            $this->alertRows($report),
            $this->auditRows($report),
        );

        return self::BYTE_ORDER_MARK.$this->toCsv($rows);
    }

    public function filename(TraceabilityReport $report): string
    {
        return sprintf('trazabilidad-lote-%s.csv', $report->batch()->id());
    }

    public function contentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    /**
     * Datos de cabecera del lote.
     *
     * @return list<list<string>>
     */
    private function headerRows(TraceabilityReport $report): array
    {
        $batch = $report->batch();
        $reference = $report->reference();

        $rows = [
            ['Cabecera', 'lote_id', $batch->id()],
            ['Cabecera', 'reference_id', $batch->referenceId()],
            ['Cabecera', 'referencia_vacuna', $reference?->name() ?? ''],
            ['Cabecera', 'familia_vacuna', $reference?->family() ?? ''],
            ['Cabecera', 'fase', $batch->phase()->value()],
            ['Cabecera', 'estado', $batch->status()->value()],
            ['Cabecera', 'campus_actual_id', $batch->currentCampusId() ?? ''],
            ['Cabecera', 'creado_en', $this->formatDate($batch->createdAt())],
            ['Cabecera', 'actualizado_en', $this->formatDate($batch->updatedAt())],
        ];

        return $rows;
    }

    /**
     * Secuencia de campus recorrida por el lote.
     *
     * @return list<list<string>>
     */
    private function campusRows(TraceabilityReport $report): array
    {
        $rows = [];

        foreach ($report->campusSequence() as $index => $visit) {
            $position = (string) ($index + 1);
            $rows[] = ['Secuencia Campus', $position.'_fase', $visit->phase()->value()];
            $rows[] = ['Secuencia Campus', $position.'_campus_id', $visit->campusId()];
            $rows[] = ['Secuencia Campus', $position.'_campus_nombre', $visit->campusName()];
            $rows[] = ['Secuencia Campus', $position.'_desde', $this->formatDate($visit->since())];
        }

        return $rows;
    }

    /**
     * Transiciones de fase recorridas.
     *
     * @return list<list<string>>
     */
    private function transitionRows(TraceabilityReport $report): array
    {
        $rows = [];

        foreach ($report->transitions() as $index => $transition) {
            $position = (string) ($index + 1);
            $rows[] = ['Transicion', $position.'_id', $transition->id()];
            $rows[] = ['Transicion', $position.'_de_fase', $transition->fromPhase()->value()];
            $rows[] = ['Transicion', $position.'_a_fase', $transition->toPhase()->value()];
            $rows[] = ['Transicion', $position.'_fecha', $this->formatDate($transition->transitionDate())];
        }

        return $rows;
    }

    /**
     * Resultados de LIMS del lote.
     *
     * @return list<list<string>>
     */
    private function limsRows(TraceabilityReport $report): array
    {
        $rows = [];

        foreach ($report->limsResults() as $index => $result) {
            $position = (string) ($index + 1);
            $rows[] = ['Resultado LIMS', $position.'_id', $result->id()];
            $rows[] = ['Resultado LIMS', $position.'_tipo_prueba', $result->testType()];
            $rows[] = ['Resultado LIMS', $position.'_resultado', $result->result()];
            $rows[] = ['Resultado LIMS', $position.'_aprobado_por', $result->approvedBy() ?? ''];
            $rows[] = ['Resultado LIMS', $position.'_fecha_resultado', $this->formatDate($result->resultDate())];
            $rows[] = ['Resultado LIMS', $position.'_origen', $result->source()];
            $rows[] = ['Resultado LIMS', $position.'_actualizado_en', $this->formatDate($result->updatedAt())];
        }

        return $rows;
    }

    /**
     * Alertas generadas durante el ciclo de vida del lote.
     *
     * @return list<list<string>>
     */
    private function alertRows(TraceabilityReport $report): array
    {
        $rows = [];

        foreach ($report->alerts() as $index => $alert) {
            $position = (string) ($index + 1);
            $rows[] = ['Alerta', $position.'_id', $alert->id()];
            $rows[] = ['Alerta', $position.'_tipo', $alert->alertType()->value()];
            $rows[] = ['Alerta', $position.'_severidad', $alert->severity()->value()];
            $rows[] = ['Alerta', $position.'_mensaje', $alert->message()];
            $rows[] = ['Alerta', $position.'_creada_en', $this->formatDate($alert->createdAt())];
        }

        return $rows;
    }

    /**
     * Historial de auditoría del lote.
     *
     * @return list<list<string>>
     */
    private function auditRows(TraceabilityReport $report): array
    {
        $rows = [];

        foreach ($report->entries() as $index => $entry) {
            $position = (string) ($index + 1);
            $rows[] = ['Auditoria', $position.'_id', $entry->id()];
            $rows[] = ['Auditoria', $position.'_tipo_evento', $entry->eventType()];
            $rows[] = ['Auditoria', $position.'_usuario_id', $entry->userId() ?? ''];
            $rows[] = ['Auditoria', $position.'_carga_util', $this->encodePayload($entry->payload())];
            $rows[] = ['Auditoria', $position.'_creado_en', $this->formatDate($entry->createdAt())];
        }

        return $rows;
    }

    /**
     * Serializa las filas al formato CSV.
     *
     * @param  list<list<string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\', "\n");
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * Serializa la carga útil de una entrada de auditoría.
     *
     * @param  array<string, mixed>  $payload
     */
    private function encodePayload(array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Formatea una marca temporal en ISO 8601.
     */
    private function formatDate(?DateTimeImmutable $date): string
    {
        return $date?->format(DateTimeInterface::ATOM) ?? '';
    }
}
