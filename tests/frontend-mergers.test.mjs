import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import vm from 'node:vm';
import test from 'node:test';

const ts = createRequire(resolve(process.env.TEST_TYPESCRIPT_ROOT || '.', 'package.json'))('typescript');
function run(helper, files, mode = 'ssr') {
    const source = readFileSync(new URL(`../src/Support/${helper}.mjs`, import.meta.url), 'utf8')
        .replace(/^import .*;\n/gm, '');
    const context = {
        createRequire: () => () => ts,
        resolve: (...parts) => parts.join('/'),
        readFileSync: (path) => {
            assert.ok(path in files, `Missing fixture ${path}`);
            return files[path];
        },
        writeFileSync: (path, value) => { files[path] = value; },
        existsSync: (path) => path in files,
        console: { log() {} },
        process: { argv: ['node', helper, 'root', mode], exit: () => { throw new Error('EXIT'); } },
    };
    try { vm.runInNewContext(source, context); } catch (error) {
        if (error.message !== 'EXIT') throw error;
    }
    return files;
}

function vite(plugin = 'inertia()', app = 'createInertiaApp({});') {
    return {
        'root/resources/js/app.ts': app,
        'root/vite.config.ts': `import inertia from '@inertiajs/vite'; import laravel from 'laravel-vite-plugin'; export default {plugins:[laravel({input: ['resources/js/app.ts']}), ${plugin}]};`,
    };
}

test('Vite comments remain syntactically valid and merging is idempotent', () => {
    const files = run('configure-frontend', vite('inertia /* note */ ()'));
    const once = files['root/vite.config.ts'];
    assert.match(once, /ssr:/);
    run('configure-frontend', files);
    assert.equal(files['root/vite.config.ts'], once);
});

test('custom SSR entry and quoted options survive in both plugins', () => {
    const files = vite("inertia({'ssr': {'entry': 'resources/js/custom.ts', host: 'custom', port: 1234}})");
    files['root/resources/js/custom.ts'] = '/* custom SSR */';
    run('configure-frontend', files);
    assert.equal(files['root/vite.config.ts'].match(/resources\/js\/custom.ts/g).length, 2);
    assert.match(files['root/vite.config.ts'], /host: 'custom', port: 1234/);
});

test('manual arrow setup is rejected without a matching server implementation', () => {
    const files = vite('inertia()', 'createInertiaApp({ setup: () => createApp({}) });');
    const before = files['root/vite.config.ts'];
    assert.throws(() => run('configure-frontend', files), /Custom Inertia setup/);
    assert.equal(files['root/vite.config.ts'], before);
});

test('ESLint comments remain valid and unused ignores are not selected', () => {
    const files = {
        'root/eslint.config.js': 'const unused = { ignores: [] }; export default defineConfigWithVueTs /* note */ ({ rules: {} });',
    };
    run('merge-eslint', files);
    const once = files['root/eslint.config.js'];
    assert.match(once, /const unused = \{ ignores: \[\] \}/);
    assert.match(once, /defineConfigWithVueTs \/\* note \*\/ \(\{"ignores"/);
    run('merge-eslint', files);
    assert.equal(files['root/eslint.config.js'], once);
});

test('dynamic plugin spreads fail without modifying Vite configuration', () => {
    for (const plugin of ['inertia({ ...options })', 'inertia({ ssr: { ...options } })']) {
        const files = vite(plugin);
        const original = files['root/vite.config.ts'];
        assert.throws(() => run('configure-frontend', files), /spread/);
        assert.equal(files['root/vite.config.ts'], original);
    }
    const files = vite();
    files['root/vite.config.ts'] = files['root/vite.config.ts'].replace('input:', '...options, input:');
    const original = files['root/vite.config.ts'];
    assert.throws(() => run('configure-frontend', files), /spread/);
    assert.equal(files['root/vite.config.ts'], original);
});
