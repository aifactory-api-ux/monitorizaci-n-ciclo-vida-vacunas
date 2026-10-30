import { useState } from 'react';
import type { FormEvent } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';

import { requestAccessToken } from '@/api/auth';
import { setAccessToken, useAuthSession } from '@/auth/authStore';

interface LoginLocationState {
    from?: string;
}

/**
 * Pantalla de autenticación.
 *
 * El formulario sólo recoge las credenciales y las envía al backend, que es
 * quien valida el par y decide si las credenciales son correctas.
 */
export function LoginPage() {
    const session = useAuthSession();
    const navigate = useNavigate();
    const location = useLocation();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState(false);

    if (session.accessToken !== null && session.accessToken !== '') {
        return <Navigate to="/" replace />;
    }

    const state = location.state as LoginLocationState | null;
    const destination = state?.from ?? '/';

    const handleSubmit = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
        event.preventDefault();
        setPending(true);
        setError(null);

        try {
            const token = await requestAccessToken(email, password);
            setAccessToken(token.access_token, token.expires_in);
            navigate(destination, { replace: true });
        } catch {
            setError('Las credenciales introducidas no son válidas.');
        } finally {
            setPending(false);
        }
    };

    return (
        <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 px-6 py-9">
            <header className="flex flex-col gap-2">
                <h1 className="font-display text-display-28 tracking-tight text-text-high">Vaccine Batch Tracking</h1>
                <p className="text-body-14 text-text-medium">Inicia sesión para acceder al panel de monitorización.</p>
            </header>

            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                <label className="flex flex-col gap-1 text-body-14 text-text-medium">
                    Correo electrónico
                    <input
                        type="email"
                        name="email"
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        className="rounded-sm border border-line bg-surface px-3 py-2 text-body-14 text-text-high focus:border-primary focus:shadow-focus focus:outline-none"
                    />
                </label>

                <label className="flex flex-col gap-1 text-body-14 text-text-medium">
                    Contraseña
                    <input
                        type="password"
                        name="password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        className="rounded-sm border border-line bg-surface px-3 py-2 text-body-14 text-text-high focus:border-primary focus:shadow-focus focus:outline-none"
                    />
                </label>

                {error !== null ? (
                    <p role="alert" data-testid="login-error" className="text-body-14 text-danger">
                        {error}
                    </p>
                ) : null}

                <button
                    type="submit"
                    disabled={pending}
                    className="rounded-md bg-primary px-4 py-2 text-body-14 font-medium text-surface transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:bg-canvas-alt disabled:text-text-low"
                >
                    Entrar
                </button>
            </form>
        </main>
    );
}
