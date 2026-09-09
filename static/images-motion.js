/* Visible GIF/WebP/APNG playback; native posters and links remain the fallback. */
(() => {
    'use strict';
    const grid = document.getElementById('images');
    if (!grid || !window.IntersectionObserver || !window.MutationObserver || !window.matchMedia) return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection;
    const entries = new Map();
    const MAX_PLAYING = 4, MAX_LOADING = 2;
    let active = 0, loading = 0, suspended = false;
    const allowed = () => !document.hidden && !suspended && !reduced.matches && !connection?.saveData;
    function proxy(value, motion = false) {
        try {
            const url = new URL(value, window.location.href);
            return url.origin === window.location.origin && url.pathname === '/proxy' &&
                !url.username && !url.password && (!motion || url.searchParams.get('s') === 'animated') ? url.href : null;
        } catch { return null; }
    }
    function poster(entry, failed = false) {
        if (entry.state === 'loading') { loading--; entry.cleanup(); }
        if (entry.state === 'loading' || entry.state === 'playing') active--;
        entry.state = failed ? 'failed' : 'idle';
        entry.image.src = entry.poster;
    }
    function pump() {
        if (!allowed()) return;
        for (const entry of entries.values()) {
            if (active >= MAX_PLAYING || loading >= MAX_LOADING) break;
            if (!entry.visible || entry.state !== 'idle') continue;
            active++; loading++; entry.state = 'loading';
            const done = success => {
                if (entry.state !== 'loading') return;
                entry.cleanup(); loading--;
                if (success && entry.visible && allowed()) entry.state = 'playing';
                else {
                    active--; entry.state = success ? 'idle' : 'failed'; entry.image.src = entry.poster;
                }
                pump();
            };
            const onload = () => done(true), onerror = () => done(false);
            const timeout = window.setTimeout(() => done(false), 20000);
            entry.cleanup = () => {
                window.clearTimeout(timeout);
                entry.image.removeEventListener('load', onload);
                entry.image.removeEventListener('error', onerror);
            };
            entry.image.addEventListener('load', onload);
            entry.image.addEventListener('error', onerror);
            entry.image.src = entry.motion;
        }
    }
    const observer = new IntersectionObserver(changes => {
        for (const change of changes) {
            const entry = entries.get(change.target);
            if (!entry) continue;
            entry.visible = change.isIntersecting && change.intersectionRatio >= 0.1;
            if (!entry.visible && (entry.state === 'playing' || entry.state === 'loading')) poster(entry);
        }
        pump();
    }, {threshold: [0, 0.1], rootMargin: '0px'});
    function add(image) {
        if (entries.has(image)) return;
        const motion = proxy(image.getAttribute('data-motion'), true);
        const still = proxy(image.getAttribute('src'));
        if (!motion || !still) return;
        entries.set(image, {image, motion, poster: still, visible: false, state: 'idle'});
        observer.observe(image);
    }
    grid.querySelectorAll('img[data-motion]').forEach(add);
    const mutations = new MutationObserver(changes => {
        for (const change of changes) for (const node of change.addedNodes) {
            if (node.nodeType === 1) node.querySelectorAll('img[data-motion]').forEach(add);
        }
    });
    mutations.observe(grid, {childList: true});
    function preferences() {
        if (!allowed()) {
            for (const entry of entries.values()) {
                if (entry.state === 'playing' || entry.state === 'loading') poster(entry);
            }
        } else pump();
    }
    document.addEventListener('visibilitychange', preferences);
    reduced.addEventListener?.('change', preferences);
    connection?.addEventListener?.('change', preferences);
    window.addEventListener('pagehide', () => { suspended = true; preferences(); });
    window.addEventListener('pageshow', () => { suspended = false; preferences(); });
})();
