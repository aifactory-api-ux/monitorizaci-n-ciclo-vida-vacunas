import { GatingBlockNotice } from '@/components/batches/GatingBlockNotice';
import { GATING_BLOCKED_STATUS, type Batch, type Gating } from '@/types/batch';

export interface AdvancePhaseButtonProps {
    /** Lote sobre el que se solicita el avance de fase. */
    batch: Batch;
    /** Fase destino de la transición. */
    toPhase: string;
    /** Decisión de gating publicada por la API. */
    gating?: Gating | null;
    /** Callback invocado con la fase destino cuando el usuario avanza el lote. */
    onAdvance?: (toPhase: string) => void;
    /** Indica que la petición de avance está en curso. */
    pending?: boolean;
}

/**
 * Botón de avance de fase de un lote.
 *
 * El botón se deshabilita únicamente cuando la API publica el estado de
 * bloqueo por gating. El frontend no calcula ni valida la regla de gating: se
 * limita a representar el estado devuelto por el backend (véase D5).
 */
export function AdvancePhaseButton({
    batch,
    toPhase,
    gating = null,
    onAdvance,
    pending = false,
}: AdvancePhaseButtonProps) {
    const blocked = batch.status === GATING_BLOCKED_STATUS;

    return (
        <div className="flex flex-col gap-3">
            {blocked ? <GatingBlockNotice status={batch.status} reason={gating?.reason} /> : null}

            <button
                type="button"
                data-testid="advance-phase-button"
                disabled={blocked || pending}
                onClick={() => onAdvance?.(toPhase)}
                className="self-start rounded-md bg-primary px-4 py-2 text-body-14 font-medium text-surface transition-colors hover:bg-primary-hover focus:shadow-focus focus:outline-none disabled:cursor-not-allowed disabled:bg-canvas-alt disabled:text-text-low"
            >
                Avanzar a {toPhase}
            </button>
        </div>
    );
}
