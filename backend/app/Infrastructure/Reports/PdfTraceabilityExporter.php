<?php

declare(strict_types=1);

namespace App\Infrastructure\Reports;

use App\Domain\Report\Entity\TraceabilityReport;
use App\Domain\Report\Port\TraceabilityReportExporter;
use DateTimeImmutable;
use DateTimeInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Exportador PDF del modelo de trazabilidad (D15).
 *
 * Genera un documento apto para auditorías regulatorias con la identificación
 * del lote, la secuencia de campus, las transiciones de fase, los resultados
 * de LIMS, las alertas y el historial de auditoría.
 */
final class PdfTraceabilityExporter implements TraceabilityReportExporter
{
    public function export(TraceabilityReport $report): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('serif');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($this->buildHtml($report), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    public function filename(TraceabilityReport $report): string
    {
        return sprintf('trazabilidad-lote-%s.pdf', $report->batch()->id());
    }

    public function contentType(): string
    {
        return 'application/pdf';
    }

    /**
     * Compone el documento HTML que se convierte en PDF.
     */
    private function buildHtml(TraceabilityReport $report): string
    {
        $batch = $report->batch();
        $reference = $report->reference();

        $sections = [
            $this->section('Identificación del lote', [
                ['Lote', $batch->id()],
                ['Referencia del lote', $batch->referenceId()],
                ['Nombre de la referencia', $reference?->name() ?? ''],
                ['Familia de la referencia', $reference?->family() ?? ''],
                ['Fase actual', $batch->phase()->value()],
                ['Estado', $batch->status()->value()],
                ['Campus actual', $batch->currentCampusId() ?? ''],
                ['Creado', $this->formatDate($batch->createdAt())],
                ['Actualizado', $this->formatDate($batch->updatedAt())],
            ]),
        ];

        $campusRows = [];
        foreach ($report->campusSequence() as $index => $visit) {
            $campusRows[] = [
                (string) ($index + 1),
                $visit->phase()->value(),
                $visit->campusName(),
                $this->formatDate($visit->since()),
            ];
        }
        $sections[] = $this->tableSection('Secuencia de campus', ['#', 'Fase', 'Campus', 'Desde'], $campusRows);

        $transitionRows = [];
        foreach ($report->transitions() as $index => $transition) {
            $transitionRows[] = [
                (string) ($index + 1),
                $transition->fromPhase()->value(),
                $transition->toPhase()->value(),
                $this->formatDate($transition->transitionDate()),
            ];
        }
        $sections[] = $this->tableSection('Transiciones de fase', ['#', 'Fase origen', 'Fase destino', 'Fecha'], $transitionRows);

        $limsRows = [];
        foreach ($report->limsResults() as $index => $result) {
            $limsRows[] = [
                (string) ($index + 1),
                $result->testType(),
                $result->result(),
                $result->approvedBy() ?? '',
                $this->formatDate($result->resultDate()),
                $result->source(),
            ];
        }
        $sections[] = $this->tableSection(
            'Resultados de LIMS',
            ['#', 'Tipo de prueba', 'Resultado', 'Aprobado por', 'Fecha', 'Origen'],
            $limsRows,
        );

        $alertRows = [];
        foreach ($report->alerts() as $index => $alert) {
            $alertRows[] = [
                (string) ($index + 1),
                $alert->alertType()->value(),
                $alert->severity()->value(),
                $alert->message(),
                $this->formatDate($alert->createdAt()),
            ];
        }
        $sections[] = $this->tableSection(
            'Alertas',
            ['#', 'Tipo', 'Severidad', 'Mensaje', 'Creada'],
            $alertRows,
        );

        $auditRows = [];
        foreach ($report->entries() as $index => $entry) {
            $auditRows[] = [
                (string) ($index + 1),
                $entry->eventType(),
                $entry->userId() ?? '',
                $this->formatDate($entry->createdAt()),
            ];
        }
        $sections[] = $this->tableSection('Auditoría', ['#', 'Evento', 'Usuario', 'Fecha'], $auditRows);

        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
        $html .= '<style>';
        $html .= 'body{font-family:serif;font-size:11px;}';
        $html .= 'h1{font-size:18px;margin-bottom:4px;}';
        $html .= 'h2{font-size:13px;margin-top:16px;margin-bottom:4px;}';
        $html .= 'table{border-collapse:collapse;width:100%;}';
        $html .= 'th,td{border:1px solid #333;padding:3px 5px;text-align:left;}';
        $html .= 'th{background-color:#eeeeee;}';
        $html .= '</style></head><body>';
        $html .= '<h1>Reporte de trazabilidad de lote</h1>';
        $html .= '<p>Documento generado para auditoría regulatoria del ciclo de vida de vacunas.</p>';

        foreach ($sections as $section) {
            $html .= $section;
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Construye una sección de detalle de dos columnas.
     *
     * @param  list<list<string>>  $rows
     */
    private function section(string $title, array $rows): string
    {
        $html = sprintf('<h2>%s</h2><table>', $this->escape($title));

        foreach ($rows as $row) {
            $html .= sprintf(
                '<tr><th style="width:35%%">%s</th><td>%s</td></tr>',
                $this->escape($row[0]),
                $this->escape($row[1]),
            );
        }

        return $html.'</table>';
    }

    /**
     * Construye una sección tabular con cabecera.
     *
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function tableSection(string $title, array $headers, array $rows): string
    {
        $html = sprintf('<h2>%s</h2><table>', $this->escape($title));
        $html .= '<thead><tr>';

        foreach ($headers as $header) {
            $html .= sprintf('<th>%s</th>', $this->escape($header));
        }

        $html .= '</tr></thead><tbody>';

        if ($rows === []) {
            $html .= sprintf(
                '<tr><td colspan="%d">Sin registros</td></tr>',
                count($headers),
            );
        }

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $html .= sprintf('<td>%s</td>', $this->escape($cell));
            }

            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    /**
     * Escapa el contenido para insertarlo en el documento HTML.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Formatea una marca temporal en ISO 8601.
     */
    private function formatDate(?DateTimeImmutable $date): string
    {
        return $date?->format(DateTimeInterface::ATOM) ?? '';
    }
}
