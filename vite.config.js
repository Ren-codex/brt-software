import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    build: {
        chunkSizeWarningLimit: 4000,
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
