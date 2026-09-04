import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = process.argv[2];
const ts = createRequire(resolve(root, 'package.json'))('typescript');
const path = resolve(root, 'eslint.config.js');
const source = readFileSync(path, 'utf8');
const ast = ts.createSourceFile(path, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.JS);
const patterns = ['**/vendor/**', '**/node_modules/**', 'public/**', 'bootstrap/ssr/**', 'resources/js/actions/**', 'resources/js/routes/**', 'resources/js/wayfinder/**'];
let ignoreArray;
const assignment = ast.statements.find(ts.isExportAssignment);
const expression = assignment?.expression;
const elements = expression && ts.isArrayLiteralExpression(expression)
    ? expression.elements
    : expression && ts.isCallExpression(expression) && expression.expression.getText(ast) === 'defineConfigWithVueTs'
        ? expression.arguments : null;
if (!elements) throw new Error('Cannot safely merge ESLint global ignores: use an exported flat array or defineConfigWithVueTs. Existing configuration was preserved.');
for (const node of elements) {
    if (ts.isObjectLiteralExpression(node) && node.properties.every((p) => ['ignores', 'name'].includes(p.name?.getText(ast).replace(/['"]/g, '')))) {
        const ignores = node.properties.find((p) => p.name?.getText(ast).replace(/['"]/g, '') === 'ignores');
        if (ignores && ts.isPropertyAssignment(ignores) && ts.isArrayLiteralExpression(ignores.initializer)) ignoreArray ??= ignores.initializer;
    }
}
let output;
if (ignoreArray) {
    const existing = ignoreArray.elements.filter(ts.isStringLiteral).map((n) => n.text);
    const missing = patterns.filter((p) => !existing.includes(p));
    if (missing.length === 0) process.exit(0);
    const position = ignoreArray.getStart(ast) + 1;
    output = source.slice(0, position) + missing.map((p) => JSON.stringify(p) + ',').join('\n') + source.slice(position);
} else {
    let position;
    if (expression && ts.isCallExpression(expression) && expression.expression.getText(ast) === 'defineConfigWithVueTs') {
        position = expression.arguments.length ? expression.arguments[0].getStart(ast) : expression.end - 1;
    } else if (expression && ts.isArrayLiteralExpression(expression)) {
        position = expression.getStart(ast) + 1;
    } else {
        throw new Error('Cannot safely merge ESLint global ignores: use a flat array or defineConfigWithVueTs. Existing configuration was preserved.');
    }
    output = source.slice(0, position) + JSON.stringify({ ignores: patterns }) + (elements.length ? ',' : '') + source.slice(position);
}
const result = ts.createSourceFile(path, output, ts.ScriptTarget.Latest, true, ts.ScriptKind.JS);
if (result.parseDiagnostics.length) throw new Error('ESLint merge would produce invalid syntax; existing configuration was preserved.');
writeFileSync(path, output);
