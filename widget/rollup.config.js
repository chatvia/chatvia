import { nodeResolve } from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import terser from '@rollup/plugin-terser';
import replace from '@rollup/plugin-replace';
import strip from '@rollup/plugin-strip';
import json from '@rollup/plugin-json';
import { visualizer } from 'rollup-plugin-visualizer';
import gzipPlugin from 'rollup-plugin-gzip';
import postcss from 'rollup-plugin-postcss';
import postcssImport from 'postcss-import';
import { brotliCompressSync } from 'zlib';
import dotenv from 'dotenv';

// dotenv v17 prints an informational banner on config() by default; keep the
// Rollup build output clean with quiet: true.
dotenv.config({ quiet: true });

const isProduction = process.env.NODE_ENV === 'production';
const appVersion = process.env.VITE_APP_VERSION;

// Use VITE_API_URL directly
const apiUrl = process.env.VITE_API_URL;
const scriptUrl = process.env.VITE_SCRIPT_URL;

// Pusher/Reverb configuration for the live-agent handoff feature. These are
// defaults only: an embed tag can override each one at runtime
// (data-reverb-host / -port / -key / -scheme, see src/endpoints.js). Without a
// key and host, live chat is disabled.
const reverbAppKey = process.env.VITE_REVERB_APP_KEY || '';
const reverbHost = process.env.VITE_REVERB_HOST || '';
const reverbPort = process.env.VITE_REVERB_PORT || '443';
const reverbScheme = process.env.VITE_REVERB_SCHEME || 'https';

// Warn if critical env vars are missing
if (!scriptUrl) {
  console.warn('⚠️  VITE_SCRIPT_URL is not set! The embed script will not work correctly.');
}
if (!apiUrl) {
  console.warn(
    '⚠️  VITE_API_URL is not set! API calls will fail unless the embed tag sets data-api-url.'
  );
}

const sharedEnv = {
  'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development'),
  __APP_VERSION__: JSON.stringify(appVersion),
  __API_URL__: JSON.stringify(apiUrl),
  __SCRIPT_URL__: JSON.stringify(scriptUrl),
  __DEV__: JSON.stringify(!isProduction),
  // Pusher/Reverb config for handoff
  __REVERB_APP_KEY__: JSON.stringify(reverbAppKey),
  __REVERB_HOST__: JSON.stringify(reverbHost),
  __REVERB_PORT__: JSON.stringify(reverbPort),
  __REVERB_SCHEME__: JSON.stringify(reverbScheme)
};

// Custom plugin to generate version.json
function generateVersionFile() {
  return {
    name: 'generate-version-file',
    generateBundle() {
      // Create version.json content
      const versionContent = JSON.stringify({ version: appVersion });

      // Emit the version.json file
      this.emitFile({
        type: 'asset',
        fileName: 'version.json',
        source: versionContent
      });
    }
  };
}

const commonPlugins = [
  json(), // Add JSON plugin to handle .json imports
  nodeResolve({ browser: true, preferBuiltins: false }),
  commonjs(),
  replace({ ...sharedEnv, preventAssignment: true }),
  isProduction &&
    strip({
      include: ['**/*.js'],
      functions: [
        'console.log',
        'console.debug',
        'console.info',
        // Keep console.warn and console.error for debugging production issues
        'assert.*'
      ],
      debugger: true
    }),
  isProduction &&
    terser({
      format: {
        comments: false
      },
      compress: {
        // Do NOT drop_console here. The `strip` plugin above already removes
        // console.log/debug/info, and we intentionally KEEP console.warn /
        // console.error in production for diagnosing live issues. Setting
        // drop_console:true would silently remove warn/error too, defeating
        // that intent (and the warn()/error() helpers in utils.js).
        drop_console: false,
        pure_getters: true,
        unsafe: true,
        unsafe_comps: true,
        passes: 1
      },
      mangle: {
        safari10: true
      }
    })
];

const mainBundleConfig = {
  input: 'src/main.js',
  output: {
    dir: `dist/${appVersion}`,
    // IIFE (classic script), NOT 'es'. embed.js injects this bundle with a
    // plain <script type="text/javascript">, so it must not contain top-level
    // import/export — those throw "export declarations may only appear at top
    // level of a module" when a non-module script is parsed.
    format: 'iife',
    name: 'chatviaWidget',
    // IIFE can't code-split; inline the dynamic import() of api.js
    // (src/widget.js, src/state/messages.js) into the single bundle.
    inlineDynamicImports: true,
    sourcemap: false,
    compact: true,
    entryFileNames: 'widget.min.js',
    chunkFileNames: '[name]-[hash].js'
  },
  plugins: [
    postcss({
      plugins: [postcssImport()],
      minimize: true,
      extract: false,
      inject: true,
      modules: false
    }),
    ...commonPlugins,
    generateVersionFile(), // Generate version.json
    isProduction && gzipPlugin({ fileName: '.gz' }),
    isProduction &&
      gzipPlugin({
        fileName: '.br',
        customCompression: content => brotliCompressSync(Buffer.from(content)),
        additionalFiles: []
      }),
    !isProduction &&
      visualizer({
        filename: 'bundle-analysis.html',
        open: false
      })
  ].filter(Boolean)
};

const embedScriptConfig = {
  input: 'src/embed.js',
  output: {
    dir: 'dist',
    // Embedding sites load this directly via <script src=".../embed.js">, a
    // classic (non-module) script. Must be IIFE so the bundle carries no
    // top-level export/import — otherwise the browser throws "export
    // declarations may only appear at top level of a module".
    format: 'iife',
    name: 'chatviaEmbed',
    sourcemap: false,
    compact: true,
    entryFileNames: 'embed.js',
    chunkFileNames: '[name]-[hash].js'
  },
  plugins: [
    ...commonPlugins,
    generateVersionFile(), // Generate version.json for embed script too
    isProduction && gzipPlugin({ fileName: '.gz' }),
    isProduction &&
      gzipPlugin({
        fileName: '.br',
        customCompression: content => brotliCompressSync(Buffer.from(content)),
        additionalFiles: []
      })
  ].filter(Boolean)
};

export default [mainBundleConfig, embedScriptConfig];
