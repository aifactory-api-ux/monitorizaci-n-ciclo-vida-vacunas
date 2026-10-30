/**
 * Almacén del token de acceso del panel.
 *
 * Guarda el token JWT que emite `POST /api/v1/auth/token` para que las
 * peticiones a la API y la suscripción al canal privado `batch-monitoring`
 * puedan enviarlo como cabecera `Authorization: Bearer` (véase D9 y D11).
 *
 * El token se conserva en el almacenamiento del navegador, de modo que una
 * recarga de la página no obliga al usuario a volver a autenticarse.
 */

import { useSyncExternalStore } from 'react';

const ACCESS_TOKEN_STORAGE_KEY = 'vaccine-batch-tracking.access-token';
const EXPIRES_AT_STORAGE_KEY = 'vaccine-batch-tracking.access-token-expires-at';

export interface AuthSession {
    accessToken: string | null;
    expiresAt: number | null;
}

type SessionListener = () => void;

const listeners = new Set<SessionListener>();

function storage(): Storage | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        // El almacenamiento puede estar bloqueado por la política del
        // navegador; en ese caso la sesión sólo vive en memoria.
        return null;
    }
}

function readStoredSession(): AuthSession {
    const store = storage();

    if (!store) {
        return { accessToken: null, expiresAt: null };
    }

    const accessToken = store.getItem(ACCESS_TOKEN_STORAGE_KEY);
    const expiresAt = store.getItem(EXPIRES_AT_STORAGE_KEY);

    return {
        accessToken,
        expiresAt: expiresAt === null ? null : Number(expiresAt),
    };
}

let session: AuthSession = readStoredSession();

function notify(): void {
    listeners.forEach((listener) => listener());
}

/**
 * Token de acceso vigente, o null si la sesión no está iniciada.
 */
export function accessToken(): string | null {
    return session.accessToken;
}

/**
 * Instante de expiración del token en milisegundos, o null si se desconoce.
 */
export function accessTokenExpiresAt(): number | null {
    return session.expiresAt;
}

/**
 * Indica si hay un token de acceso disponible.
 */
export function isAuthenticated(): boolean {
    return session.accessToken !== null && session.accessToken !== '';
}

/**
 * Guarda el token de acceso emitido por la API.
 *
 * @param token Token JWT devuelto por `POST /api/v1/auth/token`.
 * @param expiresInSeconds Vigencia del token en segundos, si la API la informa.
 */
export function setAccessToken(token: string, expiresInSeconds?: number): void {
    const expiresAt = expiresInSeconds ? Date.now() + expiresInSeconds * 1000 : null;

    session = { accessToken: token, expiresAt };

    const store = storage();

    store?.setItem(ACCESS_TOKEN_STORAGE_KEY, token);

    if (expiresAt === null) {
        store?.removeItem(EXPIRES_AT_STORAGE_KEY);
    } else {
        store?.setItem(EXPIRES_AT_STORAGE_KEY, String(expiresAt));
    }

    notify();
}

/**
 * Descarta el token de acceso y cierra la sesión.
 */
export function clearAccessToken(): void {
    session = { accessToken: null, expiresAt: null };

    const store = storage();

    store?.removeItem(ACCESS_TOKEN_STORAGE_KEY);
    store?.removeItem(EXPIRES_AT_STORAGE_KEY);

    notify();
}

/**
 * Registra un listener que se invoca cuando cambia la sesión.
 *
 * @returns Función que cancela el registro del listener.
 */
export function subscribeToSession(listener: SessionListener): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/**
 * Instantánea estable de la sesión para `useSyncExternalStore`.
 *
 * El objeto de sesión sólo se sustituye cuando cambia, de modo que la
 * referencia que devuelve es estable entre renderizados.
 */
function sessionSnapshot(): AuthSession {
    return session;
}

/**
 * Suscribe el componente a los cambios de la sesión de autenticación.
 */
export function useAuthSession(): AuthSession {
    return useSyncExternalStore(subscribeToSession, sessionSnapshot, sessionSnapshot);
}
