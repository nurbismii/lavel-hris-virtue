const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/roster-schedule-import.js'), 'utf8');

function setup({ confirm = false, upload = false } = {}) {
    const requests = [], alerts = [], timers = [];
    let confirmationCount = 0;
    function element(length = 1) {
        return {
            length, values: {}, events: {}, hidden: false, textValue: '',
            data(key, value) { if (arguments.length === 2) { this.values[key] = value; return this; } return this.values[key]; },
            prop(key, value) { if (arguments.length === 2) { this[key] = value; return this; } return !!this[key]; },
            html(value) { if (arguments.length) { this.htmlValue = value; return this; } return this.htmlValue || 'Proses'; },
            text(value) { this.textValue = value; return this; },
            attr() { return '/local-test'; }, serialize() { return '_token=test'; },
            addClass(value) { if (value === 'd-none') { this.hidden = true; } return this; },
            removeClass() { return this; }, on(name, fn) { this.events[name] = fn; return this; },
            find() { return button; }
        };
    }
    const button = element(), page = element(), form = element(upload ? 1 : 0), confirmForm = element(upload ? 0 : 1), status = element();
    page.data('terminal', '1');
    const elements = { '#roster-import-page': page, '#roster-import-form': form, '#roster-import-confirm-form': confirmForm, '[data-import-status]': status };
    const $ = selector => elements[selector] || element();
    $.each = (items, callback) => Object.entries(items).forEach(([key, value]) => callback(key, value));
    $.ajax = options => {
        const callbacks = {};
        const request = { options, done(fn) { callbacks.done = fn; return this; }, fail(fn) { callbacks.fail = fn; return this; }, always(fn) { callbacks.always = fn; return this; },
            resolve(value) { callbacks.done?.(value); callbacks.always?.(); }, reject(value) { callbacks.fail?.(value); callbacks.always?.(); } };
        requests.push(request);
        return request;
    };
    vm.runInNewContext(source, {
        jQuery: $, Date, FormData: class {},
        window: { AppDialog: { alert: (...args) => alerts.push(args), confirm: () => { confirmationCount++; return Promise.resolve(confirm); } },
            setTimeout: (fn, delay) => { timers.push({ fn, delay }); return timers.length; }, clearTimeout() {}, location: { assign() {} } }
    });
    return { requests, alerts, timers, button, confirmForm, status, confirmationCount: () => confirmationCount,
        submit() { (upload ? form : confirmForm).events.submit({ preventDefault() {} }); } };
}

test('cancelled confirmation sends no import request and repeated clicks open one dialog', async () => {
    const ui = setup();
    ui.submit(); ui.submit();
    await new Promise(setImmediate);
    assert.equal(ui.confirmationCount(), 1);
    assert.equal(ui.requests.length, 0);
    assert.equal(ui.button.prop('disabled'), false);
});

test('confirmed import queues once, hides confirmation and starts status polling', async () => {
    const ui = setup({ confirm: true });
    ui.submit();
    await new Promise(setImmediate);
    ui.submit();
    assert.equal(ui.requests.length, 1);
    ui.requests[0].resolve({ success: true, message: 'Masuk antrean' });
    assert.equal(ui.confirmForm.hidden, true);
    assert.equal(ui.status.textValue, 'Masuk antrean');
    assert.equal(ui.timers[0].delay, 0);
});

test('upload session failure is visible through AppDialog without SweetAlert2 and restores button', () => {
    const ui = setup({ upload: true });
    ui.submit();
    ui.requests[0].reject({ status: 419 });
    assert.match(ui.alerts[0][1], /Sesi login berakhir/);
    assert.equal(ui.button.prop('disabled'), false);
});
