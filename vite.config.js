
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'fs';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/main.css',
                'resources/css/menu.css',
                'resources/css/page.css',
                'resources/css/offers.css',
                'resources/css/tasks.css',
                'resources/css/wallet.css',
                'resources/css/ckeditor.css',
                'resources/css/category.css',
                'resources/css/chat_index.css',
                'resources/css/chat_show.css',
                'resources/css/organizations.css',

                'resources/js/app.js',
                'resources/js/bt_top.js',
                'resources/js/chat_index.js',
                'resources/js/chat/index.js',
                'resources/js/messenger-router.js',
                'resources/js/category-modal.js',
                'resources/js/category-submit.js',
                'resources/js/ckeditor-init.js',
                'resources/js/ckeditor.js',
                'resources/js/cropper-init.js',
                'resources/js/form-validation.js',
                'resources/js/image-uploader.js',
            ],

            refresh: [
                'resources/views/**',
                'resources/images/**',
                'public/images/**',
                'app/Livewire/**',
                'app/View/Components/**',
                'lang/**',
                'resources/lang/**',
                'routes/**',
            ],
        }),
    ],

    server: {
        https: {
            key: fs.readFileSync(
                path.resolve(__dirname, 'storage/certs/privkey.pem')
            ),
            cert: fs.readFileSync(
                path.resolve(__dirname, 'storage/certs/fullchain.pem')
            ),
        },

        host: '0.0.0.0',
        port: 5173,
        strictPort: true,

        hmr: {
            protocol: 'wss',
            host: 'daodes.space',
            clientPort: 443,
            path: '/vite-hmr',
        },

        cors: true,

        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers': 'X-Requested-With, Content-Type, Authorization',
        },

        watch: {
            usePolling: true,
            interval: 300,
        },
    },

    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },

    optimizeDeps: {
        include: ['lodash', 'axios'],
    },
});
