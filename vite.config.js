import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        validation: resolve(__dirname, 'public/js/validation.js'),
        loading: resolve(__dirname, 'public/js/loading.js'),
        confirmDialog: resolve(__dirname, 'public/js/confirm-dialog.js'),
        mobileEnhancements: resolve(__dirname, 'public/js/mobile-enhancements.js'),
      },
      output: {
        entryFileNames: 'js/[name]-[hash].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/[name]-[hash][extname]';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
    minify: 'esbuild',
  },
  server: {
    port: 3000,
    open: false,
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'public'),
    },
  },
});
