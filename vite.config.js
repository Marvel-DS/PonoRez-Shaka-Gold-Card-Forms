import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  root: '.',
  publicDir: false,
  build: {
    outDir: 'public/assets/js',
    emptyOutDir: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'assets/js/main.js'),
      output: {
        entryFileNames: 'main.js',
        format: 'es'
      }
    }
  }
});
