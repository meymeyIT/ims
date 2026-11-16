import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Bootstrap 5 build (Tailwind removed).
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
