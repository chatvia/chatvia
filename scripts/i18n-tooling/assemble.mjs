// Rebuild every locale JSON from the canonical en.json structure, resolving each
// leaf as: translated (agent output) ?? verbatim (placeholder-only) ?? existing
// (preserved real translation) ?? en (safety fallback). Guarantees identical key
// set + order across all locales. Writes 4-space JSON + trailing newline.
import { readFile, writeFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { LANGS, localesDir, workDir, flatten } from './langs.mjs';

const enRaw = JSON.parse(await readFile(path.join(localesDir, 'en.json'), 'utf8'));

async function loadOut(code) {
    const files = (await readdir(path.join(workDir, 'out'))).filter((f) => f.startsWith(`${code}__`) && f.endsWith('.json'));
    const merged = {};
    for (const f of files) {
        try {
            Object.assign(merged, JSON.parse(await readFile(path.join(workDir, 'out', f), 'utf8')));
        } catch (e) {
            console.log(`  ! bad JSON in out/${f}: ${e.message}`);
        }
    }
    return merged;
}

async function loadIn(code) {
    const files = (await readdir(path.join(workDir, 'in'))).filter((f) => f.startsWith(`${code}__`) && f.endsWith('.json'));
    const merged = {};
    for (const f of files) Object.assign(merged, JSON.parse(await readFile(path.join(workDir, 'in', f), 'utf8')));
    return merged;
}

const gaps = {}; // code -> [keys expected-translated but missing from output]

console.log('locale | from-agent | verbatim | preserved | en-fallback | GAPS');
console.log('-------|------------|----------|-----------|-------------|-----');

for (const lang of LANGS) {
    if (lang.code === 'en') continue;
    const code = lang.code;
    const existing = flatten(JSON.parse(await readFile(path.join(localesDir, `${code}.json`), 'utf8')));
    const translated = await loadOut(code);
    const expected = await loadIn(code);
    const verbatim = JSON.parse(await readFile(path.join(workDir, 'verbatim', `${code}.json`), 'utf8'));

    const missingFromOut = Object.keys(expected).filter((k) => !(k in translated));
    gaps[code] = missingFromOut;

    const stats = { translated: 0, verbatim: 0, preserved: 0, en: 0 };
    const rebuild = (node, prefix) => {
        if (node !== null && typeof node === 'object' && !Array.isArray(node)) {
            const out = {};
            for (const [k, v] of Object.entries(node)) out[k] = rebuild(v, prefix ? `${prefix}.${k}` : k);
            return out;
        }
        const key = prefix;
        if (key in translated) { stats.translated++; return translated[key]; }
        if (key in verbatim) { stats.verbatim++; return verbatim[key]; }
        if (key in existing) { stats.preserved++; return existing[key]; }
        stats.en++;
        return node;
    };

    const rebuilt = rebuild(enRaw, '');
    await writeFile(path.join(localesDir, `${code}.json`), `${JSON.stringify(rebuilt, null, 4)}\n`, 'utf8');

    console.log(
        `${code.padEnd(6)} | ${String(stats.translated).padStart(10)} | ${String(stats.verbatim).padStart(8)} | ${String(stats.preserved).padStart(9)} | ${String(stats.en).padStart(11)} | ${missingFromOut.length}`,
    );
}

await writeFile(path.join(workDir, 'gaps.json'), JSON.stringify(gaps, null, 2));
const totalGaps = Object.values(gaps).reduce((a, b) => a + b.length, 0);
console.log(`\nTotal gaps (expected-translated but missing from agent output): ${totalGaps}`);
if (totalGaps) console.log('Gaps written to gaps.json for a repair pass.');
