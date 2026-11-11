const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/payroll.js'), 'utf8');
const adminSource = fs.readFileSync(path.join(__dirname, '../../public/assets/js/admin-data-actions.js'), 'utf8');

function screen(options = {}) {
    const elements = new Map(), requests = [], dialogs = [], timers = [];
    const location = { reloads: 0, reload() { this.reloads++; }, assign(url) { this.url = url; } };
    let now = 0;
    function element(id) {
        if (!elements.has(id)) {
            const e = { length: 1, attrs: {}, values: {}, handlers: {}, label: 'Submit', props: {},
                on(event, fn) { this.handlers[event] = fn; return this; },
                data(key, value) { if (arguments.length === 1) { return this.values[key]; } this.values[key] = value; return this; },
                attr(key) { return this.attrs[key]; }, find(selector) { return element(id + (selector === '[data-form-error]' ? '-error' : '-button')); },
                prop(key, value) { this.props[key] = value; return this; },
                text(value) { if (value === undefined) { return this.label; } this.label = value; return this; }
            };
            e[0] = e; elements.set(id, e);
        }
        return elements.get(id);
    }
    element('#payroll-process').length = options.running ? 1 : 0;
    element('#payroll-process').attrs = { 'data-initial-status': 'queued', 'data-status-url': '/status' };
    function $(value) { if (typeof value === 'function') { value(); return; } return typeof value === 'string' ? element(value) : value; }
    $.ajax = request => requests.push(request);
    vm.runInNewContext(options.admin ? adminSource : source, { jQuery: $, FormData: function () {}, Promise, Date: { now: () => now },
        setTimeout(fn) { timers.push(fn); return timers.length; }, clearTimeout() {},
        window: { location, AppDialog: { alert(title, message) { dialogs.push({ title, message }); return Promise.resolve(); }, confirm() { return Promise.resolve(options.confirm !== false); } } }
    });
    function submit(confirm = false) {
        const form = element('#form'); form.attrs = { action: '/submit', 'data-confirm': confirm ? 'true' : 'false', 'data-confirm-text': confirm ? 'Pulihkan data?' : '' };
        element(options.admin ? '[data-admin-submit]' : '[data-payroll-submit]').handlers.submit.call(form, { preventDefault() {} }); return form;
    }
    return { element, requests, dialogs, timers, location, submit, advance(ms) { now += ms; } };
}

test('payroll prevents double submit and preserves the form after validation failure', () => {
    const s = screen(); const form = s.submit(); s.submit();
    assert.equal(s.requests.length, 1); assert.equal(form.values.busy, true);
    s.requests[0].error({ status: 422, responseJSON: { errors: { file: ['Parameter belum lengkap.'] } } });
    s.requests[0].complete();
    assert.equal(s.dialogs[0].message, 'Parameter belum lengkap.');
    assert.equal(form.values.busy, false); assert.equal(s.element('#form-button').props.disabled, false);
    assert.equal(s.location.reloads, 0);
});

test('recovery and master forms prevent duplicate requests and recover after validation and network failures', () => {
    for (const status of [422, 401, 403, 419, 500, 0]) {
        const s = screen({ admin: true }); const form = s.submit(); s.submit();
        assert.equal(s.requests.length, 1);
        s.requests[0].error({ status, responseJSON: { errors: { name: ['Nama wajib diisi.'] } } });
        s.requests[0].complete();
        assert.equal(form.values.busy, false);
        assert.equal(s.element('#form-button').props.disabled, false);
        assert.equal(s.dialogs.length, 1);
        assert.equal(s.location.reloads, 0);
    }
});

test('cancelling recovery submits nothing and releases its action button', async () => {
    const s = screen({ admin: true, confirm: false }); const form = s.submit(true);
    await Promise.resolve();
    assert.equal(s.requests.length, 0);
    assert.equal(form.values.busy, false);
    assert.equal(s.element('#form-button').props.disabled, false);
});

test('employee setup opens for the selected company and period without starting a calculation', () => {
    const s = screen();
    const prepare = s.element('[data-payroll-prepare]').handlers.click;
    const button = { getAttribute: () => '/payroll/isian', form: { elements: {
        employer_id: { value: '2', reportValidity: () => false },
        period: { value: '2026-10', reportValidity: () => true }
    } } };
    prepare.call(button);
    assert.equal(s.location.url, undefined);
    button.form.elements.employer_id.reportValidity = () => true;
    prepare.call(button);
    assert.equal(s.location.url, '/payroll/isian?employer_id=2&period=2026-10');
    assert.equal(s.requests.length, 0);
});

test('cancelling finalization releases the button without submitting', async () => {
    const s = screen({ confirm: false }); const form = s.submit(true);
    await Promise.resolve();
    assert.equal(s.requests.length, 0); assert.equal(form.values.busy, false);
    assert.equal(s.element('#form-button').props.disabled, false);
});

test('confirmed finalization submits once and redirects only after success feedback', async () => {
    const s = screen(); s.submit(true); s.submit(true); await Promise.resolve();
    assert.equal(s.requests.length, 1);
    s.requests[0].success({ success: true, message: 'Payroll final tersimpan.' });
    assert.equal(s.location.reloads, 0); await Promise.resolve();
    assert.equal(s.location.reloads, 1); assert.equal(s.dialogs[0].message, 'Payroll final tersimpan.');
});

test('session, permission and network failures are visible, with no server internals', () => {
    for (const [status, message] of [[401, 'Sesi login'], [419, 'Sesi login'], [403, 'akses payroll'], [0, 'Koneksi bermasalah'], [500, 'Layanan belum']]) {
        const s = screen(); s.submit();
        s.requests[0].error({ status, responseJSON: { message: 'SQLSTATE private path and bindings' } });
        s.requests[0].complete();
        assert.ok(s.dialogs[0].message.includes(message));
        assert.equal(s.element('#form-button').props.disabled, false);
    }
});

test('polling stops on final status or timeout and refresh can restart it', () => {
    const s = screen({ running: true });
    const state = { running: true, status: 'processing', status_label: 'Diproses', total: 100, success: 50, failed: 0 };
    s.advance(600001); s.requests[0].success({ success: true, data: state }); s.requests[0].complete();
    assert.equal(s.timers.length, 0); assert.match(s.element('#payroll-error').label, /10 menit/);
    s.element('#payroll-refresh').handlers.click();
    assert.equal(s.requests.length, 2);
    s.requests[1].success({ success: true, data: { ...state, running: false, status: 'validation_failed' } });
    s.requests[1].complete();
    assert.equal(s.location.reloads, 1); assert.equal(s.timers.length, 0);
});
