const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('normal forms lock once and recover after browser back navigation', async () => {
    const events = {};
    const button = { dataset: { loadingText: 'Menyimpan...' }, innerHTML: 'Simpan', disabled: false };
    const form = {
        dataset: {}, matches: () => true,
        setAttribute(name, value) { this[name] = value; },
        removeAttribute(name) { delete this[name]; },
        querySelectorAll: () => [button],
    };
    const document = {
        addEventListener(name, callback) { (events[name] ||= []).push(callback); },
        getElementById: () => null, querySelector: () => null, querySelectorAll: () => [form],
    };
    const window = { addEventListener(name, callback) { events[name] = [callback]; }, matchMedia: () => ({ matches: false }) };
    const context = vm.createContext({ document, window, HTMLFormElement: class {} });
    vm.runInContext(fs.readFileSync('public/assets/js/app-layout.js', 'utf8'), context);
    const event = { target: form, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
    const submit = async (value) => { for (const callback of events.submit) { await callback(value); } };
    await submit(event);
    assert.equal(button.disabled, true);
    assert.equal(button.textContent, 'Menyimpan...');
    assert.equal(form['aria-busy'], 'true');
    assert.equal(event.defaultPrevented, false);
    const duplicate = { ...event };
    await submit(duplicate);
    assert.equal(duplicate.defaultPrevented, true);
    events.pageshow[0]();
    assert.equal(button.disabled, false);
    assert.equal(button.innerHTML, 'Simpan');
    assert.equal(form.dataset.submitting, undefined);
    assert.equal(form['aria-busy'], undefined);
    await submit({ ...event, defaultPrevented: true });
    assert.equal(button.disabled, false);
});

test('GPS permission, unavailable and timeout errors invalidate previous location evidence', () => {
    const source = fs.readFileSync('resources/views/user/presensi/index.blade.php', 'utf8');
    const handler = source.match(/    function handleGpsError\(error\) \{[\s\S]*?\n    \}/)[0];
    for (const [code, expected] of [[1, /Izin lokasi ditolak/], [2, /Lokasi belum tersedia/], [3, /batas waktu/], [null, /Periksa GPS/]]) {
        let feedback;
        let locked = false;
        const context = vm.createContext({
            gpsReady: true, gpsEvidenceReady: true, stableStartTime: 123,
            positionHistory: [{ lat: 1 }], lastLat: 1, lastLong: 1, lastTime: 123,
            document: { getElementById: () => ({}) },
            updateFaceLiveLocation: (...args) => { feedback = args; },
            updateAttendanceButtonState: () => { locked = !context.gpsReady || !context.gpsEvidenceReady; },
        });
        vm.runInContext(handler, context);
        context.handleGpsError(code ? { code } : null);
        assert.equal(locked, true);
        assert.equal(context.gpsEvidenceReady, false);
        assert.equal(context.stableStartTime, null);
        assert.equal(context.positionHistory.length, 0);
        assert.equal(context.lastTime, null);
        assert.match(feedback[2], expected);
    }
});
