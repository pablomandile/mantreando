import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Config independiente de vite.config.ts a propósito: los plugins de Laravel
// y Wayfinder no hacen falta (ni funcionan) para testear TS puro.
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
