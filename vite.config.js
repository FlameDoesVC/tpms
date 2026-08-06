import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    // Prefix '' so plain (non-VITE_) keys are readable here. This only feeds
    // the dev server config below; nothing here reaches the client bundle.
    const env = loadEnv(mode, process.cwd(), '');

    /*
     * Never hardcode a machine's LAN address here. host and hmr.host were once
     * pinned to 10.0.0.107, so the dev server stopped booting the moment DHCP
     * handed out a different lease, and it could never have worked on anyone
     * else's machine. 127.0.0.1 is the fix for the problem that address was
     * working around: left undefined, Vite resolves localhost to ::1 and
     * laravel-vite-plugin writes "http://[::1]:5173" into public/hot, which
     * browsers refuse to load module scripts from.
     */
    const host = env.VITE_DEV_HOST || '127.0.0.1';

    /*
     * VITE_DEV_PORT, deliberately not VITE_PORT: compose.yaml publishes
     * ${VITE_PORT} on the host for the case where Vite runs inside the
     * container, so if the host-side dev server read the same variable it would
     * try to bind a port Docker has already taken. Two jobs, two variables.
     */
    const port = Number(env.VITE_DEV_PORT || 5173);

    return {
        server: {
            host,
            port,
            // Fall back to a free port rather than refusing to boot; whatever
            // it lands on, public/hot records the real one.
            strictPort: false,
            hmr: { host },
            // The page is served from http://localhost by the container while
            // this server answers on 127.0.0.1 - different origins, so the
            // module scripts and the HMR client need CORS.
            cors: true,
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
    };
});
