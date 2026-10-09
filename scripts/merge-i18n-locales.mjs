#!/usr/bin/env node
/**
 * Deep-merge locale _fragments into the base locale JSON files.
 *
 *   node scripts/merge-i18n-locales.mjs           # merge every locale
 *   node scripts/merge-i18n-locales.mjs en ar     # merge only these
 *
 * Fragments live in resources/js/i18n/locales/_fragments/{name}.{locale}.json
 * and are merged in filename sort order (later files win on key conflicts).
 */

import { readdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const localesDir = path.join(root, 'resources/js/i18n/locales');
const fragmentsDir = path.join(localesDir, '_fragments');

// Default: every base locale file (en.json, ar.json, …) next to _fragments, so
// a plain `npm run i18n:merge` (also run on deploy) never skips a language.
const LOCALES =
    process.argv.slice(2).length > 0
        ? process.argv.slice(2)
        : (await readdir(localesDir)).filter((file) => /^[a-z]{2}\.json$/.test(file)).map((file) => file.slice(0, -'.json'.length));

function deepMerge(target, source) {
    for (const key of Object.keys(source)) {
        if (
            source[key] !== null &&
            typeof source[key] === 'object' &&
            !Array.isArray(source[key]) &&
            target[key] !== null &&
            typeof target[key] === 'object' &&
            !Array.isArray(target[key])
        ) {
            deepMerge(target[key], source[key]);
        } else {
            target[key] = source[key];
        }
    }

    return target;
}

async function mergeLocale(locale) {
    const basePath = path.join(localesDir, `${locale}.json`);
    const base = JSON.parse(await readFile(basePath, 'utf8'));

    const fragmentFiles = (await readdir(fragmentsDir)).filter((f) => f.endsWith(`.${locale}.json`)).sort();

    let merged = structuredClone(base);

    for (const file of fragmentFiles) {
        const fragment = JSON.parse(await readFile(path.join(fragmentsDir, file), 'utf8'));
        merged = deepMerge(merged, fragment);
    }

    await writeFile(basePath, `${JSON.stringify(merged, null, 4)}\n`, 'utf8');

    console.log(`Merged ${fragmentFiles.length} fragment(s) into ${locale}.json`);
}

for (const locale of LOCALES) {
    await mergeLocale(locale);
}
