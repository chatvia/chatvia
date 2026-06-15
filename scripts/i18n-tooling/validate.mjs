// Validate every rebuilt locale against en.json: key parity, vue-i18n compile
// errors, unescaped "@", and interpolation/pluralization parity.
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { baseCompile } from '@intlify/message-compiler';
import { LANGS, localesDir, workDir, flatten } from './langs.mjs';

const en = flatten(JSON.parse(await readFile(path.join(localesDir, 'en.json'), 'utf8')));
const enKeys = Object.keys(en);
const enKeySet = new Set(enKeys);

const placeholders = (s) => (String(s).match(/\{[^}]*\}/g) || []).slice().sort();
const pipeCount = (s) => String(s).split(' | ').length;
const atRe = /(?<!@)@(?!@|\{)/;

let totalProblems = 0;
const repair = {}; // code -> { key: enSource } for keys needing re-translation

console.log('locale | missing | extra | compileErr | unescaped@ | ph-mismatch | pipe-mismatch');
console.log('-------|---------|-------|------------|------------|-------------|-------------');

for (const lang of LANGS) {
    if (lang.code === 'en') continue;
    const code = lang.code;
    const data = flatten(JSON.parse(await readFile(path.join(localesDir, `${code}.json`), 'utf8')));
    const keySet = new Set(Object.keys(data));

    const missing = enKeys.filter((k) => !keySet.has(k));
    const extra = Object.keys(data).filter((k) => !enKeySet.has(k));
    let compileErr = 0, atWarn = 0, phMis = 0, pipeMis = 0;
    const need = {};

    for (const k of enKeys) {
        if (!keySet.has(k)) { need[k] = en[k]; continue; }
        const v = data[k];
        if (typeof v !== 'string') continue;
        const { detectError } = baseCompile(v, { onError: () => {} });
        if (detectError) { compileErr++; need[k] = en[k]; }
        if (atRe.test(v) && !v.includes("{'@'}")) { atWarn++; need[k] = en[k]; }
        // parity only meaningful when en value is a string
        if (typeof en[k] === 'string') {
            if (JSON.stringify(placeholders(v)) !== JSON.stringify(placeholders(en[k]))) { phMis++; need[k] = en[k]; }
            if (pipeCount(v) !== pipeCount(en[k])) { pipeMis++; need[k] = en[k]; }
        }
    }

    const probs = missing.length + extra.length + compileErr + atWarn + phMis + pipeMis;
    totalProblems += probs;
    if (Object.keys(need).length) repair[code] = need;

    console.log(
        `${code.padEnd(6)} | ${String(missing.length).padStart(7)} | ${String(extra.length).padStart(5)} | ${String(compileErr).padStart(10)} | ${String(atWarn).padStart(10)} | ${String(phMis).padStart(11)} | ${String(pipeMis).padStart(13)}`,
    );
}

await writeFile(path.join(workDir, 'repair.json'), JSON.stringify(repair, null, 2));
console.log(`\nTotal problems: ${totalProblems}`);
const repairCount = Object.values(repair).reduce((a, b) => a + Object.keys(b).length, 0);
if (repairCount) console.log(`Keys needing repair: ${repairCount} (written to repair.json)`);
else console.log('All locales clean ✔');
