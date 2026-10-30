import { apiRequest } from '@/api/httpClient';

/**
 * Token de acceso emitido por `POST /api/v1/auth/token`.
 */
export interface AccessTokenResponse {
    token_type: string;
    access_token: string;
    expires_in: number;
}

/**
 * Solicita un token de acceso con las credenciales del usuario.
 *
 * El endpoint es público y valida las credenciales en el backend, de modo que
 * el frontend no comprueba el formato del correo ni de la contraseña.
 */
export async function requestAccessToken(email: string, password: string): Promise<AccessTokenResponse> {
    return apiRequest<AccessTokenResponse>('/auth/token', {
        method: 'POST',
        body: { email, password },
    });
}
