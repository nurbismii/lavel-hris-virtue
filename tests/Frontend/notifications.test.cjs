const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../../resources/js/realtime-notifications.js'), 'utf8');
const payload = {unread_count: 1, items: [{title: '<img onerror="bad">', message: 'Pesan & kabar', read_url: '/notif/test/baca', unread: true}]};
const flush = () => new Promise(resolve => setImmediate(resolve));
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return {promise, resolve}; };

function start({get = async () => ({data: payload}), remove, confirm = async () => true} = {}) {
    const elements = {};
    for (const id of ['notifBadge', 'notifList', 'panel', 'refresh', 'clear', 'status']) {
        elements[id] = {textContent: id === 'clear' ? 'Clear' : '', innerHTML: '', disabled: false, hidden: true,
            dataset: {url: '/notif', loadingText: 'Menghapus...'}, handlers: {}, attributes: {},
            classList: {toggle(name, enabled) { this[name] = enabled; }},
            setAttribute(name, value) { this.attributes[name] = value; },
            addEventListener(event, callback) { this.handlers[event] = callback; }};
    }
    const selectors = {'[data-native-notifications]': 'panel', '[data-notification-refresh]': 'refresh', '[data-notification-clear]': 'clear', '[data-notification-status]': 'status'};
    let ready;
    let deleted = 0;
    const alerts = [];
    vm.runInNewContext(source, {
        document: {hidden: false, getElementById: id => elements[id], querySelector: selector => elements[selectors[selector]],
            addEventListener: (event, callback) => { if (event === 'DOMContentLoaded') ready = callback; }},
        window: {AppRealtimeNotifications: {latestUrl: '/notifications/latest', labels: {empty: 'Kosong', load_failed: 'Gagal memuat'}},
            axios: {get, delete: async url => { assert.equal(url, '/notif'); deleted++; return remove ? remove() : {data: {success: true, message: 'Dihapus'}}; }},
            AppDialog: {confirm, alert: async (...args) => { alerts.push(args); }}, setInterval() {}}
    });
    ready();
    return {elements, alerts, deleted: () => deleted};
}

(async () => {
    let current = payload;
    const app = start({get: async () => ({data: current}), remove: async () => {
        current = {unread_count: 0, items: []}; return {data: {success: true, message: 'Dihapus'}};
    }});
    await flush();
    assert.equal(app.elements.notifBadge.textContent, 1);
    assert.match(app.elements.notifList.innerHTML, /&lt;img/);
    assert.doesNotMatch(app.elements.notifList.innerHTML, /<img/);
    assert.equal(app.elements.clear.disabled, false);
    await app.elements.clear.handlers.click();
    await flush();
    assert.equal(app.deleted(), 1);
    assert.equal(app.elements.clear.disabled, true);
    assert.equal(app.elements.clear.textContent, 'Clear');
    assert.equal(app.elements.notifBadge.classList['d-none'], true);
    assert.match(app.elements.notifList.innerHTML, /Kosong/);
    assert.equal(app.alerts[0][2], 'success');

    const cancelled = start({confirm: async () => false});
    await flush();
    await cancelled.elements.clear.handlers.click();
    await flush();
    assert.equal(cancelled.deleted(), 0);
    assert.equal(cancelled.elements.clear.disabled, false);

    const pending = deferred();
    let reads = 0;
    const race = start({get: () => ++reads === 2 ? pending.promise : Promise.resolve({data: payload})});
    await flush();
    race.elements.refresh.handlers.click();
    const clearing = race.elements.clear.handlers.click();
    await race.elements.clear.handlers.click();
    await flush();
    assert.equal(race.deleted(), 0, 'Clear waits for the older fetch');
    assert.equal(race.elements.clear.disabled, true);
    pending.resolve({data: payload});
    await clearing;
    assert.equal(race.deleted(), 1, 'Duplicate clicks send one delete');

    for (const [status, expected] of [[401, /login ulang/], [419, /login ulang/], [403, /akses/], [422, /Data salah/], [500, /gagal dibersihkan/], [0, /jaringan/]]) {
        const failed = start({remove: async () => { throw status ? {response: {status, data: {message: 'Internal detail', errors: {field: ['Data salah']}}}} : {}; }});
        await flush();
        await failed.elements.clear.handlers.click();
        await flush();
        assert.match(failed.alerts[0][1], expected);
        assert.equal(failed.elements.clear.disabled, false);
        assert.equal(failed.elements.refresh.disabled, false);
    }

    const offline = start({get: async () => { throw {}; }});
    await flush();
    assert.equal(offline.elements.status.hidden, false);
    assert.match(offline.elements.status.textContent, /jaringan/);
    assert.equal(offline.elements.notifList.innerHTML, '');
    assert.equal(offline.elements.notifList.attributes['aria-busy'], 'false');
    assert.equal(offline.elements.refresh.disabled, false);
    console.log('Notification rendering, clear, cancellation, race and error checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
