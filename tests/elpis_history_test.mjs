/**
 * History-staat voor project, filter en detail.
 * Run: node --test tests/elpis_history_test.mjs
 */
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const require = createRequire(import.meta.url);
const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const ElpisHistory = require(join(root, 'web/elpis_history.js'));
const indexPhp = readFileSync(join(root, 'web/index.php'), 'utf8');
const historyJs = readFileSync(join(root, 'web/elpis_history.js'), 'utf8');

function createMemorySession(href) {
    const stack = [{ href, state: null }];
    let index = 0;

    function absolute(next) {
        return new URL(next, stack[index].href).href;
    }

    return {
        getHref() {
            return stack[index].href;
        },
        history: {
            pushState(state, _title, url) {
                stack.splice(index + 1);
                stack.push({ href: absolute(url), state });
                index += 1;
            },
            replaceState(state, _title, url) {
                stack[index] = { href: absolute(url), state };
            },
            back() {
                if (index > 0) {
                    index -= 1;
                }
            },
            forward() {
                if (index < stack.length - 1) {
                    index += 1;
                }
            },
            get state() {
                return stack[index].state;
            },
        },
        back() {
            this.history.back();
        },
        forward() {
            this.history.forward();
        },
        index() {
            return index;
        },
        length() {
            return stack.length;
        },
    };
}

function createHarness(href) {
    const session = createMemorySession(href);
    const events = [];
    const api = ElpisHistory.createProjectHistory({
        history: session.history,
        getHref: () => session.getHref(),
        onListFilter(view) {
            events.push(['list', view.q]);
        },
        onOpenProject(view) {
            events.push(['open', view.project, view.q, view.line_q, view.line_status]);
        },
        onCloseProject(view) {
            events.push(['close', view.project, view.line_q, view.line_status]);
        },
        onLineFilter(view) {
            events.push(['line', view.line_q, view.line_status]);
        },
    });
    api.seed();
    return { session, events, api };
}

function paramsOf(href) {
    return new URL(href).searchParams;
}

test('uitgestelde lijstlaad vervangt history, filters en links duwen een entry', () => {
    assert.equal(ElpisHistory.navigationMode('deferred'), 'replace');
    assert.equal(ElpisHistory.navigationMode('filter'), 'assign');
    assert.equal(ElpisHistory.navigationMode('link'), 'assign');
    assert.equal(ElpisHistory.navigationMode(undefined), 'assign');
});

test('tijdelijke laadparameters gaan van de url af zonder view te wissen', () => {
    const params = new URLSearchParams('company=KVT&manager=Bob&project=P1&q=pomp&_loaded=1&reload_managers=1&line_q=moer&line_status=LATE');
    assert.equal(ElpisHistory.hasTransientParams(params), true);
    assert.equal(ElpisHistory.stripTransientParams(params), true);
    assert.equal(params.has('_loaded'), false);
    assert.equal(params.has('reload_managers'), false);
    assert.equal(params.get('company'), 'KVT');
    assert.equal(params.get('manager'), 'Bob');
    assert.equal(params.get('project'), 'P1');
    assert.equal(params.get('q'), 'pomp');
    assert.equal(params.get('line_q'), 'moer');
    assert.equal(params.get('line_status'), 'LATE');
    assert.equal(ElpisHistory.stripTransientParams(params), false);
});

test('elke betekenisvolle view-wijziging plant een push, een gelijke view niet', () => {
    const params = new URLSearchParams('company=KVT&q=pomp');
    assert.equal(ElpisHistory.planViewChange(params, { q: 'pomp', project: '', line_q: '', line_status: '' }), 'none');
    assert.equal(ElpisHistory.planViewChange(params, { q: 'klep', project: '', line_q: '', line_status: '' }), 'push');
    assert.equal(ElpisHistory.planViewChange(params, { q: 'pomp', project: 'P1', line_q: '', line_status: '' }), 'push');
    assert.equal(ElpisHistory.planViewChange(params, { q: 'pomp', project: '', line_q: 'moer', line_status: '' }), 'push');
    assert.equal(ElpisHistory.planViewChange(params, { q: 'pomp', project: '', line_q: '', line_status: 'LATE' }), 'push');
    assert.equal(params.get('q'), 'pomp');
});

