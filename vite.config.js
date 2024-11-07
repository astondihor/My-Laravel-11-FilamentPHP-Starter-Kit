import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/backend/app.scss',
                'resources/sass/frontend/site.scss',
                'resources/js/backend/app.js',
                'resources/js/frontend/site.js',
                'resources/sass/filament/admin/theme.scss',
            ],
            refresh: true,
        }),
    ],
});
