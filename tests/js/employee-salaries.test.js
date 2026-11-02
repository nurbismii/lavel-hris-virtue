const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/employee-salaries.js'), 'utf8');

function screen(options = {}) {
    const elements = new Map();
    const requests = [];
    const dialogs = [];
    const timers = [];
    const location = { reloads: 0, reload() { this.reloads++; }, assign(url) { this.url = url; } };
    let now = 0;
    function element(id) {
        if (!elements.has(id)) {
            const e = { length: 1, attrs: {}, values: {}, handlers: {}, label: 'Submit', props: {},
                on(event, fn) { this.handlers[event] = fn; return this; },
                data(key, value) { if (arguments.length === 1) { return this.values[key]; } this.values[key] = value; return this; },
                attr(key) { return this.attrs[key]; },
                find() { return element(id + '-button'); },
                prop(key, value) { this.props[key] = value; return this; },
                text(value) { if (value === undefined) { return this.label; } this.label = value; return this; }
            };
            e[0] = e;
            elements.set(id, e);
        }
        return elements.get(id);
    }
    element('#salary-process').length = options.running ? 1 : 0;
    element('#salary-process').attrs = { 'data-initial-status': 'queued', 'data-status-url': '/status' };
    function $(value) { if (typeof value === 'function') { value(); return; } return typeof value === 'string' ? element(value) : value; }
    $.ajax = function (request) { requests.push(request); };
    const context = { jQuery: $, FormData: function () {}, Promise, Date: { now: () => now },
        setTimeout(fn) { timers.push(fn); return timers.length; }, clearTimeout() {},
        window: { location, AppDialog: { alert(title, message) { dialogs.push({ title, message }); return Promise.resolve(); },
            confirm() { return Promise.resolve(options.confirm !== false); } } } };
    vm.runInNewContext(source, context);
    function submit(id = 'salary-upload') {
        const form = element('#' + id);
        form.attrs = { action: '/submit', id };
        element('#salary-upload, #salary-confirm').handlers.submit.call(form, { preventDefault() {} });
        return form;
    }
    return { element, requests, dialogs, timers, location, submit, advance(ms) { now += ms; } };
}

test('upload prevents duplicate submit and restores the button after a failed request', () => {
    const s = screen();
    const form = s.submit();
    s.submit();
    assert.equal(s.requests.length, 1);
    assert.equal(form.values.busy, true);
    s.requests[0].error({ status: 422, responseJSON: { errors: { file: ['Gunakan file XLSX.'] } } });
    s.requests[0].complete();
    assert.equal(s.dialogs[0].message, 'Gunakan file XLSX.');
    assert.equal(form.values.busy, false);
    assert.equal(s.element('#salary-upload-button').props.disabled, false);
});

test('cancelling salary confirmation sends no request and releases the form', async () => {
    const s = screen({ confirm: false });
    const form = s.submit('salary-confirm');
    await Promise.resolve();
    assert.equal(s.requests.length, 0);
    assert.equal(form.values.busy, false);
});

test('session, permission and network failures are visible to HR', () => {
    for (const [status, message] of [[401, 'Sesi login'], [419, 'Sesi login'], [403, 'tidak memiliki akses'], [0, 'Koneksi bermasalah'], [500, 'Layanan belum']]) {
        const s = screen();
        s.submit();
        s.requests[0].error({ status });
        assert.ok(s.dialogs[0].message.includes(message));
        s.requests[0].complete();
        assert.equal(s.element('#salary-upload-button').props.disabled, false);
    }
});

test('polling stops after ten minutes or completion and refresh can restart it', () => {
    const s = screen({ running: true });
    const state = { running: true, status: 'processing', status_label: 'Diproses', total: 100, success: 0, failed: 0, phase: 'preview' };
    s.advance(600001);
    s.requests[0].success({ success: true, data: state });
    s.requests[0].complete();
    assert.equal(s.timers.length, 0);
    assert.match(s.element('#salary-error').label, /10 menit/);
    s.element('#salary-refresh').handlers.click();
    assert.equal(s.requests.length, 2);
    s.requests[1].success({ success: true, data: { ...state, running: false, status: 'awaiting_confirmation' } });
    s.requests[1].complete();
    assert.equal(s.location.reloads, 1);
    assert.equal(s.timers.length, 0);
});

test('employee lookup reports request errors and ignores cancelled searches', () => {
    let config;
    const messages = [];
    const select = { data(key) { return key === 'select2' ? false : '/employee-search'; },
        select2(value) { config = value; } };
    const $ = value => {
        if (typeof value === 'function') { value($); return; }
        return typeof value === 'string' ? { each(fn) { fn.call(select); } } : value;
    };
    $.fn = { select2() {} };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/overtime-order-form.js'), 'utf8'), {
        jQuery: $, window: { jQuery: $, AppDialog: { alert(title, message) { messages.push(message); } } }
    });
    config.ajax.error({ status: 0 }, 'abort');
    assert.equal(messages.length, 0);
    config.ajax.error({ status: 419 }, 'error');
    assert.match(messages[0], /Sesi login/);
    config.ajax.error({ status: 0 }, 'error');
    assert.match(messages[1], /Koneksi bermasalah/);
});
