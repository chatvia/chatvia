#!/usr/bin/env node
/**
 * Build Shakomako, the embeddable chat widget, into public/shakomako.
 *
 *   npm run shakomako:build                                # the newest release
 *   SHAKOMAKO_VERSION=v3.0.0 npm run shakomako:build       # one chosen release
 *   SHAKOMAKO_PATH=../shakomako npm run shakomako:build    # your own checkout of the widget
 *   npm run -s shakomako:version                           # only print the release
 *
 * The widget lives in its own repository (SHAKOMAKO_REPO in the Dockerfile).
 * The newest release is its highest vX.Y.Z tag; pre-release tags and untagged
 * commits are never built. The image workflow uses --print-version to pass the
 * same release to the Docker build. A release is cloned once into
 * node_modules/.cache.
 */

import { execFileSync } from 'node:child_process';
import { cpSync, existsSync, readFileSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const target = path.join(root, 'public/shakomako');

function dockerfileArg(name) {
    const match = readFileSync(path.join(root, 'Dockerfile'), 'utf8').match(new RegExp(`^ARG ${name}=(\\S+)$`, 'm'));

    if (!match) {
        throw new Error(`The Dockerfile has no "ARG ${name}=..." line.`);
    }

    return match[1];
}

function run(command, args, cwd) {
    // npm is npm.cmd on Windows, which only runs through a shell.
    execFileSync(command, args, { cwd, stdio: 'inherit', shell: process.platform === 'win32' });
}

// The highest vX.Y.Z tag of the repository, compared as numbers (v3.10.0 is
// newer than v3.9.0).
function newestRelease(repo) {
    const output = execFileSync('git', ['ls-remote', '--tags', '--refs', repo], { encoding: 'utf8' });
    const releases = output
        .split('\n')
        .map((line) => line.split('refs/tags/')[1])
        .filter((tag) => /^v\d+\.\d+\.\d+$/.test(tag ?? ''));

    if (releases.length === 0) {
        throw new Error(`${repo} has no release tags (vX.Y.Z).`);
    }

    const numbers = (tag) => tag.slice(1).split('.').map(Number);

    return releases
        .sort((a, b) => {
            const [x, y] = [numbers(a), numbers(b)];

            return x[0] - y[0] || x[1] - y[1] || x[2] - y[2];
        })
        .at(-1);
}

const repo = dockerfileArg('SHAKOMAKO_REPO');

function release() {
    return process.env.SHAKOMAKO_VERSION || newestRelease(repo);
}

if (process.argv.includes('--print-version')) {
    console.log(release());
    process.exit(0);
}

let source;

if (process.env.SHAKOMAKO_PATH) {
    source = path.resolve(root, process.env.SHAKOMAKO_PATH);

    // The widget build reads .env, and its settings end up in the bundle.
    if (existsSync(path.join(source, '.env'))) {
        console.warn(`Warning: this build uses ${path.join(source, '.env')}. A Chatvia server needs a build without settings.`);
    }
} else {
    const version = release();
    source = path.join(root, 'node_modules/.cache/shakomako', version);
    console.log(`Building Shakomako ${version}.`);

    if (!existsSync(path.join(source, 'package.json'))) {
        rmSync(source, { recursive: true, force: true });
        // Not `git clone --depth 1 --branch`: it warns that an annotated tag
        // "is not a commit".
        run('git', ['init', '--quiet', source], root);
        run('git', ['fetch', '--quiet', '--depth', '1', repo, version], source);
        run('git', ['-c', 'advice.detachedHead=false', 'checkout', '--quiet', 'FETCH_HEAD'], source);
    }
}

if (!existsSync(path.join(source, 'node_modules'))) {
    // --ignore-scripts also skips the widget's husky git-hook setup.
    run('npm', ['ci', '--ignore-scripts', '--no-audit', '--no-fund'], source);
}

run('npm', ['run', 'build'], source);

rmSync(target, { recursive: true, force: true });
cpSync(path.join(source, 'dist'), target, { recursive: true });

console.log(`Built Shakomako into ${path.relative(root, target)}.`);
