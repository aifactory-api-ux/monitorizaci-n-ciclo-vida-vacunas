import { useMutation, useQueryClient } from '@tanstack/react-query';

import { closeLogisticsCycle, registerPhysicalReception } from '@/api/batches';
import { ApiError } from '@/api/httpClient';
import { BATCH_STATUS_CLOSED, BATCH_STATUS_RECEIVED, type Batch } from '@/types/batch';

export interface ReceptionActionsProps {
    /** Lote sobre el que se registra la recepción o el cierre del ciclo logístico. */
    batch: Batch;
}

/**
 * Acción que el lote admite según el estado que publica la API.
 */
type ReceptionAction = 'reception' | 'closure' | 'closed';

/**
 * Traduce el estado publicado por la API en la acción que la interfaz ofrece.
 *
 * La traducción se limita a leer el estado devuelto: el frontend no calcula
 * ni valida las reglas de la recepción ni del cierre logístico, que residen
 * en el backend (véase D5).
 */
export function receptionActionOf(status: string): ReceptionAction {
    if (status === BATCH_STATUS_CLOSED) {
        return 'closed';
    }

    if (status === BATCH_STATUS_RECEIVED) {
        return 'closure';
    }

    return 'reception';
}

/**
 * Acciones de recepción física y de cierre del ciclo logístico de un lote.
 *
 * El componente ofrece la acción que corresponde al estado publicado por la
 * API y se limita a representar el resultado de la llamada: cuando el backend
 * rechaza la operación con 422, el mensaje publicado se muestra al usuario.
 */
export function ReceptionActions({ batch }: ReceptionActionsProps) {
    const queryClient = useQueryClient();
    const action = receptionActionOf(batch.status);

    const mutation = useMutation({
        mutationFn: async (): Promise<Batch> =>
            action === 'closure' ? closeLogisticsCycle(batch.id) : registerPhysicalReception(batch.id),
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['batch', batch.id] });
        },
    });

    const failureMessage =
        action === 'closure'
            ? 'No se ha podido cerrar el ciclo logístico del lote.'
            : 'No se ha podido registrar la recepción física del lote.';

    return (
        <section aria-labelledby="reception-actions-title" className="flex flex-col gap-3">
            <h3 id="reception-actions-title" className="text-heading-16 text-text-high">
                Recepción y cierre logístico
            </h3>

            {action === 'closed' ? (
                <p data-testid="reception-actions-closed" className="text-body-14 text-success">
                    El ciclo logístico del lote está cerrado.
                </p>
            ) : (
                <>
                    {action === 'closure' ? (
                        <p data-testid="reception-actions-received" className="text-body-14 text-success">
                            La recepción física del lote ya está registrada.
                        </p>
                    ) : null}

                    <button
                        type="button"
                        data-testid={
                            action === 'closure' ? 'close-logistics-cycle-button' : 'register-reception-button'
                        }
                        disabled={mutation.isPending}
                        onClick={() => {
                            mutation.mutate();
                        }}
                        className="self-start rounded-md border border-line bg-surface px-4 py-2 text-body-14 font-medium text-text-high transition-colors hover:border-primary hover:text-primary focus:shadow-focus focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {action === 'closure' ? 'Cerrar ciclo logístico' : 'Registrar recepción física'}
                    </button>
                </>
            )}

            {mutation.isError ? (
                <p role="alert" data-testid="reception-actions-error" className="text-body-14 text-danger">
                    {mutation.error instanceof ApiError ? mutation.error.message : failureMessage}
                </p>
            ) : null}
        </section>
    );
}
