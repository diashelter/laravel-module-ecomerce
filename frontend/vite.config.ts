import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// The dev server runs inside the "frontend" container and is reached through Nginx
// (http://localhost:APP_PORT), which also proxies /api and /sanctum to Laravel.
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    allowedHosts: true,
    hmr: {
      // The browser connects to Nginx, not directly to the container port.
      clientPort: Number(process.env.APP_PORT ?? 8080),
    },
    watch: {
      // Bind mounts on macOS/Windows do not always propagate file system events.
      usePolling: true,
      interval: 300,
    },
  },
})
