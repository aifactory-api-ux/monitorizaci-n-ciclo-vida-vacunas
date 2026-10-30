export interface GatingBlockNoticeProps {
    /** Estado publicado por la API para el lote. */
    status: string;
    /** Motivo del bloqueo publicado por la API. */
    reason?: string | null;
}

/**
 * Aviso que representa el bloqueo de un lote por Gating LIMS.
 *
 * El aviso reproduce el estado y el motivo que devuelve la API; no evalúa la
 * regla de gating, que reside en el backend (véase D5).
 */
export function GatingBlockNotice({ status, reason }: GatingBlockNoticeProps) {
    return (
        <div
            role="status"
            data-testid="gating-block-notice"
            className="rounded-lg border border-warning bg-warning-soft px-4 py-3"
        >
            <p className="text-body-14 font-medium text-warning">{status}</p>

            {reason ? <p className="mt-1 text-body-14 text-warning">{reason}</p> : null}
        </div>
    );
}