test('zoekfilter is één history-entry en terug herstelt de vorige lijst', () => {
    const { session, events, api } = createHarness('http://localhost/elpis/index.php?company=KVT&manager=Bob');
    assert.equal(api.setListQuery('p'), 'push');
    assert.equal(api.setListQuery('pomp'), 'replace');
    assert.equal(session.length(), 2);
    assert.equal(paramsOf(session.getHref()).get('q'), 'pomp');
    assert.equal(paramsOf(session.getHref()).get('company'), 'KVT');
    assert.equal(paramsOf(session.getHref()).get('manager'), 'Bob');

    session.back();
    const actions = api.handlePopstate();
    assert.equal(actions.list, true);
    assert.equal(actions.closeProject, false);
    assert.deepEqual(events.at(-1), ['list', '']);
    assert.equal(api.current().q, '');

    session.forward();
    api.handlePopstate();
    assert.equal(api.current().q, 'pomp');
    assert.deepEqual(events.at(-1), ['list', 'pomp']);
    assert.equal(session.length(), 2);
});

test('project, regelzoekactie en status hebben elk een entry; terug loopt ze af', () => {
    const { session, events, api } = createHarness('http://localhost/elpis/index.php?company=KVT&q=pomp');
    assert.equal(api.openProject('P100'), 'push');
    assert.equal(api.current().project, 'P100');
    assert.equal(api.current().q, 'pomp');
    assert.equal(api.current().line_q, '');
    assert.deepEqual(events.at(-1), ['open', 'P100', 'pomp', '', '']);

    assert.equal(api.setLineQuery('m'), 'push');
    assert.equal(api.setLineQuery('moer'), 'replace');
    assert.equal(api.current().line_q, 'moer');
    assert.equal(session.length(), 3);

    assert.equal(api.setStatus('LATE'), 'push');
    assert.equal(api.setStatus('I'), 'push');
    assert.equal(api.current().line_status, 'I');
    assert.equal(api.current().line_q, 'moer');
    assert.equal(session.length(), 5);

    session.back();
    let actions = api.handlePopstate();
    assert.equal(actions.line, true);
    assert.equal(actions.openProject, false);
    assert.equal(api.current().line_status, 'LATE');
    assert.equal(api.current().project, 'P100');

    session.back();
    actions = api.handlePopstate();
    assert.equal(api.current().line_status, '');
    assert.equal(api.current().line_q, 'moer');

    session.back();
    actions = api.handlePopstate();
    assert.equal(actions.closeProject, false);
    assert.equal(actions.line, true);
    assert.equal(api.current().project, 'P100');
    assert.equal(api.current().line_q, '');

    session.back();
    actions = api.handlePopstate();
    assert.equal(actions.closeProject, true);
    assert.equal(api.current().project, '');
    assert.equal(api.current().q, 'pomp');
    assert.deepEqual(events.at(-1), ['close', '', '', '']);
    assert.equal(session.index(), 0);
});

test('sluiten duwt de lijst; terug opent het project weer, inclusief na herladen van de url', () => {
    const { session, events, api } = createHarness('http://localhost/elpis/index.php?company=KVT');
    api.openProject('P100');
    api.setLineQuery('moer');
    api.setStatus('LATE');
    events.length = 0;

    assert.equal(api.closeProject(), 'push');
    assert.equal(api.current().project, '');
    assert.equal(api.current().line_q, '');
    assert.equal(api.current().line_status, '');
    assert.deepEqual(events.at(-1), ['close', '', '', '']);

    session.back();
    const actions = api.handlePopstate();
    assert.equal(actions.openProject, true);
    assert.equal(api.current().project, 'P100');
    assert.equal(api.current().line_q, 'moer');
    assert.equal(api.current().line_status, 'LATE');
    assert.deepEqual(events.at(-1), ['open', 'P100', '', 'moer', 'LATE']);

    const reloaded = ElpisHistory.createProjectHistory({
        history: session.history,
        getHref: () => session.getHref(),
    });
    reloaded.seed();
    assert.equal(session.length(), 5);
    assert.deepEqual(reloaded.current(), {
        q: '',
        project: 'P100',
        line_q: 'moer',
        line_status: 'LATE',
    });
});

