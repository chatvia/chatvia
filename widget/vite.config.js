import { defineConfig, loadEnv } from 'vite';
import { createHtmlPlugin } from 'vite-plugin-html';
import strip from '@rollup/plugin-strip';

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_');

  // Use VITE_API_URL directly
  const apiUrl = env.VITE_API_URL || '';
  const scriptUrl = env.VITE_SCRIPT_URL;
  const isProduction = mode === 'production';

  return {
    server: {
      open: '/index.html',
      port: 3000,
      host: true,
      allowedHosts: true,
      cors: {
        origin: true,
        credentials: true
      },
      watch: {
        usePolling: true,
        ignored: ['node_modules/**', 'dist/**']
      }
    },
    build: {
      emptyOutDir: true,
      outDir: 'dist',
      rollupOptions: {
        input: {
          widget: 'src/main.js',
          embed: 'src/embed.js'
        },
        output: [
          {
            entryFileNames: `${env.VITE_APP_VERSION}/widget.min.js`,
            format: 'iife',
            sourcemap: false,
            compact: true,
            name: 'ChatWidgetBundle'
          },
          {
            entryFileNames: 'embed.js',
            format: 'iife',
            sourcemap: false,
            compact: true,
            name: 'ChatWidgetEmbed'
          }
        ],
        plugins: [
          isProduction &&
            strip({
              include: ['**/*.js'],
              functions: ['console.*'],
              debugger: true
            })
        ]
      },
      minify: 'terser'
    },
    plugins: [createHtmlPlugin()],
    define: {
      __APP_VERSION__: JSON.stringify(env.VITE_APP_VERSION),
      __API_URL__: JSON.stringify(apiUrl.endsWith('/') ? apiUrl.slice(0, -1) : apiUrl),
      __SCRIPT_URL__: JSON.stringify(scriptUrl),
      __DEV__: JSON.stringify(!isProduction),
      // Live-agent handoff (Reverb) — same env keys as rollup.config.js.
      __REVERB_APP_KEY__: JSON.stringify(env.VITE_REVERB_APP_KEY || ''),
      __REVERB_HOST__: JSON.stringify(env.VITE_REVERB_HOST || ''),
      __REVERB_PORT__: JSON.stringify(env.VITE_REVERB_PORT || '443'),
      __REVERB_SCHEME__: JSON.stringify(env.VITE_REVERB_SCHEME || 'https')
    }
  };
});
