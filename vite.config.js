import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/calendar.js', 'resources/js/scan.js', 'resources/js/signature.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
