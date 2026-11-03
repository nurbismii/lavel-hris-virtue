const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const blade = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');
const hint = blade.match(/<script id="presensiIosBrowserHint">([\s\S]*?)<\/script>/)[1];

function open(navigator, url = 'https://example.test/presensi?month=11') {
    const redirects = [];
    vm.runInNewContext(hint, {navigator, URL, window: {location: {href: url, replace: value => redirects.push(value)}}});
    return redirects;
}

const ipad = {platform: 'MacIntel', maxTouchPoints: 5, userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) Safari/605.1.15'};
const redirects = open(ipad);
assert.equal(redirects.length, 1);
assert.equal(redirects[0], 'https://example.test/presensi?month=11&ios_browser=1');
assert.equal(open(ipad, redirects[0]).length, 0);
for (const device of [
    {...ipad, maxTouchPoints: 0}, {...ipad, maxTouchPoints: undefined},
    {...ipad, userAgent: 'iPhone'}, {...ipad, userAgent: 'iPad'},
    {...ipad, userAgent: 'Android'}, {...ipad, userAgent: 'iPhone VPEOPLE_APP/1.0.0'},
    {...ipad, platform: 'Win32'},
]) assert.equal(open(device).length, 0);

// Exercise the actual log and submit branches without launching a native bridge on iOS.
const logRequest = blade.match(/(\(supportsIosBrowserGps \? Promise\.resolve\(null\)[^\n]+)\.then\(proof => fetch/)[1];
const submitRequest = blade.match(/const proof = (supportsIosBrowserGps \? null[^;]+);/)[1];
(async () => {
    for (const ios of [true, false]) {
        const calls = [];
        const proof = {data: 'signed-coordinates'};
        const context = {
            supportsIosBrowserGps: ios, gpsChallengeUrl: '/gps-challenge', attendanceChallenge: {id: 'challenge'}, type: 'masuk',
            window: {requestPresensiGps: (...args) => { calls.push(args); return Promise.resolve(proof); }},
        };
        assert.equal(await vm.runInNewContext(logRequest, context), ios ? null : proof);
        assert.equal(await vm.runInNewContext('(async () => (' + submitRequest + '))()', context), ios ? null : proof);
        assert.equal(calls.length, ios ? 0 : 2);
        if (!ios) assert.deepEqual(calls.map(call => call[2]), ['log', 'masuk']);
    }
    console.log('iOS browser attendance checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
