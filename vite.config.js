import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  root: '.',
  publicDir: false,
  build: {
    outDir: 'public/assets',
    emptyOutDir: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'assets/js/main.js'),
      output: {
        entryFileNames: 'js/main.js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          const extension = path.extname(assetInfo.name ?? '').toLowerCase();

          if (extension === '.css') {
            return 'css/[name][extname]';
          }

          if (['.woff', '.woff2', '.ttf', '.otf', '.eot'].includes(extension)) {
            return 'fonts/[name][extname]';
          }

          if (['.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.avif'].includes(extension)) {
            return 'images/[name][extname]';
          }

          return 'js/[name][extname]';
        },
        format: 'es'
      }
    }
  }
});
