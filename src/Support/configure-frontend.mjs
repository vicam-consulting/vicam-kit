import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

const [root, mode] = process.argv.slice(2);
const require = createRequire(resolve(root, 'package.json'));
const ts = require('typescript');
const path = resolve(root, 'vite.config.ts');
const source = readFileSync(path, 'utf8');
const ast = ts.createSourceFile(path, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
const edits = [];
const enabled = mode === 'ssr';
const app = readFileSync(resolve(root, 'resources/js/app.ts'), 'utf8');
let entry = 'resources/js/app.ts';
const appAst = ts.createSourceFile('app.ts', app, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
let inertiaAppName = 'createInertiaApp';
for (const statement of appAst.statements) {
    if (ts.isImportDeclaration(statement) && statement.moduleSpecifier.text === '@inertiajs/vue3') {
        for (const specifier of statement.importClause?.namedBindings?.elements ?? []) {
            if ((specifier.propertyName ?? specifier.name).text === 'createInertiaApp') inertiaAppName = specifier.name.text;
        }
    }
}
let manualSetup = false;
let appCalls = 0;
function inspectApp(node) {
    if (ts.isCallExpression(node) && ts.isIdentifier(node.expression) && node.expression.text === inertiaAppName) {
        appCalls++;
        const options = node.arguments[0];
        if (!options || !ts.isObjectLiteralExpression(options) || options.properties.some(ts.isSpreadAssignment)) {
            throw new Error('SSR requires literal createInertiaApp options without spreads so setup can be verified.');
        }
        manualSetup = options.properties.some((property) => property.name?.getText(appAst).replace(/['"]/g, '') === 'setup');
        let parent = node.parent;
        if (ts.isAwaitExpression(parent) || ts.isVoidExpression(parent)) parent = parent.parent;
        if (!ts.isExpressionStatement(parent) || parent.parent !== appAst) {
            throw new Error('SSR requires a top-level createInertiaApp(...) expression, optionally awaited or voided.');
        }
    }
    ts.forEachChild(node, inspectApp);
}
if (enabled) {
    inspectApp(appAst);
    if (appCalls !== 1) throw new Error(`SSR requires exactly one createInertiaApp call; found ${appCalls}.`);
}

// Automatic Inertia 3 entrypoints retain all existing layouts and Vue plugins.
// A custom manual setup needs its own server implementation; never invent one.
if (enabled && manualSetup) {
    if (!existsSync(resolve(root, 'resources/js/ssr.ts'))) {
        throw new Error('Custom Inertia setup detected. Supply a matching resources/js/ssr.ts or migrate to the Inertia 3 automatic setup before enabling SSR.');
    }
    entry = 'resources/js/ssr.ts';
    if (/\bcreateApp\b/.test(app)) {
        throw new Error('Custom SSR browser entry must use createSSRApp for hydration. Update resources/js/app.ts before enabling SSR.');
    }
}

let inertiaName;
let laravelName;
for (const statement of ast.statements) {
    if (ts.isImportDeclaration(statement) && statement.moduleSpecifier.text === '@inertiajs/vite') {
        inertiaName = statement.importClause?.name?.text;
    }
    if (ts.isImportDeclaration(statement) && statement.moduleSpecifier.text === 'laravel-vite-plugin') {
        laravelName = statement.importClause?.name?.text;
    }
}
if (!inertiaName) throw new Error('Supported Vite baseline requires a default import from @inertiajs/vite. Add the Inertia 3 plugin before installation.');
if (enabled && !laravelName) throw new Error('SSR requires the Laravel Vite plugin.');

// Resolve an existing explicit server entry before updating either plugin.
function discoverEntry(node) {
    if (enabled && ts.isCallExpression(node) && ts.isIdentifier(node.expression) && node.expression.text === inertiaName) {
        const options = node.arguments[0];
        if (options && ts.isObjectLiteralExpression(options)) {
            const ssr = options.properties.find((p) => p.name?.getText(ast).replace(/['"]/g, '') === 'ssr');
            if (ssr && ts.isPropertyAssignment(ssr) && ts.isObjectLiteralExpression(ssr.initializer)) {
                const existing = ssr.initializer.properties.find((p) => p.name?.getText(ast).replace(/['"]/g, '') === 'entry');
                if (existing) {
                    if (!ts.isPropertyAssignment(existing) || !ts.isStringLiteral(existing.initializer)) {
                        throw new Error('SSR entry must be a literal path for safe reconciliation.');
                    }
                    entry = existing.initializer.text;
                    if (!existsSync(resolve(root, entry))) throw new Error(`SSR entry does not exist: ${entry}`);
                }
            }
        }
    }
    ts.forEachChild(node, discoverEntry);
}
discoverEntry(ast);

function replaceProperty(object, name, value) {
    if (object.properties.some(ts.isSpreadAssignment)) throw new Error('Cannot safely reconcile spread plugin options. Use literal options.');
    const property = object.properties.find((node) => node.name?.getText(ast).replace(/['"]/g, '') === name);
    if (property) {
        if (!ts.isPropertyAssignment(property)) throw new Error(`Cannot safely reconcile shorthand/spread property ${name}.`);
        edits.push([property.initializer.getStart(ast), property.initializer.end, value]);
    } else {
        const last = object.properties.at(-1);
        const position = last ? last.end : object.getStart(ast) + 1;
        edits.push([position, position, `${last ? ',' : ''}\nssr: ${value}${object.properties.hasTrailingComma ? '' : ','}`]);
    }
}

let found = 0;
let laravelFound = 0;
function visit(node) {
    if (enabled && ts.isCallExpression(node) && ts.isIdentifier(node.expression) && node.expression.text === laravelName) {
        laravelFound++;
        if (node.arguments.length !== 1 || !ts.isObjectLiteralExpression(node.arguments[0])) {
            throw new Error('SSR reconciliation requires literal Laravel Vite plugin options.');
        }
        replaceProperty(node.arguments[0], 'ssr', `'${entry}'`);
    }
    if (ts.isCallExpression(node) && ts.isIdentifier(node.expression) && node.expression.text === inertiaName) {
        found++;
        const value = enabled ? `{ entry: '${entry}', host: '127.0.0.1' }` : 'false';
        if (node.arguments.length === 0) {
            edits.push([node.end - 1, node.end - 1, `{ ssr: ${value} }`]);
        } else if (node.arguments.length === 1 && ts.isObjectLiteralExpression(node.arguments[0])) {
            const options = node.arguments[0];
            if (options.properties.some(ts.isSpreadAssignment)) throw new Error('Cannot safely reconcile spread Inertia options.');
            const ssr = options.properties.find((p) => p.name?.getText(ast).replace(/['"]/g, '') === 'ssr');
            if (enabled && ssr && ts.isPropertyAssignment(ssr) && ts.isObjectLiteralExpression(ssr.initializer)) {
                if (ssr.initializer.properties.some(ts.isSpreadAssignment)) throw new Error('Cannot safely reconcile spread SSR options.');
                const existingEntry = ssr.initializer.properties.find((p) => p.name?.getText(ast).replace(/['"]/g, '') === 'entry');
                if (!existingEntry) {
                    const text = ssr.initializer.getText(ast);
                    edits.push([ssr.initializer.getStart(ast), ssr.initializer.end, text.replace('{', `{ entry: '${entry}', `)]);
                }
                // Preserve explicitly configured host, port, clustering, and entry.
            } else {
                replaceProperty(options, 'ssr', value);
            }
        } else {
            throw new Error('Cannot safely reconcile dynamic Inertia plugin options. Use a literal options object.');
        }
    }
    ts.forEachChild(node, visit);
}
visit(ast);
if (found !== 1) throw new Error(`Expected exactly one Inertia plugin invocation; found ${found}.`);
if (enabled && laravelFound !== 1) throw new Error(`Expected exactly one Laravel plugin invocation; found ${laravelFound}.`);
let output = source;
for (const [start, end, replacement] of edits.sort((a, b) => b[0] - a[0])) {
    output = output.slice(0, start) + replacement + output.slice(end);
}
const result = ts.createSourceFile(path, output, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
if (result.parseDiagnostics.length) throw new Error('Vite merge would produce invalid syntax; existing configuration was preserved.');
if (output !== source) writeFileSync(path, output);
console.log(`Configured Inertia SSR ${enabled ? `using ${entry}` : 'disabled'}.`);
