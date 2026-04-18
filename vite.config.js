import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  base: '/tools/alleogen/',
  logLevel: 'error',
  plugins: [react()],
  resolve: {
    alias: { '@': path.resolve(__dirname, './src') },
  },
  build: {
    outDir: 'dist',
    sourcemap: false,
  },
  server: {
    // In dev, proxy /tools/alleogen/api/* to a local PHP server.
    // Override via `API_PROXY_TARGET` if your PHP is on a different port.
    proxy: {
      '/tools/alleogen/api': {
        target: process.env.API_PROXY_TARGET || 'http://127.0.0.1:8000',
        changeOrigin: true,
        secure: false,
      },
    },
  },
});
