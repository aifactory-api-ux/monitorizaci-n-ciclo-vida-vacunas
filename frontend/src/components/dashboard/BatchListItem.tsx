import { Link } from 'react-router-dom';

import type { BatchSearchItem } from '@/types/batch';

export interface BatchListItemProps {
    /** Lote mostrado en la fila. */
    batch: BatchSearchItem;
}

/**
 * Fila del listado de lotes.
 *
 * Enlaza con la página de detalle del lote para que el seguimiento del ciclo
 * de vida sea accesible desde el listado.
 */
export function BatchListItem({ batch }: BatchListItemProps) {
    return (
        <li
            data-testid="batch-list-item"
            className="rounded-lg border border-line bg-surface px-4 py-3 shadow-card"
        >
            <Link
                to={`/batches/${batch.id}`}
                className="text-heading-16 text-text-high underline-offset-4 hover:text-primary hover:underline"
            >
                {batch.reference_id}
            </Link>

            <p className="text-body-14 text-text-medium">{batch.reference_name}</p>

            <dl className="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-label-12 text-text-low">
                <div className="flex gap-1">
                    <dt>Familia</dt>
                    <dd className="font-medium text-text-medium">{batch.reference_family}</dd>
                </div>
                <div className="flex gap-1">
                    <dt>Fase</dt>
                    <dd className="font-medium text-text-medium">{batch.phase}</dd>
                </div>
                <div className="flex gap-1">
                    <dt>Estado</dt>
                    <dd className="font-medium text-text-medium">{batch.status}</dd>
                </div>
            </dl>
        </li>
    );
}
