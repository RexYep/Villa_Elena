import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin.js',
                'resources/js/admin-charts.js',
                'resources/js/admin-calendar.js',
                'resources/js/portal.js',
                'resources/js/portal-calendar.js',
                'resources/js/auth.js',
                'resources/js/staff.js',
                'resources/js/payment.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
