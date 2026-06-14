// vite.config.js
import { fileURLToPath, URL } from 'node:url';

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [
        vue(),
    ],
    build: {
        sourcemap: true,
        rollupOptions: {
            input: {
                search: 'src/pages/search/app.js',
                config: 'src/pages/config/app.js'
            },
            output: {
                entryFileNames: `[name].js`,
                chunkFileNames: `[name]-[hash].js`,
                assetFileNames: `assets/[name][extname]`,
            },
        },
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
        }
    }
})