test('popstate duwt geen extra entry en negeert filterwijzigingen uit de handler', () => {
    const session = createMemorySession('http://localhost/elpis/index.php?company=KVT');
    let api;
    api = ElpisHistory.createProjectHistory({
        history: session.history,
        getHref: () => session.getHref(),
        onListFilter() {
            api.setListQuery('mag-niet');
        },
        onCloseProject() {
            api.openProject('mag-niet');
        },
    });
    api.seed();
    api.setListQuery('pomp');
    api.openProject('P100');
    const length = session.length();

    session.back();
    api.handlePopstate();
    session.back();
    api.handlePopstate();
    assert.equal(session.length(), length);
    assert.equal(api.current().q, '');
    assert.equal(api.current().project, '');
});

test('regelfilters zonder open project wijzigen de url niet', () => {
    const { session, api } = createHarness('http://localhost/elpis/index.php?company=KVT');
    assert.equal(api.setLineQuery('moer'), 'skipped');
    assert.equal(api.setStatus('LATE'), 'skipped');
    assert.equal(session.length(), 1);
    assert.equal(session.getHref().includes('line_q'), false);
});

test('een gedeelde project-url opent bij laden het project zonder extra history-entry', () => {
    const { session, api } = createHarness('http://localhost/elpis/index.php?company=KVT&manager=Bob&project=P100&line_q=moer&line_status=LATE');
    assert.equal(session.length(), 1);
    assert.equal(api.current().project, 'P100');
    assert.equal(api.current().line_q, 'moer');
    assert.equal(api.current().line_status, 'LATE');
    assert.equal(session.history.state.elpisView.project, 'P100');
});

test('index koppelt navigatie aan history-entries en niet aan replaceState van het project', () => {
    assert.match(indexPhp, /src="elpis_history\.js"/);
    assert.match(indexPhp, /history:\s*'deferred'/);
    assert.match(indexPhp, /ElpisHistory\.navigationMode/);
    assert.match(indexPhp, /ElpisHistory\.stripTransientParams/);
    assert.match(indexPhp, /ElpisHistory\.createProjectHistory/);
    assert.match(indexPhp, /projectHistory\.openProject/);
    assert.match(indexPhp, /projectHistory\.closeProject/);
    assert.match(indexPhp, /projectHistory\.setListQuery/);
    assert.match(indexPhp, /projectHistory\.setLineQuery/);
    assert.match(indexPhp, /projectHistory\.setStatus/);
    assert.match(indexPhp, /projectHistory\.install/);
    assert.match(indexPhp, /addEventListener\('pagehide'/);
    assert.match(indexPhp, /addEventListener\('pageshow'/);
    assert.match(indexPhp, /url\.searchParams\.set\('project', projectNo\)/);
    assert.doesNotMatch(indexPhp, /searchParams\.delete\('project'/);
    assert.doesNotMatch(indexPhp, /syncProjectSearchToUrl/);
    assert.doesNotMatch(indexPhp, /history\.replaceState\(\{\},\s*'',\s*url\.pathname/);
    assert.match(indexPhp, /'line_q'\s*=>\s*null/);
    assert.match(indexPhp, /'line_status'\s*=>\s*null/);
    assert.match(indexPhp, /name="line_q"|line_q/);
    assert.match(historyJs, /addEventListener\('popstate'/);
    assert.match(historyJs, /pushState/);
});
