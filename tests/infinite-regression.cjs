/* Node-only state tests for the actual enhancer, using a minimal DOM fixture.
 * No browser, packages, network or production provider requests. */
'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const code = fs.readFileSync('static/images-infinite.js', 'utf8');
class Node {
    constructor(tag = '') { this.tagName = tag; this.children = []; this.attrs = {}; this.listeners = {}; this.textContent = ''; this.hidden = false; }
    setAttribute(key, value) { this.attrs[key] = String(value); }
    getAttribute(key) { return this.attrs[key] ?? null; }
    removeAttribute(key) { delete this.attrs[key]; }
    set href(value) { this.setAttribute('href', value); }
    get href() { return this.getAttribute('href'); }
    get classList() { return {contains: value => (this.className || '').split(' ').includes(value)}; }
    append(...nodes) { for (const node of nodes) this.children.push(...(node.tagName === '#fragment' ? node.children : [node])); }
    before(node) { this.status = node; }
    get lastElementChild() { return this.children.at(-1); }
    querySelectorAll() { return this.children; }
    getBoundingClientRect() { return this.rect || {top: 100, bottom: 500}; }
    addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); }
    removeEventListener(type, fn) { this.listeners[type] = (this.listeners[type] || []).filter(item => item !== fn); }
    dispatch(type, values = {}) {
        const event = {button: 0, preventDefault() { this.prevented = true; }, ...values};
        for (const fn of this.listeners[type] || []) fn(event);
        return event;
    }
}
const flush = async () => { for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve)); };
function fixture(options = {}) {
    const grid = new Node('div'); grid.setAttribute('data-provider', 'google'); grid.className = options.filmstrip ? 'images-view-filmstrip' : 'images-view-grid';
    for (let i = 0; i < (options.count || 24); i++) grid.append(new Node('article'));
    const next = new Node('a'); next.href = options.href || '/images?s=GNU+Guix&scraper=google&view=grid&quality=high&format=gif&npt=page2';
    const doc = {hidden: false, getElementById: () => grid, querySelector: () => next,
        createElement: tag => new Node(tag), createDocumentFragment: () => new Node('#fragment')};
    const calls = [], pending = [], observers = [], timers = new Map();
    const window = new Node('window'); window.location = new URL('https://securityops.co/images?s=GNU+Guix&scraper=google&view=grid&quality=high&format=gif');
    window.innerHeight = 800; window.AbortController = AbortController; window.ReadableStream = ReadableStream; window.TextDecoder = TextDecoder;
    window.setTimeout = fn => { const id = timers.size + 1; timers.set(id, fn); return id; };
    window.clearTimeout = id => timers.delete(id);
    class Observer {
        constructor(callback, config) { this.callback = callback; this.config = config; observers.push(this); }
        observe(node) { this.target = node; }
        disconnect() { this.target = null; }
        intersect(value = true) { this.callback([{isIntersecting: value}]); }
    }
    const fetch = async (url, config) => {
        calls.push({url, config});
        return new Promise((resolve, reject) => {
            config.signal.addEventListener('abort', () => reject(new Error('aborted')), {once: true});
            pending.push({resolve, reject});
        });
    };
    window.IntersectionObserver = options.unsupported ? null : Observer; window.fetch = fetch;
    vm.runInNewContext(code, {window, document: doc, navigator: {connection: {saveData: options.saveData}},
        IntersectionObserver: Observer, URL, TextDecoder, AbortController, fetch});
    return {grid, next, doc, calls, pending, observers, timers, window,
        start() { observers[0].intersect(); window.dispatch('scroll'); },
        reply(data, extra = {}) { pending.shift().resolve(new Response(JSON.stringify(data), {headers: {'Content-Type': 'application/json'}, ...extra})); }};
}
function item(title = 'GNU Guix') {
    return {original: '/proxy?i=https%3A%2F%2Fexample.org%2Fa.gif&s=original', preview: '/proxy?i=https%3A%2F%2Fexample.org%2Fa.gif&s=thumb',
        title, source: 'https://example.org/image', host: 'example.org', width: 400, height: 300,
        links: [{label: 'Original', href: '/proxy?i=https%3A%2F%2Fexample.org%2Fa.gif&s=original'}]};
}
function page(number = 3, items = [item()]) { return {version: 1, provider: 'google', items, next: '/images?s=GNU+Guix&scraper=google&view=grid&quality=high&format=gif&npt=page' + number, omitted: 0}; }
(async () => {
    let f = fixture(); f.observers[0].intersect(); assert.equal(f.calls.length, 0, 'No automatic load before scrolling');
    f.window.dispatch('scroll'); f.observers[0].intersect(); f.window.dispatch('scroll');
    assert(f.next.dispatch('click').prevented); assert.equal(f.calls.length, 1, 'Single-flight observer/click');
    assert.equal(f.next.href, null, 'Consumed URL unavailable while busy');
    assert.equal(new URL(f.calls[0].url).searchParams.get('append'), '1');
    assert.equal(f.calls[0].config.redirect, 'error');
    f.reply(page(3, [item('<img src=x onerror=alert(1)>')])); await flush();
    assert.equal(f.grid.children.length, 25, 'Appends without replacing initial cards');
    let image = f.grid.lastElementChild.children[0].children[0].children[0];
    assert.equal(image.loading, 'lazy'); assert.equal(image.decoding, 'async'); assert.equal(image.getAttribute('fetchpriority'), 'low');
    assert.equal(image.alt, '<img src=x onerror=alert(1)>', 'Title is literal text');
    assert.equal(f.grid.lastElementChild.children[0].children[1].children[1].textContent, image.alt);
    f.observers[0].intersect(); assert.equal(f.calls.length, 1, 'No short-page request loop');
    for (let p = 4; p <= 25; p++) { f.window.dispatch('scroll'); f.reply(page(p)); await flush(); f.observers[0].intersect(); }
    assert.equal(f.calls.length, 23, 'Continues beyond old 3/10-page limits');
    f.window.dispatch('scroll'); f.reply({...page(), next: null}); await flush();
    assert(f.next.hidden); assert.match(f.next.status.textContent, /end/); assert.equal(f.observers[0].target, null);
    console.log('PASS: append, lazy images, literal text, single flight, real continuation beyond 10 pages and final-page stop.');

    for (const options of [{saveData: true}, {unsupported: true}]) {
        f = fixture(options); assert.equal(f.observers.length, 0); assert(f.next.href.includes('npt=page2'));
    }
    f = fixture({filmstrip: true}); assert.equal(f.observers[0].config.root, f.grid);
    f.doc.hidden = true; f.start(); assert.equal(f.calls.length, 0);
    f.doc.hidden = false; f.grid.rect = {top: 900, bottom: 1200}; f.grid.dispatch('scroll'); assert.equal(f.calls.length, 0);
    f.grid.rect = {top: 100, bottom: 500}; f.grid.dispatch('scroll'); assert.equal(f.calls.length, 1);
    f.reply(page(3, [])); await flush(); assert.match(f.next.status.textContent, /No images/); assert(f.next.href.includes('page3'));
    f.window.dispatch('scroll'); assert.equal(f.calls.length, 1);
    f = fixture({count: 456}); f.start(); f.reply(page(3, Array.from({length: 24}, () => item()))); await flush();
    assert.equal(f.grid.children.length, 480); assert.match(f.next.status.textContent, /480 images/);
    assert(!f.next.dispatch('click').prevented, 'Native continuation at DOM budget');
    console.log('PASS: Save-Data/unsupported fallback, hidden/offscreen filmstrip, empty continuation and bounded DOM.');

    for (const href of ['https://evil.example/images?npt=x', '/settings?npt=x', 'https://user@securityops.co/images?npt=x']) {
        f = fixture({href}); f.start(); assert.equal(f.calls.length, 0);
    }
    const badPages = [
        {...page(), next: 'https://evil.example/images?npt=x'},
        {...page(), next: '/images?frame=secret&npt=x'},
        {...page(), next: '/images?s=GNU+Guix&scraper=google&view=grid&quality=high&format=gif&npt=page2'},
        {...page(), next: '/images?s=changed&npt=page2'},
        {...page(), items: [{...item(), preview: 'https://evil.example/image.jpg'}]},
        {...page(), items: [{...item(), source: 'javascript:alert(1)'}]},
        {...page(), items: [{...item(), width: -1}]},
        {...page(), items: Array.from({length: 25}, () => item())},
        {version: 99},
        {...page(), provider: 'brave'},
        {...page(), items: [{...item(), motion: 'https://evil.example/a.gif'}]},
    ];
    for (const data of badPages) {
        f = fixture(); f.start(); f.reply(data); await flush();
        assert.equal(f.grid.children.length, 24); assert.equal(f.next.textContent, 'Restart search');
        assert(!new URL(f.next.href).searchParams.has('npt')); assert(f.calls[0].config.signal.aborted);
        f.window.dispatch('scroll'); f.observers[0].intersect(); assert.equal(f.calls.length, 1);
    }
    for (const extra of [{status: 503}, {headers: {'Content-Type': 'text/html'}},
        {headers: {'Content-Type': 'application/json', 'Content-Length': String(2 * 1024 * 1024)}}]) {
        f = fixture(); f.start(); f.reply(page(), extra); await flush();
        assert.equal(f.next.textContent, 'Restart search'); assert(f.calls[0].config.signal.aborted, 'Discarded body cancelled');
    }
    f = fixture(); f.start(); f.pending.shift().resolve({ok: true, redirected: true, headers: new Headers()}); await flush();
    assert.equal(f.next.textContent, 'Restart search');
    f = fixture(); f.start(); f.pending.shift().resolve(new Response('x'.repeat(1024 * 1024 + 1), {headers: {'Content-Type': 'application/json'}})); await flush();
    assert(f.calls[0].config.signal.aborted); assert.equal(f.grid.children.length, 24);
    f = fixture(); f.start(); for (const fn of f.timers.values()) fn(); await flush();
    assert(f.calls[0].config.signal.aborted); assert.equal(f.next.textContent, 'Restart search');
    f = fixture(); f.start(); f.window.dispatch('pagehide'); await flush();
    assert(f.calls[0].config.signal.aborted); assert.equal(f.next.textContent, 'Restart search');
    console.log('PASS: unsafe URL/data, repeated token, redirects, status/type, declared/streamed size, deadline and navigation abort; no retry of consumed tokens.');
})().catch(error => { console.error(error); process.exitCode = 1; });
