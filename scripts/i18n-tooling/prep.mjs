// Compute per-locale translation jobs against the canonical en.json and emit
// agent input batches + verbatim maps + a manifest into the work dir.
import { readFile, writeFile, readdir, unlink } from 'node:fs/promises';
import path from 'node:path';
import { LANGS, localesDir, workDir, flatten, isVerbatim } from './langs.mjs';

const BATCH_SIZE = 150;

const enRaw = JSON.parse(await readFile(path.join(localesDir, 'en.json'), 'utf8'));
const en = flatten(enRaw);
const enKeys = Object.keys(en); // canonical order

// Guard: vue-i18n messages are strings; flag any non-string leaves so assembly
// logic (which assumes strings) never silently mangles them.
const nonString = enKeys.filter((k) => typeof en[k] !== 'string');
if (nonString.length) {
    console.log(`WARNING: ${nonString.length} non-string en values (handled as verbatim):`, nonString.slice(0, 10));
}

// Clean previous run.
for (const sub of ['in', 'out', 'verbatim', 'glossary']) {
    const dir = path.join(workDir, sub);
    for (const f of await readdir(dir).catch(() => [])) await unlink(path.join(dir, f)).catch(() => {});
}

const manifest = [];
const summary = [];
const droppedExtras = {};

for (const lang of LANGS) {
    if (lang.code === 'en') continue;
    const locRaw = JSON.parse(await readFile(path.join(localesDir, `${lang.code}.json`), 'utf8'));
    const loc = flatten(locRaw);
    const locKeySet = new Set(Object.keys(loc));
    const enKeySet = new Set(enKeys);

    droppedExtras[lang.code] = Object.keys(loc).filter((k) => !enKeySet.has(k));

    const verbatim = {}; // key -> en value (copied as-is)
    const toTranslate = []; // { key, source }

    for (const key of enKeys) {
        const enVal = en[key];
        const missing = !locKeySet.has(key);
        const leaked = !missing && loc[key] === enVal && /\p{L}/u.test(typeof enVal === 'string' ? enVal : '');

        if (!missing && !leaked) continue; // preserve existing real translation

        if (isVerbatim(enVal)) {
            verbatim[key] = enVal; // placeholder-only / no words — copy English
        } else {
            toTranslate.push({ key, source: enVal });
        }
    }

    await writeFile(path.join(workDir, 'verbatim', `${lang.code}.json`), JSON.stringify(verbatim, null, 2));

    // Batch the agent work.
    let batchIndex = 0;
    for (let i = 0; i < toTranslate.length; i += BATCH_SIZE) {
        const slice = toTranslate.slice(i, i + BATCH_SIZE);
        const inObj = {};
        for (const { key, source } of slice) inObj[key] = source;
        const inPath = path.join(workDir, 'in', `${lang.code}__${batchIndex}.json`);
        const outPath = path.join(workDir, 'out', `${lang.code}__${batchIndex}.json`);
        await writeFile(inPath, JSON.stringify(inObj, null, 2));
        manifest.push({
            locale: lang.code,
            name: lang.name,
            rtl: lang.rtl,
            batchIndex,
            inPath,
            outPath,
            count: slice.length,
        });
        batchIndex++;
    }

    summary.push({
        code: lang.code,
        name: lang.name,
        missingOrLeaked: toTranslate.length + Object.keys(verbatim).length,
        toTranslate: toTranslate.length,
        verbatim: Object.keys(verbatim).length,
        batches: batchIndex,
        extras: droppedExtras[lang.code].length,
    });
}

await writeFile(path.join(workDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
await writeFile(path.join(workDir, 'langs.json'), JSON.stringify(LANGS, null, 2));
await writeFile(path.join(workDir, 'dropped-extras.json'), JSON.stringify(droppedExtras, null, 2));

console.log(`Canonical en keys: ${enKeys.length}\n`);
console.log('locale | name              | translate | verbatim | batches | drop-extra');
console.log('-------|-------------------|-----------|----------|---------|-----------');
let totalBatches = 0,
    totalStrings = 0;
for (const s of summary) {
    totalBatches += s.batches;
    totalStrings += s.toTranslate;
    console.log(
        `${s.code.padEnd(6)} | ${s.name.padEnd(17)} | ${String(s.toTranslate).padStart(9)} | ${String(s.verbatim).padStart(8)} | ${String(s.batches).padStart(7)} | ${String(s.extras).padStart(10)}`,
    );
}
console.log(`\nTOTAL batches: ${totalBatches}  |  TOTAL strings to translate: ${totalStrings}`);
console.log('Extras to drop:', JSON.stringify(droppedExtras).slice(0, 300));
