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
                allowedHosts: ['councilofcrows.dv', '.councilofcrows.dv'], 
                https: {
                    key: fs.readFileSync("../certs/councilofcrows.dv-key.pem"),
                    cert: fs.readFileSync("../certs/councilofcrows.dv.pem"),
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
