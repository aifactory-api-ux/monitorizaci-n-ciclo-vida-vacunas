import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';

import { advanceBatchPhase } from '@/api/batches';
import { ApiError } from '@/api/httpClient';
import { AdvancePhaseButton } from '@/components/batches/AdvancePhaseButton';
import { BatchDetailPanel } from '@/components/batches/BatchDetailPanel';
import { ReceptionActions } from '@/components/batches/ReceptionActions';
import { ExportTraceabilityReportButton } from '@/components/reports/ExportTraceabilityReportButton';
import { useBatchDetail } from '@/hooks/useBatchDetail';
import { useRealtimeClient } from '@/realtime/useRealtimeClient';
import { BATCH_PHASES } from '@/types/batch';

/**
 * Fase siguiente según el orden de los literales de fase del backend.
 *
 * El frontend sólo ofrece la fase contigua del ciclo; la secuencia de la
 * transición y el gating microbiológico los valida la API, que responde con
 * 422 cuando el avance no está permitido.
 */
export function nextBatchPhase(currentPhase: string): string | null {
    const index = (BATCH_PHASES as readonly string[]).indexOf(currentPhase);

    if (index < 0 || index === BATCH_PHASES.length - 1) {
        return null;
    }

    return BATCH_PHASES[index + 1];
}

/**
 * Detalle de un lote con su gating, su trazabilidad y la exportación del
 * reporte de trazabilidad.
 */
export function BatchDetailPage() {
    const { batchId } = useParams<{ batchId: string }>();
    const realtimeClient = useRealtimeClient();
    const queryClient = useQueryClient();
    const detail = useBatchDetail(batchId ?? null);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleAdvance = async (toPhase: string): Promise<void> => {
        if (batchId === undefined) {
            return;
        }

        setPending(true);
        setError(null);

        try {
            await advanceBatchPhase(batchId, toPhase);
            await queryClient.invalidateQueries({ queryKey: ['batch', batchId] });
        } catch (cause) {
            setError(cause instanceof ApiError ? cause.message : 'No se ha podido avanzar el lote.');
        } finally {
            setPending(false);
        }
    };

    if (detail.isPending) {
        return (
            <main className="mx-auto max-w-5xl px-6 py-9">
                <p className="text-body-14 text-text-medium" data-testid="batch-detail-loading">
                    Cargando lote…
                </p>
            </main>
        );
    }

    if (detail.isError || detail.data === undefined) {
        return (
            <main className="mx-auto max-w-5xl px-6 py-9">
                <p role="alert" data-testid="batch-detail-error" className="text-body-14 text-danger">
                    No se ha podido consultar el lote.
                </p>
            </main>
        );
    }

    const { batch, gating, lims_results: limsResults } = detail.data;
    const targetPhase = nextBatchPhase(batch.phase);

    return (
        <main className="mx-auto flex min-h-screen max-w-5xl flex-col gap-7 px-6 py-9">
            <header className="flex flex-col gap-2">
                <h1 className="font-display text-display-28 tracking-tight text-text-high">
                    Lote {batch.reference_id}
                </h1>
                <nav className="flex gap-4 text-body-14 text-text-medium">
                    <Link to="/">Panel</Link>
                    <Link to="/alerts">Histórico de alertas</Link>
                </nav>
            </header>

            <section className="flex flex-col gap-4">
                <h2 className="text-title-20 text-text-high">Estado del lote</h2>

                <dl className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <dt className="text-label-12 uppercase tracking-wide text-text-low">Fase</dt>
                        <dd data-testid="batch-phase" className="text-body-14 text-text-high">
                            {batch.phase}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-label-12 uppercase tracking-wide text-text-low">Estado</dt>
                        <dd data-testid="batch-status" className="text-body-14 text-text-high">
                            {batch.status}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-label-12 uppercase tracking-wide text-text-low">Gating LIMS</dt>
                        <dd data-testid="batch-gating" className="text-body-14 text-text-high">
                            {gating.approved ? 'Aprobado' : 'Pendiente'}
                        </dd>
                    </div>
                </dl>

                {targetPhase !== null ? (
                    <AdvancePhaseButton
                        batch={batch}
                        gating={gating}
                        toPhase={targetPhase}
                        pending={pending}
                        onAdvance={(toPhase) => {
                            void handleAdvance(toPhase);
                        }}
                    />
                ) : null}

                {error !== null ? (
                    <p role="alert" data-testid="batch-advance-error" className="text-body-14 text-danger">
                        {error}
                    </p>
                ) : null}

                <ReceptionActions batch={batch} />
            </section>

            <section className="flex flex-col gap-4">
                <h2 className="text-title-20 text-text-high">Resultados de LIMS</h2>

                {limsResults.length === 0 ? (
                    <p data-testid="batch-lims-empty" className="text-body-14 text-text-medium">
                        El lote todavía no tiene resultados microbiológicos registrados.
                    </p>
                ) : (
                    <ul data-testid="batch-lims-results" className="flex flex-col gap-2">
                        {limsResults.map((result) => (
                            <li
                                key={result.id}
                                data-testid="batch-lims-result"
                                className="rounded-lg border border-line bg-surface px-4 py-3 text-body-14 text-text-high shadow-card"
                            >
                                {result.test_type}: {result.result}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <BatchDetailPanel batchId={batch.id} realtimeClient={realtimeClient} />

            <section className="flex flex-col gap-4">
                <h2 className="text-title-20 text-text-high">Reporte de trazabilidad</h2>

                <div className="flex flex-wrap gap-3">
                    <ExportTraceabilityReportButton batchId={batch.id} format="csv" />
                    <ExportTraceabilityReportButton batchId={batch.id} format="pdf" />
                </div>
            </section>
        </main>
    );
}
