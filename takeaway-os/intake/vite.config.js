import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    cssCodeSplit: false,
    outDir: 'dist',
    emptyOutDir: true,
    lib: {
      entry: 'src/index.jsx',
      name: 'TTOSIntake',
      formats: ['iife'],
      fileName: () => 'intake.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: 'intake.css',
      },
    },
  },
})
