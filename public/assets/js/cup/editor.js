"use strict";

/**
 * File editor of the admin panel: explorer, tabs, CodeMirror, quick open, status bar.
 * Talks to /cup/editor/{path} (see EditorPageAction): ?json=1 reads, POST save=ajax|create|delete writes.
 */
document.addEventListener('DOMContentLoaded', async () => {
    const CFG = window.EDITOR, T = Object.assign({
        newFile: 'Path of the new file', quick: 'Search a file by name', del: 'Delete', confirmDelete: 'Delete the file?',
        confirmClose: 'Discard the unsaved changes?', saved: 'Saved', readonly: 'Read only', newHere: 'New file', copyPath: 'Copy path', none: 'No data'
    }, CFG.t || {});
    const $ = (sel, root = document) => root.querySelector(sel);
    const root = $('#vsc');
    const el = {
        side: $('#vsc-side', root), tree: $('[data-tree]', root), filter: $('[data-filter]', root), tabs: $('[data-tabs]', root),
        crumbs: $('[data-crumbs]', root), cm: $('[data-cm]', root), welcome: $('[data-welcome]', root), status: $('.vsc-status', root),
        palette: $('[data-palette]', root), pin: $('[data-palette-input]', root), plist: $('[data-palette-list]', root), menu: $('[data-menu]', root),
        resize: $('[data-resize]', root),
    };
    const st = (name) => $(`[data-st="${name}"]`, root);
    const url = (path, query = '') => '/cup/editor/' + path.split('/').map(encodeURIComponent).join('/') + query;
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
    const store = {
        get: () => { try { return JSON.parse(localStorage.getItem('wse-editor') || '{}'); } catch (e) { return {}; } },
        set: (data) => { try { localStorage.setItem('wse-editor', JSON.stringify(Object.assign(store.get(), data))); } catch (e) { /* optional */ } },
    };

    // ---- CodeMirror ------------------------------------------------------------------------------------------
    const v = '?v=' + (document.querySelector('script[src*="cup/editor.js"]').src.split('v=')[1] || '');
    const cmBase = '/assets/js/plugin/codemirror/';
    await window.loader([
        'lib/codemirror.css', 'lib/codemirror.js', 'addon/mode/overlay.js', 'addon/edit/matchbrackets.js', 'addon/dialog/dialog.css', 'addon/dialog/dialog.js',
        'addon/search/search.js', 'addon/search/searchcursor.js', 'addon/search/jump-to-line.js', 'addon/selection/active-line.js', 'addon/selection/mark-selection.js',
        'mode/css/css.js', 'mode/xml/xml.js', 'mode/javascript/javascript.js', 'mode/htmlmixed/htmlmixed.js', 'mode/markdown/markdown.js',
    ].map((f) => cmBase + f + v));

    // html with twig tags painted on top
    CodeMirror.defineMode('twig-html', (config) => CodeMirror.overlayMode(CodeMirror.getMode(config, 'htmlmixed'), {
        startState: () => ({close: null}),
        token(stream, state) {
            if (state.close) {
                if (stream.skipTo(state.close)) { stream.match(state.close); state.close = null; } else { stream.skipToEnd(); }
                return 'twig';
            }
            let m = stream.match(/^\{[{%#]/);
            if (m) {
                state.close = {'{{': '}}', '{%': '%}', '{#': '#}'}[m[0]];
                if (stream.skipTo(state.close)) { stream.match(state.close); state.close = null; } else { stream.skipToEnd(); }
                return 'twig';
            }
            while (stream.next() != null && !stream.match(/^\{[{%#]/, false)) { /* next token */ }
            return null;
        },
    }));

    const LANGS = {
        twig: {mode: 'twig-html', label: 'Twig', block: ['{#', '#}']}, html: {mode: 'htmlmixed', label: 'HTML', block: ['<!--', '-->']},
        htm: {mode: 'htmlmixed', label: 'HTML', block: ['<!--', '-->']}, css: {mode: 'css', label: 'CSS', block: ['/*', '*/']},
        js: {mode: 'javascript', label: 'JavaScript', line: '//'}, json: {mode: {name: 'javascript', json: true}, label: 'JSON'},
        md: {mode: 'markdown', label: 'Markdown'}, xml: {mode: 'xml', label: 'XML', block: ['<!--', '-->']}, svg: {mode: 'xml', label: 'SVG', block: ['<!--', '-->']},
        txt: {mode: null, label: 'Plain Text'},
    };
    const lang = (path) => LANGS[(path.split('.').pop() || '').toLowerCase()] || {mode: null, label: 'Plain Text'};
    const ICONS = {
        twig: ['fa-file-code', '#8dc149'], html: ['fa-file-code', '#e44d26'], htm: ['fa-file-code', '#e44d26'], css: ['fa-hashtag', '#42a5f5'], js: ['fa-file-code', '#f1e05a'],
        json: ['fa-file-code', '#cbcb41'], md: ['fa-file-alt', '#519aba'], svg: ['fa-file-image', '#ffb13b'], png: ['fa-file-image', '#a074c4'], jpg: ['fa-file-image', '#a074c4'],
        jpeg: ['fa-file-image', '#a074c4'], gif: ['fa-file-image', '#a074c4'], webp: ['fa-file-image', '#a074c4'], txt: ['fa-file-alt', '#999'], xml: ['fa-file-code', '#e37933'],
    };
    const icon = (name) => {
        let [cls, color] = ICONS[(name.split('.').pop() || '').toLowerCase()] || ['fa-file', '#999'];

        return `<i class="fas ${cls} ico" style="color:${color}"></i>`;
    };

    const cm = CodeMirror(el.cm, {
        lineNumbers: true, theme: 'vscode', indentUnit: 4, tabSize: 4, matchBrackets: true, styleActiveLine: true, styleSelectedText: true, viewportMargin: 50,
        extraKeys: {
            'Ctrl-S': () => save(), 'Cmd-S': () => save(), 'Ctrl-/': 'toggleCommentLines', 'Cmd-/': 'toggleCommentLines',
            Tab: (c) => (c.somethingSelected() ? c.indentSelection('add') : c.replaceSelection('    ', 'end')), 'Shift-Tab': (c) => c.indentSelection('subtract'),
        },
    });
    CodeMirror.commands.toggleCommentLines = (c) => {
        let l = tab() ? lang(tab().path) : {}, from = c.getCursor('from').line, to = c.getCursor('to').line, lines = [];

        for (let i = from; i <= to; i++) { lines.push(c.getLine(i)); }
        c.operation(() => {
            if (l.line) {
                let all = lines.every((s) => !s.trim() || s.trim().startsWith(l.line));

                lines.forEach((s, k) => s.trim() && c.replaceRange(all ? s.replace(new RegExp('^(\\s*)' + l.line.replace(/\//g, '\\/') + ' ?'), '$1') : s.replace(/^(\s*)/, '$1' + l.line + ' '), {line: from + k, ch: 0}, {line: from + k, ch: s.length}));
            } else if (l.block) {
                let text = c.getRange({line: from, ch: 0}, {line: to, ch: lines[lines.length - 1].length}), [a, b] = l.block,
                    has = text.trim().startsWith(a) && text.trim().endsWith(b);

                c.replaceRange(has ? text.replace(a + ' ', '').replace(a, '').replace(' ' + b, '').replace(b, '') : a + ' ' + text + ' ' + b, {line: from, ch: 0}, {line: to, ch: lines[lines.length - 1].length});
            }
        });
    };

    // ---- state -------------------------------------------------------------------------------------------------
    const tabs = [];            // {path, doc, gen, size, writable}
    let active = null;
    const files = [];           // flat list of every file path for quick open
    const expanded = new Set(store.get().expanded || ['theme']);
    const tab = () => tabs.find((t) => t.path === active);
    const dirty = (t) => !t.doc.isClean(t.gen);
    const say = (text, ms = 2500) => { st('message').textContent = text; if (ms) { setTimeout(() => { if (st('message').textContent === text) { st('message').textContent = ''; } }, ms); } };
    const flatten = (nodes, prefix) => nodes.forEach((n) => (n.type === 'dir' ? flatten(n.list, prefix + n.name + '/') : files.push(prefix + n.name)));
    CFG.tree.forEach((root) => flatten(root.list, root.name + '/'));

    // ---- explorer -----------------------------------------------------------------------------------------------
    const row = (cls, pad, html, attrs = '') => `<div class="vsc-row ${cls}" style="padding-left:${pad}px" ${attrs}>${html}</div>`;
    const renderTree = () => {
        let q = el.filter.value.trim().toLowerCase(), html = '';

        const walk = (nodes, prefix, depth) => nodes.forEach((n) => {
            let path = prefix + n.name;

            if (n.type === 'dir') {
                let open = expanded.has(path);

                html += row('', 8 + depth * 12, `<span class="chev"><i class="fas fa-chevron-${open ? 'down' : 'right'}"></i></span><i class="fas fa-folder${open ? '-open' : ''} ico" style="color:#dcb67a"></i><span class="nm">${esc(n.name)}</span>`, `data-dir="${esc(path)}"`);
                if (open) { walk(n.list, path + '/', depth + 1); }
            } else {
                html += row(path === active ? 'active' : '', 8 + depth * 12 + 16, `${icon(n.name)}<span class="nm">${esc(n.name)}</span>${tabs.some((t) => t.path === path && dirty(t)) ? '<i class="fas fa-circle dot"></i>' : ''}`, `data-file="${esc(path)}"`);
            }
        });

        if (q) {
            let found = files.filter((p) => p.toLowerCase().includes(q)).slice(0, 200);

            html = found.map((p) => row(p === active ? 'active' : '', 12, `${icon(p)}<span class="nm">${esc(p.split('/').pop())}</span><span class="path">${esc(p.split('/').slice(0, -1).join('/'))}</span>`, `data-file="${esc(p)}"`)).join('') || `<div class="vsc-empty">${esc(T.none)}</div>`;
        } else {
            CFG.tree.forEach((r) => {
                let open = expanded.has(r.name);

                html += row('root', 4, `<span class="chev"><i class="fas fa-chevron-${open ? 'down' : 'right'}"></i></span><span class="nm">${esc(r.title)}</span>`, `data-dir="${esc(r.name)}"`);
                if (open) { walk(r.list, r.name + '/', 0); }
            });
        }
        el.tree.innerHTML = html;
    };
    el.tree.addEventListener('click', (e) => {
        let node = e.target.closest('[data-dir],[data-file]');

        if (!node) { return; }
        if (node.dataset.file) { return open(node.dataset.file); }
        expanded.has(node.dataset.dir) ? expanded.delete(node.dataset.dir) : expanded.add(node.dataset.dir);
        store.set({expanded: [...expanded]});
        renderTree();
    });
    el.filter.addEventListener('input', renderTree);

    // ---- tabs ---------------------------------------------------------------------------------------------------
    const renderTabs = () => {
        el.tabs.innerHTML = tabs.map((t) => `<div class="vsc-tab ${t.path === active ? 'active' : ''} ${dirty(t) ? 'dirty' : ''}" data-tab="${esc(t.path)}" title="${esc(t.path)}">${icon(t.path)}<span>${esc(t.path.split('/').pop())}</span><span class="x" data-close="${esc(t.path)}"><i class="fas fa-times"></i></span></div>`).join('');
        let a = $('.vsc-tab.active', el.tabs);

        if (a) { a.scrollIntoView({block: 'nearest', inline: 'nearest'}); }
    };
    el.tabs.addEventListener('click', (e) => {
        let close = e.target.closest('[data-close]'), t = e.target.closest('[data-tab]');

        if (close) { return closeTab(close.dataset.close); }
        if (t) { activate(t.dataset.tab); }
    });
    el.tabs.addEventListener('auxclick', (e) => {
        let t = e.target.closest('[data-tab]');

        if (e.button === 1 && t) { e.preventDefault(); closeTab(t.dataset.tab); }
    });

    const refresh = () => {
        let t = tab(), l = t ? lang(t.path) : null;

        el.cm.hidden = !t;
        el.welcome.hidden = !!t;
        renderTabs();
        renderTree();
        el.crumbs.innerHTML = t ? t.path.split('/').map((p) => `<span>${esc(p)}</span>`).join('') : '';
        st('lang').textContent = l ? l.label : '';
        st('size').textContent = t ? (t.size / 1024).toFixed(t.size < 10240 ? 1 : 0) + ' KB' : '';
        el.status.classList.toggle('readonly', !!t && !t.writable);
        if (t && !t.writable) { say(T.readonly, 0); } else if (st('message').textContent === T.readonly) { st('message').textContent = ''; }
        if (!t) { st('pos').textContent = ''; }
        document.title = (t ? t.path.split('/').pop() + ' - ' : '') + 'WebSpace';
    };

    const activate = (path) => {
        let t = tabs.find((x) => x.path === path);

        if (!t) { return; }
        active = path;
        cm.swapDoc(t.doc);
        cm.setOption('readOnly', t.writable ? false : true);
        refresh();
        cm.refresh();
        cm.focus();
        store.set({tabs: tabs.map((x) => x.path), active});
        history.replaceState(null, '', url(path));
    };

    const open = async (path, quiet = false) => {
        if (tabs.some((t) => t.path === path)) { return activate(path); }

        let res, data;

        try {
            res = await fetch(url(path, '?json=1'), {credentials: 'same-origin'});
            data = await res.json();
        } catch (e) { data = {ok: false, error: String(e)}; }

        if (!data.ok) {
            if (!quiet) { say(path.split('/').pop() + ': ' + data.error, 5000); }

            return false;
        }

        let doc = CodeMirror.Doc(data.content, lang(path).mode);

        tabs.push({path, doc, gen: doc.changeGeneration(true), size: data.size, writable: data.writable});
        activate(path);
    };

    const closeTab = (path) => {
        let i = tabs.findIndex((t) => t.path === path);

        if (i < 0) { return; }
        if (dirty(tabs[i]) && !confirm(T.confirmClose)) { return; }
        tabs.splice(i, 1);
        if (active === path) {
            active = null;
            if (tabs.length) { return activate(tabs[Math.min(i, tabs.length - 1)].path); }
            cm.swapDoc(CodeMirror.Doc(''));
            history.replaceState(null, '', '/cup/editor');
        }
        store.set({tabs: tabs.map((x) => x.path), active});
        refresh();
    };

    cm.on('change', () => { renderTabs(); renderTree(); });
    cm.on('cursorActivity', () => { let c = cm.getCursor(); st('pos').textContent = `Ln ${c.line + 1}, Col ${c.ch + 1}`; });

    // ---- save / create / delete ----------------------------------------------------------------------------------
    const post = async (path, fields) => {
        let res = await fetch(url(path), {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams(fields)});

        return res.json().catch(() => ({ok: false, error: 'Bad response'}));
    };

    const save = async () => {
        let t = tab();

        if (!t || !t.writable) { return; }

        let content = t.doc.getValue(), gen = t.doc.changeGeneration(true), data = await post(t.path, {save: 'ajax', content});

        if (data.ok) {
            t.gen = gen;
            t.size = data.size;
            refresh();
            say(T.saved + ' · ' + t.path.split('/').pop());
        } else {
            say(data.error || 'Error', 6000);
        }
    };

    const createFile = async (path) => {
        path = path.trim().replace(/^\/+/, '');
        if (!path) { return; }

        let data = await post(path, {save: 'create'});

        if (!data.ok) { return say(data.error || 'Error', 6000); }

        // add to the tree and the quick-open list, expand its folders
        let parts = path.split('/'), nodes = (CFG.tree.find((r) => r.name === parts[0]) || {}).list;

        if (nodes) {
            for (let i = 1; i < parts.length - 1; i++) {
                expanded.add(parts.slice(0, i + 1).join('/'));

                let dir = nodes.find((n) => n.type === 'dir' && n.name === parts[i]);

                if (!dir) { dir = {name: parts[i], type: 'dir', list: []}; nodes.unshift(dir); }
                nodes = dir.list;
            }
            nodes.push({name: parts[parts.length - 1], type: 'file', size: 0});
        }
        files.push(path);
        await open(path);
    };

    const removeFile = async (path) => {
        if (!confirm(T.confirmDelete + '\n' + path)) { return; }

        let data = await post(path, {save: 'delete'});

        if (!data.ok) { return say(data.error || 'Error', 6000); }

        let t = tabs.find((x) => x.path === path);

        if (t) { t.gen = t.doc.changeGeneration(true); closeTab(path); }

        const drop = (nodes, parts) => { let i = nodes.findIndex((n) => n.name === parts[0] && (parts.length === 1) === (n.type === 'file')); if (parts.length === 1) { if (i >= 0) { nodes.splice(i, 1); } } else if (i >= 0) { drop(nodes[i].list, parts.slice(1)); } };
        let parts = path.split('/');

        drop((CFG.tree.find((r) => r.name === parts[0]) || {list: []}).list, parts.slice(1));
        files.splice(files.indexOf(path), 1);
        renderTree();
    };

    // ---- palette (quick open and "new file" input) ---------------------------------------------------------------
    let pal = null;
    const score = (path, q) => {
        let name = path.toLowerCase(), i = 0, s = 0, last = -2;

        for (const ch of q) {
            let at = name.indexOf(ch, i);

            if (at < 0) { return -1; }
            s += at === last + 1 ? 3 : 1;
            if (at === 0 || '/._-'.includes(name[at - 1])) { s += 2; }
            last = at;
            i = at + 1;
        }

        return s - name.length / 100 + (name.split('/').pop().includes(q) ? 20 : 0);
    };
    const palRender = () => {
        if (pal.mode !== 'files') { el.plist.innerHTML = ''; return; }

        let q = el.pin.value.trim().toLowerCase();

        pal.items = (q ? files.map((p) => [p, score(p, q)]).filter((x) => x[1] >= 0).sort((a, b) => b[1] - a[1]).map((x) => x[0]) : [...tabs.map((t) => t.path), ...files.filter((p) => !tabs.some((t) => t.path === p))]).slice(0, 50);
        pal.sel = Math.min(pal.sel, Math.max(0, pal.items.length - 1));
        el.plist.innerHTML = pal.items.map((p, i) => `<div class="vsc-palette-item ${i === pal.sel ? 'sel' : ''}" data-i="${i}">${icon(p)}<span>${esc(p.split('/').pop())}</span><small>${esc(p.split('/').slice(0, -1).join('/'))}</small></div>`).join('');
        let s = $('.sel', el.plist);

        if (s) { s.scrollIntoView({block: 'nearest'}); }
    };
    const palOpen = (mode, value = '', placeholder = '') => {
        pal = {mode, sel: 0, items: []};
        el.palette.hidden = false;
        el.pin.value = value;
        el.pin.placeholder = placeholder;
        palRender();
        el.pin.focus();
        el.pin.setSelectionRange(value.length, value.length);
    };
    const palClose = () => { el.palette.hidden = true; pal = null; if (tab()) { cm.focus(); } };
    const palAccept = () => {
        if (!pal) { return; }

        let mode = pal.mode, value = el.pin.value, pick = pal.items[pal.sel];

        palClose();
        if (mode === 'files' && pick) { open(pick); }
        if (mode === 'new') { createFile(value); }
    };
    el.pin.addEventListener('input', () => { if (pal) { pal.sel = 0; palRender(); } });
    el.pin.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.preventDefault(); palClose(); }
        if (e.key === 'Enter') { e.preventDefault(); palAccept(); }
        if (pal && pal.mode === 'files' && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            pal.sel = (pal.sel + (e.key === 'ArrowDown' ? 1 : -1) + pal.items.length) % Math.max(1, pal.items.length);
            palRender();
        }
    });
    el.plist.addEventListener('click', (e) => { let it = e.target.closest('[data-i]'); if (it) { pal.sel = +it.dataset.i; palAccept(); } });
    el.palette.addEventListener('mousedown', (e) => { if (e.target === el.palette) { palClose(); } });

    const quick = () => palOpen('files', '', T.quick);
    const newFile = (folder = '') => palOpen('new', folder, T.newFile);

    // ---- context menu in the explorer ----------------------------------------------------------------------------
    el.tree.addEventListener('contextmenu', (e) => {
        let node = e.target.closest('[data-dir],[data-file]');

        if (!node) { return; }
        e.preventDefault();

        let path = node.dataset.file || node.dataset.dir, folder = node.dataset.file ? path.split('/').slice(0, -1).join('/') : path,
            items = [[T.newHere, () => newFile(folder + '/')], [T.copyPath, () => navigator.clipboard && navigator.clipboard.writeText(path)]];

        if (node.dataset.file) { items.push([T.del, () => removeFile(path)]); }
        el.menu.innerHTML = items.map((it, i) => `<div data-m="${i}">${esc(it[0])}</div>`).join('');
        el.menu.hidden = false;

        let box = root.getBoundingClientRect();

        el.menu.style.left = Math.min(e.clientX - box.left, box.width - 190) + 'px';
        el.menu.style.top = Math.min(e.clientY - box.top, box.height - 120) + 'px';
        el.menu._items = items;
    });
    el.menu.addEventListener('click', (e) => { let m = e.target.closest('[data-m]'); if (m) { el.menu._items[+m.dataset.m][1](); } el.menu.hidden = true; });
    document.addEventListener('click', () => { el.menu.hidden = true; });

    // ---- toolbar, sidebar width, global keys ---------------------------------------------------------------------
    root.addEventListener('click', (e) => {
        let b = e.target.closest('[data-act]');

        if (!b) { return; }
        ({
            sidebar: () => { el.side.classList.toggle('is-hidden'); b.classList.toggle('active', !el.side.classList.contains('is-hidden')); cm.refresh(); },
            quick, new: () => newFile(''), collapse: () => { expanded.clear(); store.set({expanded: []}); renderTree(); },
        })[b.dataset.act]();
    });

    let w = store.get().width;

    if (w) { root.style.setProperty('--side-w', w + 'px'); }
    el.resize.addEventListener('mousedown', (e) => {
        e.preventDefault();
        el.resize.classList.add('drag');

        const move = (ev) => { let x = Math.max(160, Math.min(600, ev.clientX - root.getBoundingClientRect().left - 48)); root.style.setProperty('--side-w', x + 'px'); w = x; cm.refresh(); };
        const up = () => { el.resize.classList.remove('drag'); document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); store.set({width: w}); };

        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', up);
    });

    document.addEventListener('keydown', (e) => {
        let mod = e.ctrlKey || e.metaKey, k = e.key.toLowerCase();

        if (mod && k === 'p') { e.preventDefault(); quick(); }
        if (mod && k === 's') { e.preventDefault(); save(); }
        if (mod && k === 'b') { e.preventDefault(); $('[data-act="sidebar"]', root).click(); }
        if (mod && k === 'w' && active) { e.preventDefault(); closeTab(active); }
    }, true);

    window.addEventListener('beforeunload', (e) => {
        if (tabs.some(dirty)) { e.preventDefault(); e.returnValue = ''; }
    });

    // ---- start ---------------------------------------------------------------------------------------------------
    renderTree();
    refresh();

    let saved = store.get(), first = CFG.open || saved.active;

    for (const p of (saved.tabs || [])) { if (files.includes(p) && p !== first) { await open(p, true); } }
    if (CFG.open) { await open(CFG.open); } else if (saved.active && files.includes(saved.active)) { await open(saved.active, true); }
    if (!tab() && tabs.length) { activate(tabs[0].path); }
    window.__editor = {tabs, open, save, cm};
});
