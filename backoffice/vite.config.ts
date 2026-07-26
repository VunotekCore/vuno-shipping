import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/admin': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/user': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/role': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/permission': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/company': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/document_sequence': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/direccion': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/puesto': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/empleado': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
      '/imagekit': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
})
