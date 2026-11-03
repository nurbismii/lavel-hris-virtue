const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/js/presensi-native-gps.js', 'utf8');

function bridge(response, nativeResult) {
    const listeners = new Map();
    const timers = new Map();
    const calls = [];
    const context = {
        AbortController, TypeError, SyntaxError,
        document: {querySelector: () => ({content: 'test-csrf'})},
        setTimeout: callback => { const id = {}; timers.set(id, callback); return id; },
        clearTimeout: id => timers.delete(id),
        fetch: async (url, options) => { calls.push([url, options]); return typeof response === 'function' ? response() : response; },
        window: {
            addEventListener: (name, callback) => listeners.set(name, callback),
            removeEventListener: name => listeners.delete(name),
            location: {assign: url => {
                assert.ok(url.startsWith('vpeople://gps-proof?'));
                const nonce = new URL(url).searchParams.get('nonce');
                const emit = listeners.get('vpeople:gps');
                emit({detail: {nonce: 'different', proof: {data: 'wrong'}}});
                if (nativeResult) emit({detail: {nonce, ...nativeResult}});
                else for (const timeout of [...timers.values()]) timeout();
            }}
        }
    };
    vm.runInNewContext(source, context);
    return {get: context.window.requestPresensiGps, calls, listeners, timers};
}

(async () => {
    const ok = {ok: true, status: 200, json: async () => ({success: true, data: {nonce: 'nonce', key: 'new'}})};
    const proof = {data: 'signed-fix', signature: 'signature'};
    let test = bridge(ok, {proof});
    assert.equal(await test.get('/gps-challenge', 'attendance-id', 'masuk'), proof);
    assert.deepEqual(JSON.parse(test.calls[0][1].body), {attendance_challenge_id: 'attendance-id', purpose: 'masuk'});
    assert.equal(test.calls[0][1].headers['X-CSRF-TOKEN'], 'test-csrf');
    assert.equal(test.listeners.size, 0);
    assert.equal(test.timers.size, 0);
    test = bridge(ok, {error: 'Matikan Fake GPS'});
    await assert.rejects(test.get('/gps-challenge', 'attendance-id', 'log'), /Fake GPS/);
    assert.equal(test.listeners.size, 0);
    test = bridge(ok, null);
    await assert.rejects(test.get('/gps-challenge', 'attendance-id', 'log'), /lokasi terverifikasi/);
    assert.equal(test.listeners.size, 0);
    for (const status of [401, 419, 429, 403, 503]) {
        test = bridge({ok: false, status, json: async () => ({message: 'Akses ditolak'})});
        await assert.rejects(test.get('/gps-challenge', 'attendance-id', 'log'));
        assert.equal(test.listeners.size, 0);
        assert.equal(test.timers.size, 0);
    }
    test = bridge(() => { throw new TypeError('network'); });
    await assert.rejects(test.get('/gps-challenge', 'attendance-id', 'log'), /Periksa koneksi/);
    console.log('Native GPS proof flow checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
