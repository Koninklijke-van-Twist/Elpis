/**
 * Zoek-leegmelding telt zichtbare projecten én projectloze orders.
 * Run: node --test tests/search_empty_test.mjs
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const php = readFileSync(join(root, 'web/index.php'), 'utf8');
const start = php.indexOf('    function normalizeSearchQuery');
const end = php.indexOf('    function copyProjectLink', start);
assert.ok(start > 0 && end > start, 'filterblok gevonden');
const script = php.slice(start, end);

function item(attrs) {
    const classes = new Set();
    return {
        attrs: { ...attrs },
        classList: {
            add(c) { classes.add(c); },
            remove(c) { classes.delete(c); },
            toggle(c, on) { if (on) classes.add(c); else classes.delete(c); },
            contains(c) { return classes.has(c); },
        },
        getAttribute(key) { return this.attrs[key] ?? null; },
    };
}

function harness(projects, orders, query, problems) {
    const nodes = [
        ...projects.map((text) => item({ 'data-elpis-project-item': '1', 'data-search-text': text, 'data-project-no': text, 'data-has-problem': text.includes('probleem') ? '1' : '0' })),
        ...orders.map((text) => item({ 'data-elpis-loose-order': '1', 'data-search-text': text, 'data-has-problem': text.includes('probleem') ? '1' : '0' })),
    ];
    const searchEmpty = { classList: { on: false, toggle(name, value) { if (name === 'is-visible') this.on = value; } } };
    const searchInput = { value: query };
    const projectsProblemsOnly = { checked: problems };
    const document = {
        querySelectorAll(sel) {
            if (sel === '[data-elpis-project-item]') return nodes.filter((n) => n.attrs['data-elpis-project-item']);
            if (sel === '[data-elpis-loose-order]') return nodes.filter((n) => n.attrs['data-elpis-loose-order']);
            return [];
        },
    };
    const apply = new Function('searchInput', 'projectsProblemsOnly', 'searchEmpty', 'document', 'window', `${script}\nreturn applyProjectFilters;`)(
        searchInput,
        projectsProblemsOnly,
        searchEmpty,
        document,
        { location: { href: 'https://elpis.test/index.php' } }
    );
    apply();
    return searchEmpty.classList.on;
}

test('alleen projectloze treffers verbergt de leegmelding', () => {
    assert.equal(harness(['prj-ander'], ['po12600350 probleem'], 'po126'), false);
});

test('geen project en geen losse order toont de leegmelding', () => {
    assert.equal(harness(['prj-ander'], ['po-ander'], 'ontbreekt'), true);
});

test('probleemfilter telt een verborgen project niet als treffer', () => {
    assert.equal(harness(['prj-groen'], ['po-groen'], 'prj', true), true);
    assert.equal(harness(['prj-probleem'], [], 'prj', true), false);
});
