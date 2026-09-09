'use strict';
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const script = fs.readFileSync('static/images-motion.js', 'utf8');
class Element extends EventTarget {
    constructor() { super(); this.attrs = {}; this.nodeType = 1; this.images = []; }
    getAttribute(key) { return this.attrs[key] || null; }
    setAttribute(key, value) { this.attrs[key] = value; }
    set src(value) { this.attrs.src = value; }
    get src() { return this.attrs.src; }
    querySelectorAll() { return this.images; }
}
function fixture(options = {}) {
    const grid = new Element();
    const makeImage = id => { const image = new Element(); image.src = '/proxy?i=https%3A%2F%2Fexample.org%2F' + id + '.gif&s=poster'; image.setAttribute('data-motion', '/proxy?i=https%3A%2F%2Fexample.org%2F' + id + '.gif&s=animated&preview=1'); return image; };
    grid.images = Array.from({length: 6}, (_, i) => makeImage(i));
    const document = new Element(); document.getElementById = () => grid; document.hidden = !!options.hidden;
    const reduced = new Element(); reduced.matches = !!options.reduced;
    const connection = new Element(); connection.saveData = !!options.saveData;
    const window = new Element(); window.location = new URL('https://securityops.co/images?s=GNU+Guix'); window.matchMedia = () => reduced;
    const observers = [], mutations = [], timers = new Map(); let timer = 0;
    class IO { constructor(callback, config) { this.callback = callback; this.config = config; this.nodes = []; observers.push(this); } observe(node) { this.nodes.push(node); } }
    class MO { constructor(callback) { this.callback = callback; mutations.push(this); } observe() {} }
    window.IntersectionObserver = options.unsupported ? null : IO; window.MutationObserver = MO;
    window.setTimeout = fn => { timers.set(++timer, fn); return timer; }; window.clearTimeout = id => timers.delete(id);
    vm.runInNewContext(script, {window, document, navigator: {connection}, IntersectionObserver: IO, MutationObserver: MO, URL});
    const visible = (images, value = true) => observers[0].callback(images.map(target => ({target, isIntersecting: value, intersectionRatio: value ? 1 : 0})));
    const playing = () => grid.images.filter(i => i.src.includes('s=animated'));
    return {grid, document, window, reduced, connection, observers, mutations, timers, visible, playing, makeImage};
}
let f = fixture(); assert.equal(f.playing().length, 0, 'No offscreen prefetch'); assert.equal(f.observers[0].config.rootMargin, '0px');
f.visible(f.grid.images); assert.equal(f.playing().length, 2, 'At most two requests begin');
f.grid.images[0].dispatchEvent(new Event('load')); assert.equal(f.playing().length, 3);
f.grid.images[1].dispatchEvent(new Event('load')); assert.equal(f.playing().length, 4);
f.grid.images[2].dispatchEvent(new Event('load')); f.grid.images[3].dispatchEvent(new Event('load'));
assert.equal(f.playing().length, 4, 'At most four visible animations');
f.visible([f.grid.images[0]], false); assert(f.grid.images[0].src.includes('s=poster')); assert(f.grid.images[4].src.includes('s=animated'));
f.grid.images[4].dispatchEvent(new Event('error')); assert(f.grid.images[4].src.includes('s=poster')); assert(f.grid.images[5].src.includes('s=animated'));
f.visible([f.grid.images[4]], false); f.visible([f.grid.images[4]]); assert(f.grid.images[4].src.includes('s=poster'), 'No repeated failed/static candidate');
f.document.hidden = true; f.document.dispatchEvent(new Event('visibilitychange')); assert.equal(f.playing().length, 0); assert.equal(f.timers.size, 0);
f.document.hidden = false; f.document.dispatchEvent(new Event('visibilitychange')); assert.equal(f.playing().length, 2);
f.reduced.matches = true; f.reduced.dispatchEvent(new Event('change')); assert.equal(f.playing().length, 0);
f.reduced.matches = false; f.connection.saveData = true; f.connection.dispatchEvent(new Event('change')); assert.equal(f.playing().length, 0);
f.connection.saveData = false; f.connection.dispatchEvent(new Event('change')); assert.equal(f.playing().length, 2);
f.window.dispatchEvent(new Event('pagehide')); assert.equal(f.playing().length, 0); f.window.dispatchEvent(new Event('pageshow')); assert.equal(f.playing().length, 2);
for (const fn of [...f.timers.values()]) fn(); assert(f.grid.images[1].src.includes('s=poster'), 'Deadline restores poster');
console.log('PASS: visible-only playback, two loading/four active limits, no failed retries, offscreen/hidden/reduced-motion/Save-Data suspension and deadline.');

for (const options of [{hidden: true}, {reduced: true}, {saveData: true}, {unsupported: true}]) {
    f = fixture(options); if (f.observers.length) f.visible(f.grid.images); assert.equal(f.playing().length, 0);
}
f = fixture(); const article = new Element(); article.images = [f.makeImage('appended')];
f.mutations[0].callback([{addedNodes: [article]}]); assert(f.observers[0].nodes.includes(article.images[0]));
f.visible(article.images); assert(article.images[0].src.includes('s=animated'), 'Appended card plays without a Next link dependency');
const bad = new Element(); bad.images = [f.makeImage('bad')]; bad.images[0].setAttribute('data-motion','https://evil.example/proxy?s=animated');
f.mutations[0].callback([{addedNodes: [bad]}]); assert(!f.observers[0].nodes.includes(bad.images[0]), 'Cross-origin motion never scheduled');
console.log('PASS: initial opt-outs, capability fallback, appended card registration and unsafe motion URL rejection.');
