import tailwindcss from '@tailwindcss/vite';
import { sveltekit } from '@sveltejs/kit/vite';
import { defineConfig } from 'vite';
import path from "path";
import fs from "fs";

export default defineConfig(({ command }) => {
    const isDev = command === 'serve';

    return {
        plugins: [tailwindcss(), sveltekit()],
        resolve: {
            alias: {
                
            }
        },
        server: {
            port: 3088,
            host: true, // Add this to listen on all network interfaces
            ...(isDev && {
                allowedHosts: ['thinkertanker.dv', 'thinkertanker.klams.dv', '.thinkertanker.dv'], 
                https: {
                    key: fs.readFileSync("../certs/thinkertanker.dv-key.pem"),
                    cert: fs.readFileSync("../certs/thinkertanker.dv.pem"),
                },
            }),
        },
        preview: {
            port: 3088,
        },
        optimizeDeps: {
            exclude: ['jsts'],
        },
    };
});
