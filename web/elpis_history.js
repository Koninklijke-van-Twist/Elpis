/**
 * URL- en history-staat voor Elpis-views.
 * company/manager blijven volledige paginanavigatie; project, zoekfilter en
 * detailfilters krijgen een eigen history-entry op dezelfde pagina.
 */
(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    root.ElpisHistory = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    var VIEW_KEYS = ['q', 'project', 'line_q', 'line_status'];
    var TRANSIENT_PARAMS = ['_loaded', 'reload_managers'];

    function emptyView() {
        return {
            q: '',
            project: '',
            line_q: '',
            line_status: ''
        };
    }

    function cloneView(view) {
        var copy = emptyView();
        VIEW_KEYS.forEach(function (key) {
            copy[key] = String(view && view[key] != null ? view[key] : '').trim();
        });
        return copy;
    }

    function readView(params) {
        var view = emptyView();
        VIEW_KEYS.forEach(function (key) {
            view[key] = String(params.get(key) || '').trim();
        });
        return view;
    }

    function writeView(params, view) {
        var source = cloneView(view);
        VIEW_KEYS.forEach(function (key) {
            if (source[key] === '') {
                params.delete(key);
            } else {
                params.set(key, source[key]);
            }
        });
    }

    function planViewChange(params, nextView) {
        var current = readView(params);
        var draft = new URLSearchParams(params.toString());
        writeView(draft, nextView);
        var written = readView(draft);
        var same = VIEW_KEYS.every(function (key) {
            return current[key] === written[key];
        });
        return same ? 'none' : 'push';
    }

    function diffViews(previous, next) {
        var before = cloneView(previous);
        var after = cloneView(next);
        return {
            list: before.q !== after.q,
            line: before.line_q !== after.line_q || before.line_status !== after.line_status,
            openProject: after.project !== '' && before.project !== after.project,
            closeProject: before.project !== '' && after.project === ''
        };
    }

    function navigationMode(reason) {
        return reason === 'deferred' ? 'replace' : 'assign';
    }

    function hasTransientParams(params) {
        return TRANSIENT_PARAMS.some(function (key) {
            return params.has(key);
        });
    }

    function stripTransientParams(params) {
        var changed = false;
        TRANSIENT_PARAMS.forEach(function (key) {
            if (params.has(key)) {
                params.delete(key);
                changed = true;
            }
        });
        return changed;
    }

    function createProjectHistory(options) {
        options = options || {};
        var historyApi = options.history || window.history;
        var getHref = options.getHref || function () {
            return window.location.href;
        };
        var onListFilter = options.onListFilter || function () {};
        var onOpenProject = options.onOpenProject || function () {};
        var onCloseProject = options.onCloseProject || function () {};
        var onLineFilter = options.onLineFilter || function () {};
        var applied = null;
        var locked = false;
        var gestureBase = { q: null, line_q: null };

        function hrefUrl() {
            return new URL(getHref());
        }

        function pathOf(url) {
            return url.pathname + url.search;
        }

        function current() {
            return readView(hrefUrl().searchParams);
        }

        function clearGestures() {
            gestureBase.q = null;
            gestureBase.line_q = null;
        }

        function commit(nextView) {
            if (locked) {
                return 'skipped';
            }
            var url = hrefUrl();
            if (planViewChange(url.searchParams, nextView) === 'none') {
                applied = readView(url.searchParams);
                return 'none';
            }
            writeView(url.searchParams, nextView);
            historyApi.pushState({ elpisView: readView(url.searchParams) }, '', pathOf(url));
            applied = readView(url.searchParams);
            return 'push';
        }

        function replaceCurrent(nextView) {
            if (locked) {
                return 'skipped';
            }
            var url = hrefUrl();
            if (planViewChange(url.searchParams, nextView) === 'none') {
                applied = readView(url.searchParams);
                return 'none';
            }
            writeView(url.searchParams, nextView);
            historyApi.replaceState({ elpisView: readView(url.searchParams) }, '', pathOf(url));
            applied = readView(url.searchParams);
            return 'replace';
        }

        function setFieldGesture(field, value) {
            if (locked) {
                return 'skipped';
            }
            if ((field === 'line_q' || field === 'line_status') && current().project === '') {
                return 'skipped';
            }

            var view = current();
            view[field] = String(value == null ? '' : value);

            if (field === 'line_status') {
                gestureBase.line_q = null;
                return commit(view);
            }

            if (gestureBase[field] == null) {
                var before = pathOf(hrefUrl());
                var action = commit(view);
                if (action === 'push') {
                    gestureBase[field] = before;
                }
                return action;
            }

            return replaceCurrent(view);
        }

        function seed() {
            clearGestures();
            var url = hrefUrl();
            applied = readView(url.searchParams);
            historyApi.replaceState({ elpisView: cloneView(applied) }, '', pathOf(url));
        }

        function openProject(projectNo) {
            projectNo = String(projectNo || '').trim();
            if (projectNo === '') {
                return 'none';
            }
            clearGestures();
            var view = current();
            view.project = projectNo;
            view.line_q = '';
            view.line_status = '';
            var action = commit(view);
            onOpenProject(cloneView(current()));
            return action;
        }

        function closeProject() {
            clearGestures();
            var view = current();
            view.project = '';
            view.line_q = '';
            view.line_status = '';
            var action = commit(view);
            onCloseProject(cloneView(current()));
            return action;
        }

        function handlePopstate() {
            clearGestures();
            locked = true;
            var next = current();
            var previous = applied || emptyView();
            var actions = diffViews(previous, next);
            applied = cloneView(next);
            if (actions.list) {
                onListFilter(cloneView(next));
            }
            if (actions.closeProject) {
                onCloseProject(cloneView(next));
            } else if (actions.openProject) {
                onOpenProject(cloneView(next));
            } else if (actions.line) {
                onLineFilter(cloneView(next));
            }
            locked = false;
            return actions;
        }

        function install() {
            if (applied == null) {
                seed();
            }
            var target = options.target || window;
            target.addEventListener('popstate', handlePopstate);
        }

        return {
            current: current,
            seed: seed,
            install: install,
            handlePopstate: handlePopstate,
            openProject: openProject,
            closeProject: closeProject,
            setListQuery: function (value) {
                return setFieldGesture('q', value);
            },
            setLineQuery: function (value) {
                return setFieldGesture('line_q', value);
            },
            setStatus: function (value) {
                return setFieldGesture('line_status', value);
            }
        };
    }

    return {
        viewKeys: VIEW_KEYS,
        emptyView: emptyView,
        cloneView: cloneView,
        readView: readView,
        writeView: writeView,
        planViewChange: planViewChange,
        diffViews: diffViews,
        navigationMode: navigationMode,
        hasTransientParams: hasTransientParams,
        stripTransientParams: stripTransientParams,
        createProjectHistory: createProjectHistory
    };
});
