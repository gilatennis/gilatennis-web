import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const tunnelHost = env.VITE_TUNNEL_HOST;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                transformOnServe: (code, url) => {
                    // url == server.origin. see below
                    return code
                        .replaceAll('http://localhost:5173', url)
                        .replaceAll('http://[::1]:5173', url)
                        .replaceAll('http://0.0.0.0:5173', url);
                }
            }),
            inertia(),
            tailwindcss(),
            svelte(),
            wayfinder({
                formVariants: true,
            }),
        ],
        server: tunnelHost ? {
            host: '0.0.0.0',
            port: 5173,
            origin: `https://${tunnelHost}`,
            cors: {
                origin: [env.APP_URL, `https://${tunnelHost}`],
            },
            hmr: {
                host: tunnelHost,
                protocol: 'wss',
                clientPort: 443,
            },
        } : undefined,
    };
});
