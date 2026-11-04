/**
 * PM-combobox: pijltje maakt altijd leeg, Escape zet de laatst gekozen waarde terug (ook na Enter).
 * Draait het echte combobox-script uit web/index.php tegen een mini-DOM.
 * Run: node --test tests/elpis_combo_test.mjs
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const php = readFileSync(join(root, 'web/index.php'), 'utf8');
const start = php.indexOf('    // Gedeelde combobox (PM en besteller)');
const end = php.indexOf('    if (managerSelect && filterForm && !managerSelect.disabled) {', start);
assert.ok(start > 0 && end > start, 'combobox-blok gevonden');
const script = php.slice(start, end);

function el(tag) {
    const listeners = {};
    const e = {
        tagName: tag, value: '', title: '', hidden: false, attrs: {}, children: [], textContent: '',
        classList: { set: new Set(), add(c) { this.set.add(c); }, remove(c) { this.set.delete(c); }, toggle(c, on) { on ? this.set.add(c) : this.set.delete(c); }, contains(c) { return this.set.has(c); } },
        addEventListener(t, f) { (listeners[t] ||= []).push(f); },
        dispatchEvent(ev) { (listeners[ev.type] || []).forEach((f) => f(ev)); return true; },
        setAttribute(k, v) { e.attrs[k] = String(v); },
        getAttribute(k) { return k in e.attrs ? e.attrs[k] : null; },
        appendChild(c) { e.children.push(c); },
        querySelectorAll() { return e.children; },
        scrollIntoView() {},
        focus() { if (doc.activeElement !== e) { const prev = doc.activeElement; doc.activeElement = e; prev && prev.dispatchEvent(ev('blur')); } },
        blur() { if (doc.activeElement === e) { doc.activeElement = doc.body; e.dispatchEvent(ev('blur')); } },
    };
    Object.defineProperty(e, 'innerHTML', { set() { e.children = []; } });
    return e;
}
function ev(type, extra = {}) {
    return { type, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, stopPropagation() {}, ...extra };
}
const options = [['Alle', '__ALL__'], ['ADGROOT', 'ADGROOT'], ['ACKERMANS', 'ACK']].map(([v, d]) => { const o = el('option'); o.value = v; o.attrs['data-value'] = d; return o; });
const doc = { activeElement: null, body: el('body'), createElement: el, querySelectorAll: () => options, getElementById: (id) => (id === 'elpis-manager-list' ? list : null) };
const input = el('input'); input.value = 'Alle';
const toggle = el('button');
const list = el('ul'); list.hidden = true;
const select = el('select'); select.value = '__ALL__'; select.disabled = false;
const form = { querySelector: (s) => (s.includes('purchaser') ? null : (s.includes('toggle') ? toggle : input)) };

// Simuleer een muisklik op het pijltje: mousedown (met focusverplaatsing tenzij preventDefault) en daarna click.
function clickArrow() {
    // Zoals in Chrome: preventDefault op pointerdown voorkomt NIET dat mousedown de focus verplaatst.
    toggle.dispatchEvent(ev('pointerdown', { pointerType: 'mouse' }));
    const md = ev('mousedown');
    toggle.dispatchEvent(md);
    if (!md.defaultPrevented) { toggle.focus(); }
    toggle.dispatchEvent(ev('click'));
}
function type(text) { for (const ch of text) { input.value += ch; input.dispatchEvent(ev('input')); } }
function key(k) { input.dispatchEvent(ev('keydown', { key: k })); }

new Function('document', 'window', 'filterForm', 'managerSelect', 'Event', script)(doc, { PointerEvent: function () {} }, form, select, function (t) { return ev(t); });
input.focus();

test('Enter-keuze, pijltje met focus, typen, Escape -> laatst gekozen waarde', () => {
    input.value = '';
    type('ADG');
    key('ArrowDown');
    key('Enter');
    assert.equal(input.value, 'ADGROOT');
    assert.equal(select.value, 'ADGROOT');
    assert.equal(doc.activeElement, input);
    clickArrow();
    assert.equal(input.value, '', 'pijltje maakt leeg ook met focus');
    assert.equal(doc.activeElement, input, 'focus blijft in de input');
    assert.equal(list.hidden, false);
    assert.equal(list.children.length, 3, 'alle opties');
    type('AC');
    key('Escape');
    assert.equal(input.value, 'ADGROOT');
});

test('pijltje maakt ook leeg als de lijst al open is', () => {
    clickArrow();
    type('X');
    clickArrow();
    assert.equal(input.value, '');
    assert.equal(list.hidden, false);
    assert.equal(list.children.length, 3);
    key('Escape');
    assert.equal(input.value, 'ADGROOT');
});

test('Escape met dichte lijst en blur zonder keuze herstellen', () => {
    type('ZZ');
    key('Escape');
    key('Escape');
    assert.equal(input.value, 'ADGROOT');
    clickArrow();
    input.blur();
    assert.equal(input.value, 'ADGROOT');
});
