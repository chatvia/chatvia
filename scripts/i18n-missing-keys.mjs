#!/usr/bin/env node
import { baseCompile } from '@intlify/message-compiler';
import { readFile, readdir, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const jsDir = path.join(root, 'resources/js');
const localesDir = path.join(root, 'resources/js/shared/i18n/locales');

function flattenKeys(obj, prefix = '') {
    const keys = [];
    for (const [k, v] of Object.entries(obj)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
            keys.push(...flattenKeys(v, key));
        } else {
            keys.push(key);
        }
    }
    return keys;
}

function hasKey(obj, keyPath) {
    const parts = keyPath.split('.');
    let cur = obj;
    for (const p of parts) {
        if (cur === null || typeof cur !== 'object' || !(p in cur)) return false;
        cur = cur[p];
    }
    return typeof cur === 'string';
}

function collectCompileErrors(obj, prefix = '') {
    const errors = [];
    for (const [k, v] of Object.entries(obj)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
            errors.push(...collectCompileErrors(v, key));
        } else if (typeof v === 'string') {
            const { detectError } = baseCompile(v, { onError: () => {} });
            if (detectError) {
                errors.push({ key, value: v, message: detectError.message });
            }
        }
    }
    return errors;
}

/** vue-i18n treats `@` as linked-message syntax — a literal `@` must be written `{'@'}`. */
function collectUnescapedAt(obj, prefix = '') {
    const warnings = [];
    for (const [k, v] of Object.entries(obj)) {
        const key = prefix ? `${prefix}.${k}` : k;
        if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
            warnings.push(...collectUnescapedAt(v, key));
        } else if (typeof v === 'string' && /(?<!@)@(?!@|\{)/.test(v) && !v.includes("{'@'}")) {
            warnings.push({ key, value: v });
        }
    }
    return warnings;
}

async function walk(dir, files = []) {
    for (const entry of await readdir(dir)) {
        const full = path.join(dir, entry);
        if (entry === 'i18n' || entry === 'node_modules') continue;
        const s = await stat(full);
        if (s.isDirectory()) await walk(full, files);
        else if (/\.(vue|ts)$/.test(entry)) files.push(full);
    }
    return files;
}

const locale = JSON.parse(await readFile(path.join(localesDir, 'en.json'), 'utf8'));
const localeKeys = new Set(flattenKeys(locale));

const used = new Set();
// `t('key')` calls, and `<i18n-t keypath="key">` components, which render the
// raw key when it is missing just the same.
const keyRes = [/\bt\(\s*['"]([a-zA-Z0-9_.]+)['"]/g, /\bkeypath="([a-zA-Z0-9_.]+)"/g];

for (const file of await walk(jsDir)) {
    const content = await readFile(file, 'utf8');
    for (const keyRe of keyRes) {
        let m;
        while ((m = keyRe.exec(content)) !== null) {
            used.add(m[1]);
        }
    }
}

const missing = [...used].filter((k) => !hasKey(locale, k) && !k.includes('${') && !k.endsWith('.')).sort();

console.log(`Used: ${used.size}, In locale: ${localeKeys.size}, Missing: ${missing.length}`);
console.log('\nBy namespace:');
const byNs = {};
for (const k of missing) {
    const ns = k.split('.').slice(0, 2).join('.');
    byNs[ns] = (byNs[ns] || 0) + 1;
}
for (const [ns, n] of Object.entries(byNs).sort((a, b) => b[1] - a[1])) {
    console.log(`  ${ns}: ${n}`);
}
console.log('\nAll missing keys:');
for (const k of missing) console.log(k);

let hasCompileErrors = false;
const localeCodes = (await readdir(localesDir)).filter((file) => /^[a-z]{2}\.json$/.test(file)).map((file) => file.slice(0, -'.json'.length));

for (const localeCode of localeCodes) {
    const data = JSON.parse(await readFile(path.join(localesDir, `${localeCode}.json`), 'utf8'));
    const compileErrors = collectCompileErrors(data);
    const atWarnings = collectUnescapedAt(data);

    if (compileErrors.length || atWarnings.length) {
        console.log(`\n${localeCode}.json message issues:`);
    }
    for (const e of compileErrors) {
        hasCompileErrors = true;
        console.log(`  COMPILE ERROR ${e.key}: ${e.message}`);
        console.log(`    ${e.value}`);
    }
    for (const w of atWarnings) {
        hasCompileErrors = true;
        console.log(`  UNESCAPED @ ${w.key} — write a literal @ as {'@'} (e.g. email{'@'}example.com)`);
        console.log(`    ${w.value}`);
    }
}

// A missing key renders as the raw key in the UI, so it fails the check too.
if (hasCompileErrors || missing.length > 0) {
    process.exitCode = 1;
}
