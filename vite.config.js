import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        // Bind IPv4 explicitly. Left to default, Vite resolves localhost to ::1
        // and laravel-vite-plugin writes "http://[::1]:5173" into public/hot,
        // which some browsers refuse to load module scripts from.
        host: '127.0.0.1',
        port: 5173,
        // Docker Desktop's WSL relay squats on 5173 on this machine, so allow
        // the fallback rather than failing the boot; public/hot tracks the
        // real port automatically.
        strictPort: false,
        hmr: { host: '127.0.0.1' },
    },

    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
