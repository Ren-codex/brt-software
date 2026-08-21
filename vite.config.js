import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    build: {
        chunkSizeWarningLimit: 4000,
    },
    css: {
        preprocessorOptions: {
            scss: {
                // NOT api: 'modern-compiler'. app.scss reaches Bootstrap through
                // explicitly-relative paths ("./node_modules/bootstrap/scss/...")
                // which the legacy API resolves from the project root and the
                // modern compiler resolves from the importing file, where no
                // node_modules exists -- so the build dies on the first @import.
                // Moving to the modern compiler means rewriting those paths and
                // adding loadPaths, which touches the load-bearing import order in
                // app.scss. Worth doing deliberately, not as a side effect.
                // Bootstrap's own SCSS trips most of the deprecation warnings, but
                // quietDeps only reaches files Sass counts as dependencies -- and
                // because app.scss pulls Bootstrap in through explicitly-relative
                // paths, Sass counts it as ours. So this helps less than it looks:
                // 23 warnings down to 18. Importing Bootstrap via loadPaths instead
                // would fix that and is the same change modern-compiler needs.
                quietDeps: true,
            },
        },
    },
    server: {
        // Without an explicit host, Node resolves localhost to the IPv6 loopback
        // and laravel-vite-plugin writes "http://[::1]:5173" into public/hot,
        // which browsers refuse to load ES modules from. Pin IPv4 instead.
        host: '127.0.0.1',
        port: 5173,
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
    resolve: {
        alias: {
            '@assets': '/public/', // Update this with the correct path to your images
            '@favicon': '/public/images/', // Update this with the correct path to your images
        },
    },
});
