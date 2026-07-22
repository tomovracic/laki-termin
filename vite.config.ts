import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig } from 'vite';

// Temporary: default resources/js/routes/index.ts is locked (Windows ACL).
// Generate + resolve Wayfinder output under resources/js/wf until that file is deleted.
const wayfinderPath = 'resources/js/wf';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            path: wayfinderPath,
        }),
    ],
    resolve: {
        alias: {
            '@/routes': path.resolve(__dirname, `${wayfinderPath}/routes`),
            '@/actions': path.resolve(__dirname, `${wayfinderPath}/actions`),
            '@/wayfinder': path.resolve(__dirname, `${wayfinderPath}/wayfinder`),
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
});
