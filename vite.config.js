import { defineConfig } from 'vite';
import path from 'path';
import { existsSync } from 'fs';
import { mkdir, readdir, rm, copyFile } from 'fs/promises';

const STATIC_ASSET_DIRECTORIES = [
  { source: 'assets/icons', target: 'public/assets/icons' },
  { source: 'assets/images', target: 'public/assets/images' },
  { source: 'assets/fonts', target: 'public/assets/fonts' }
];

async function copyDirectoryRecursive(source, destination) {
  await mkdir(destination, { recursive: true });
  const entries = await readdir(source, { withFileTypes: true });

  for (const entry of entries) {
    const sourcePath = path.join(source, entry.name);
    const destinationPath = path.join(destination, entry.name);

    if (entry.isDirectory()) {
      await copyDirectoryRecursive(sourcePath, destinationPath);
    } else if (entry.isFile()) {
      await copyFile(sourcePath, destinationPath);
    }
  }
}

function copyStaticAssets() {
  return {
    name: 'copy-static-assets',
    closeBundle: async () => {
      for (const { source, target } of STATIC_ASSET_DIRECTORIES) {
        const resolvedSource = path.resolve(__dirname, source);

        if (!existsSync(resolvedSource)) {
          continue;
        }

        const resolvedTarget = path.resolve(__dirname, target);
        await rm(resolvedTarget, { recursive: true, force: true });
        await copyDirectoryRecursive(resolvedSource, resolvedTarget);
      }
    }
  };
}

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
  },
  plugins: [copyStaticAssets()]
});
