import { defineConfig, type Plugin } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

import { buildThemeCss } from './src/styles/theme';

const backendUrl = process.env.VITE_DEV_BACKEND_URL ?? 'http://127.0.0.1:8000';
const reverbUrl = process.env.VITE_DEV_REVERB_URL ?? 'ws://127.0.0.1:8080';

/**
 * Inyecta en la hoja de estilos el tema de Tailwind generado a partir de los
 * tokens del contrato UI/UX aprobado, de modo que `src/styles/tokens.ts` es la
 * única fuente de verdad de la paleta, la tipografía, el espaciado, los radios,
 * las sombras y el movimiento del panel.
 *
 * El plugin se registra antes que Tailwind y con `enforce: 'pre'` para que el
 * tema forme parte del CSS que Tailwind compila.
 */
function designTokensPlugin(): Plugin {
    return {
        name: 'msd-design-tokens',
        enforce: 'pre',
        transform(code, id) {
            if (!/\/src\/index\.css(\?.*)?$/.test(id)) {
                return null;
            }

            return { code: `${code}\n${buildThemeCss()}`, map: null };
        },
    };
}

export default defineConfig({
    plugins: [designTokensPlugin(), react(), tailwindcss()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        proxy: {
            '/api': {
                target: backendUrl,
                changeOrigin: true,
            },
            '/broadcasting': {
                target: backendUrl,
                changeOrigin: true,
            },
            '/app': {
                target: reverbUrl,
                changeOrigin: true,
                ws: true,
            },
        },
    },
    build: {
        // `BUILD_OUT_DIR` permite que la imagen compile el SPA en un directorio
        // distinto del artefacto local (`dist`): la regla `**/dist` de
        // `.dockerignore` excluye el artefacto local del contexto de
        // construcción sin poder excluir la ruta que el `Dockerfile` copia
        // desde el stage `frontend-builder`.
        outDir: process.env.BUILD_OUT_DIR ?? 'dist',
        sourcemap: false,
    },
});
