const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const guard = fs.readFileSync(path.join(__dirname, '../../public/assets/js/mobile-app-guard.js'), 'utf8');
function redirects(navigator, iosRequired = false) {
    let destination;
    vm.runInNewContext(guard, {
        navigator,
        document: { currentScript: { dataset: { downloadUrl: '/download-app', iosRequired: iosRequired ? '1' : '0' } } },
        window: { location: { replace: url => { destination = url; } } }
    });
    return destination;
}
for (const userAgent of ['Android', 'Windows Phone', 'Android FAKE_VPEOPLE_APP']) {
    assert.equal(redirects({userAgent}), '/download-app');
}
for (const userAgent of ['Windows NT 10.0', 'Macintosh', 'X11 Linux', 'iPhone', 'iPad', 'iPod', 'Android VPEOPLE_APP', 'iPhone VPEOPLE_APP/1.0.0']) {
    assert.equal(redirects({userAgent}), undefined);
}
assert.equal(redirects({userAgent: 'Macintosh', platform: 'MacIntel', maxTouchPoints: 5}), undefined);
assert.equal(redirects({userAgent: 'iPhone'}, true), '/download-app');
assert.equal(redirects({userAgent: 'Macintosh', platform: 'MacIntel', maxTouchPoints: 5}, true), '/download-app');
assert.equal(redirects({userAgent: 'Windows', platform: 'Win32', maxTouchPoints: 10}), undefined);
assert.equal(redirects({userAgent: 'Chromium', userAgentData: {mobile: true}}), '/download-app');

const page = fs.readFileSync(path.join(__dirname, '../../public/assets/js/download-app.js'), 'utf8');
for (const device of ['Android', 'iPhone', 'Macintosh', 'Windows']) {
    const sections = ['android', 'ios'].map(platform => ({dataset: {platform}, hidden: false}));
    const desktop = {hidden: false};
    const status = {dataset: {opening: 'opening', downloading: 'downloading'}};
    let open, download;
    const elements = {
        downloadStatus: status, desktopLogin: desktop,
        openAppButton: {hidden: false, addEventListener: (event, callback) => { open = callback; }}
    };
    vm.runInNewContext(page, {
        navigator: {userAgent: device, platform: device === 'Macintosh' ? 'MacIntel' : '', maxTouchPoints: 5},
        document: {
            body: {dataset: {iosRequired: '0'}},
            getElementById: id => elements[id],
            querySelectorAll: selector => selector === '[data-platform]' ? sections : [{addEventListener: (event, callback) => { download = callback; }}]
        }
    });
    assert.equal(desktop.hidden, device !== 'Windows');
    assert.equal(sections[0].hidden, device === 'iPhone' || device === 'Macintosh');
    assert.equal(sections[1].hidden, device === 'Android');
    assert.equal(elements.openAppButton.hidden, device === 'iPhone' || device === 'Macintosh');
    open(); assert.equal(status.textContent, 'opening');
    download(); assert.equal(status.textContent, 'downloading');
}
console.log('Mobile routing and download UI checks passed.');
