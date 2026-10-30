import { useBatches } from '@/hooks/useBatches';
import { useRealtimeEvents } from '@/hooks/useRealtimeEvents';
import { BatchListItem } from '@/components/dashboard/BatchListItem';
import type { BatchFilters } from '@/api/batches';
import type { RealtimeClient } from '@/realtime/echo';

export interface BatchListProps {
    /** Filtros aplicados a la consulta del listado. */
    filters?: BatchFilters;
    /** Cliente de tiempo real que refresca el listado al recibir eventos. */
    realtimeClient?: RealtimeClient | null;
}

/**
 * Listado paginado de lotes.
 *
 * Los lotes se pintan a partir de la respuesta paginada de la API, con el
 * orden y los campos que devuelve el backend. Cada evento del canal de
 * monitorización invalida la consulta del listado, de modo que la vista se
 * actualiza sin recargar la página (véase D12).
 */
export function BatchList({ filters = {}, realtimeClient = null }: BatchListProps) {
    const batches = useBatches(filters);

    useRealtimeEvents({ client: realtimeClient });

    if (batches.isPending) {
        return (
            <p className="text-body-14 text-text-medium" data-testid="batch-list-loading">
                Cargando lotes…
            </p>
        );
    }

    if (batches.isError) {
        return (
            <p role="alert" className="text-body-14 text-danger" data-testid="batch-list-error">
                No se ha podido consultar el listado de lotes.
            </p>
        );
    }

    if (!batches.data) {
        return null;
    }

    const { batches: items, total, page, last_page: lastPage } = batches.data;

    return (
        <section aria-labelledby="batch-list-title" className="flex flex-col gap-4">
            <h2 id="batch-list-title" className="text-title-20 text-text-high">
                Lotes monitorizados
            </h2>

            <p data-testid="batch-list-summary" className="text-body-13 text-text-medium">
                Página {page} de {lastPage} · {total} lotes
            </p>

            {items.length === 0 ? (
                <p className="text-body-14 text-text-medium" data-testid="batch-list-empty">
                    No hay lotes que coincidan con los filtros aplicados.
                </p>
            ) : (
                <ul data-testid="batch-list" className="flex flex-col gap-3">
                    {items.map((batch) => (
                        <BatchListItem key={batch.id} batch={batch} />
                    ))}
                </ul>
            )}
        </section>
    );
}
