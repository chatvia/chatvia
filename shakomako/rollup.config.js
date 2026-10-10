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
import { createHash } from 'crypto';
import { readFileSync, readdirSync, statSync } from 'fs';
import { join } from 'path';
import dotenv from 'dotenv';

// dotenv v17 prints an informational banner on config() by default; keep the
// Rollup build output clean with quiet: true.
dotenv.config({ quiet: true });

const isProduction = process.env.NODE_ENV === 'production';

// Every setting below is optional. Without them the widget works out its
// server at runtime: the bundle loads from the folder embed.js came from, the
// API is /widget on that server, and the Reverb settings come with the agent
// settings (see src/endpoints.js). A Chatvia server builds the widget this
// way and serves it from public/shakomako.
const apiUrl = process.env.VITE_API_URL || '';
const scriptUrl = process.env.VITE_SCRIPT_URL || '';

// Pusher/Reverb configuration for the live-agent handoff feature. An embed tag
// can override each one at runtime (data-reverb-host / -port / -key / -scheme).
const reverbAppKey = process.env.VITE_REVERB_APP_KEY || '';
const reverbHost = process.env.VITE_REVERB_HOST || '';
const reverbPort = process.env.VITE_REVERB_PORT || '';
const reverbScheme = process.env.VITE_REVERB_SCHEME || '';

// Release folder name. Release folders are cached forever, so a changed build
// must land in a new one. VITE_APP_VERSION names it by hand; otherwise it is
// the package version plus a hash of everything that goes into the bundle.
const appVersion =
  process.env.VITE_APP_VERSION ||
  `${readPackageVersion()}-${buildHash(['src', 'css', 'languages', 'package-lock.json'])}`;

function readPackageVersion() {
  return JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8')).version;
}

/**
 * Short hash of the given files and folders (read recursively, in a fixed
 * order) and of the build settings above.
 */
function buildHash(paths) {
  const hash = createHash('sha256');
  const add = path => {
    if (statSync(path).isDirectory()) {
      for (const name of readdirSync(path).sort()) add(join(path, name));
      return;
    }
    hash.update(path);
    hash.update(readFileSync(path));
  };
  paths.forEach(add);
  hash.update(
    JSON.stringify([isProduction, apiUrl, scriptUrl, reverbAppKey, reverbHost, reverbPort, reverbScheme])
  );
  return hash.digest('hex').slice(0, 10);
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
