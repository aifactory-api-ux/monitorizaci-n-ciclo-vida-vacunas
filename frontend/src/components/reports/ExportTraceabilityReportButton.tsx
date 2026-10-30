import { useState } from 'react';

import { downloadTraceabilityReport } from '@/api/reports';
import type { TraceabilityReportFormat } from '@/api/reports';

export interface ExportTraceabilityReportButtonProps {
    batchId: string;
    format: TraceabilityReportFormat;
    label?: string;
}

/**
 * Botón que descarga el reporte de trazabilidad del lote en el formato indicado.
 */
export function ExportTraceabilityReportButton({
    batchId,
    format,
    label,
}: ExportTraceabilityReportButtonProps) {
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleClick = async (): Promise<void> => {
        setPending(true);
        setError(null);

        try {
            const report = await downloadTraceabilityReport(batchId, format);
            const objectUrl = URL.createObjectURL(report);
            const anchor = document.createElement('a');
            anchor.href = objectUrl;
            anchor.download = `trazabilidad-lote-${batchId}.${format}`;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(objectUrl);
        } catch {
            setError('No se pudo exportar el reporte de trazabilidad.');
        } finally {
            setPending(false);
        }
    };

    return (
        <div>
            <button
                type="button"
                data-testid="export-traceability-report"
                onClick={() => {
                    void handleClick();
                }}
                disabled={pending}
                className="rounded-sm border border-line bg-surface px-3 py-1 text-body-14 font-medium text-text-high transition-colors hover:border-primary hover:text-primary focus:shadow-focus focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
            >
                {label ?? `Exportar reporte de trazabilidad (${format.toUpperCase()})`}
            </button>
            {error !== null ? (
                <p role="alert" data-testid="export-traceability-report-error" className="mt-1 text-body-14 text-danger">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
