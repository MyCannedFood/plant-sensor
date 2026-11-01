import { fileURLToPath, URL } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const backend = env.VITE_BACKEND_URL || 'http://localhost:8000';

    return {
        plugins: [vue()],
        resolve: {
            alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
        },
        server: {
            port: 5173,
            // In development the browser only ever talks to the Vite server;
            // /api and /sanctum are forwarded to Laravel. Same origin means
            // the session and XSRF cookies just work, with no CORS involved.
            proxy: {
                '/api': { target: backend, changeOrigin: false },
                '/sanctum': { target: backend, changeOrigin: false },
            },
        },
        build: {
            // ApexCharts (~900 kB) is already split into its own chunk and only
            // loaded on the plant page, so its size is expected.
            chunkSizeWarningLimit: 1000,
        },
    };
});
