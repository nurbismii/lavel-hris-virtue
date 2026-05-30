(function(window, document, $) {
    'use strict';

    if (!window || !document) {
        return;
    }

    const ACTION_SELECTOR = [
        'button',
        'input[type="submit"]',
        'input[type="button"]',
        'input[type="reset"]',
        'a.btn[href]',
        '[data-action-loading]',
        '[data-action-button]'
    ].join(',');

    const DISABLE_SELECTOR = [
        'button:not([data-action-loading-ignore])',
        'input[type="submit"]:not([data-action-loading-ignore])',
        'input[type="button"]:not([data-action-loading-ignore])',
        'input[type="reset"]:not([data-action-loading-ignore])',
        'a.btn[href]:not([data-action-loading-ignore])',
        '[data-action-loading]:not([data-action-loading-ignore])',
        '[data-action-button]:not([data-action-loading-ignore])'
    ].join(',');

    const IGNORE_CONTAINER_SELECTOR = [
        '.swal-modal',
        '.swal-overlay',
        '.swal2-container',
        '[data-action-loading-scope="ignore"]'
    ].join(',');

    const CONTROL_TAGS = ['BUTTON', 'INPUT', 'A'];
    const RECENT_ACTION_WINDOW_MS = 3000;
    const NAVIGATION_FALLBACK_MS = 12000;

    const nativeFormSubmit = window.HTMLFormElement && window.HTMLFormElement.prototype.submit;
    const nativeFetch = window.fetch;
    const activeTokens = new Set();
    let activeTrigger = null;
    let lastAction = null;
    let tokenCounter = 0;

    function now() {
        return Date.now();
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isElement(value) {
        return value && value.nodeType === 1;
    }

    function isIgnored(element) {
        if (!isElement(element)) {
            return true;
        }

        return element.hasAttribute('data-action-loading-ignore') ||
            Boolean(element.closest(IGNORE_CONTAINER_SELECTOR));
    }

    function normalizeControl(element) {
        if (!isElement(element)) {
            return null;
        }

        const control = element.matches(ACTION_SELECTOR) ? element : element.closest(ACTION_SELECTOR);

        if (!control || isIgnored(control)) {
            return null;
        }

        return control;
    }

    function getDefaultSubmitter(form) {
        if (!(form instanceof HTMLFormElement)) {
            return null;
        }

        return form.querySelector(
            'button[type="submit"]:not([disabled]), button:not([type]):not([disabled]), input[type="submit"]:not([disabled])'
        );
    }

    function getSubmitter(event, form) {
        const submitter = event && event.submitter ? event.submitter : null;

        if (submitter) {
            return submitter;
        }

        const activeElement = document.activeElement;

        if (activeElement && form && activeElement.form === form) {
            return activeElement;
        }

        return getDefaultSubmitter(form);
    }

    function ensureSubmitterValue(form, submitter) {
        if (!(form instanceof HTMLFormElement) || !submitter || !submitter.name) {
            return;
        }

        const tagName = submitter.tagName;
        const type = (submitter.getAttribute('type') || '').toLowerCase();
        const isSubmitControl = tagName === 'BUTTON' ||
            (tagName === 'INPUT' && ['submit', 'image'].includes(type));

        if (!isSubmitControl) {
            return;
        }

        let field = Array.prototype.find.call(
            form.querySelectorAll('input[type="hidden"][data-action-state-submitter="1"]'),
            function(input) {
                return input.name === submitter.name;
            }
        );

        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = submitter.name;
            field.setAttribute('data-action-state-submitter', '1');
            form.appendChild(field);
        }

        field.value = submitter.value;
    }

    function shouldSkipForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return true;
        }

        if (form.hasAttribute('data-action-loading-ignore')) {
            return true;
        }

        const target = (form.getAttribute('target') || '').toLowerCase();

        return target && target !== '_self';
    }

    function rememberLastAction(element) {
        const control = normalizeControl(element);

        if (!control) {
            return;
        }

        lastAction = {
            element: control,
            time: now()
        };
    }

    function getRecentAction() {
        if (!lastAction || !lastAction.element || !document.documentElement.contains(lastAction.element)) {
            return null;
        }

        if (now() - lastAction.time > RECENT_ACTION_WINDOW_MS) {
            return null;
        }

        return normalizeControl(lastAction.element);
    }

    function isBusy() {
        return activeTokens.size > 0;
    }

    function getLoadingText(trigger, options) {
        if (options && options.loadingText) {
            return options.loadingText;
        }

        if (trigger && trigger.dataset) {
            return trigger.dataset.loadingText ||
                trigger.dataset.actionLoadingText ||
                trigger.getAttribute('aria-label') ||
                'Memproses...';
        }

        return 'Memproses...';
    }

    function rememberControlState(control) {
        if (control.dataset.appActionStateStored === '1') {
            return;
        }

        control.dataset.appActionStateStored = '1';
        control.dataset.appActionWasDisabled = control.disabled ? '1' : '0';
        control.dataset.appActionHadDisabledClass = control.classList.contains('disabled') ? '1' : '0';
        control.dataset.appActionHadPeNone = control.classList.contains('pe-none') ? '1' : '0';
        control.dataset.appActionAriaDisabled = control.getAttribute('aria-disabled') || '';
        control.dataset.appActionTabindex = control.getAttribute('tabindex') || '';
        control.dataset.appActionPointerEvents = control.style.pointerEvents || '';

        if (control.tagName === 'INPUT') {
            control.dataset.appActionOriginalValue = control.value;
        } else if (CONTROL_TAGS.includes(control.tagName)) {
            control.dataset.appActionOriginalHtml = control.innerHTML;
        }
    }

    function disableControl(control) {
        if (!isElement(control) || isIgnored(control)) {
            return;
        }

        rememberControlState(control);

        if ('disabled' in control) {
            control.disabled = true;
        }

        if (control.tagName === 'A') {
            control.classList.add('disabled', 'pe-none');
            control.setAttribute('aria-disabled', 'true');
            control.setAttribute('tabindex', '-1');
            control.style.pointerEvents = 'none';
        }
    }

    function restoreControl(control) {
        if (!isElement(control) || control.dataset.appActionStateStored !== '1') {
            return;
        }

        if ('disabled' in control && control.dataset.appActionWasDisabled !== '1') {
            control.disabled = false;
        }

        if (control.tagName === 'A') {
            if (control.dataset.appActionHadDisabledClass !== '1') {
                control.classList.remove('disabled');
            }

            if (control.dataset.appActionHadPeNone !== '1') {
                control.classList.remove('pe-none');
            }

            if (control.dataset.appActionAriaDisabled) {
                control.setAttribute('aria-disabled', control.dataset.appActionAriaDisabled);
            } else {
                control.removeAttribute('aria-disabled');
            }

            if (control.dataset.appActionTabindex) {
                control.setAttribute('tabindex', control.dataset.appActionTabindex);
            } else {
                control.removeAttribute('tabindex');
            }

            control.style.pointerEvents = control.dataset.appActionPointerEvents || '';
        }

        restoreLoadingContent(control);

        delete control.dataset.appActionStateStored;
        delete control.dataset.appActionWasDisabled;
        delete control.dataset.appActionHadDisabledClass;
        delete control.dataset.appActionHadPeNone;
        delete control.dataset.appActionAriaDisabled;
        delete control.dataset.appActionTabindex;
        delete control.dataset.appActionPointerEvents;
        delete control.dataset.appActionOriginalValue;
        delete control.dataset.appActionOriginalHtml;
    }

    function setLoadingContent(control, text) {
        if (!control || isIgnored(control)) {
            return;
        }

        rememberControlState(control);

        const safeText = escapeHtml(text || 'Memproses...');

        if (control.tagName === 'INPUT') {
            control.value = text || 'Memproses...';
            return;
        }

        if (CONTROL_TAGS.includes(control.tagName)) {
            control.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + safeText;
        }
    }

    function restoreLoadingContent(control) {
        if (!control || control.dataset.appActionStateStored !== '1') {
            return;
        }

        if (control.tagName === 'INPUT' && Object.prototype.hasOwnProperty.call(control.dataset, 'appActionOriginalValue')) {
            control.value = control.dataset.appActionOriginalValue;
            return;
        }

        if (Object.prototype.hasOwnProperty.call(control.dataset, 'appActionOriginalHtml')) {
            control.innerHTML = control.dataset.appActionOriginalHtml;
        }
    }

    function disableActionControls() {
        document.querySelectorAll(DISABLE_SELECTOR).forEach(disableControl);
    }

    function restoreActionControls() {
        document.querySelectorAll('[data-app-action-state-stored="1"]').forEach(restoreControl);
    }

    function start(trigger, options) {
        const control = normalizeControl(trigger);
        const token = {
            id: ++tokenCounter,
            trigger: control,
            timer: null
        };

        activeTokens.add(token);

        if (activeTokens.size === 1) {
            activeTrigger = control;
            document.body.classList.add('app-action-busy');
            document.body.setAttribute('aria-busy', 'true');

            disableActionControls();

            if (control) {
                setLoadingContent(control, getLoadingText(control, options));
                disableControl(control);
            }
        }

        if (options && options.unlockAfterMs) {
            token.timer = window.setTimeout(function() {
                finish(token);
            }, options.unlockAfterMs);
        }

        return token;
    }

    function finish(token) {
        if (!token || !activeTokens.has(token)) {
            return;
        }

        if (token.timer) {
            window.clearTimeout(token.timer);
            token.timer = null;
        }

        activeTokens.delete(token);

        if (activeTokens.size > 0) {
            return;
        }

        restoreActionControls();
        document.body.classList.remove('app-action-busy');
        document.body.removeAttribute('aria-busy');
        activeTrigger = null;
    }

    function finishAll() {
        Array.from(activeTokens).forEach(finish);
    }

    function prepareProgrammaticSubmit(form, submitter, options) {
        if (shouldSkipForm(form)) {
            return null;
        }

        const trigger = submitter || getDefaultSubmitter(form);
        ensureSubmitterValue(form, trigger);
        rememberLastAction(trigger || form);

        return start(trigger, options || {
            loadingText: form.dataset.loadingText || 'Memproses...'
        });
    }

    function isNavigationAction(anchor) {
        if (!anchor || anchor.tagName !== 'A') {
            return false;
        }

        if (anchor.hasAttribute('download') || anchor.hasAttribute('data-bs-toggle') || anchor.hasAttribute('data-toggle')) {
            return false;
        }

        const href = anchor.getAttribute('href') || '';

        if (!href || href === '#' || href.indexOf('javascript:') === 0) {
            return false;
        }

        const target = (anchor.getAttribute('target') || '').toLowerCase();

        return !target || target === '_self';
    }

    document.addEventListener('click', function(event) {
        const control = normalizeControl(event.target);

        if (!control) {
            return;
        }

        if (isBusy() && control !== activeTrigger) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        rememberLastAction(control);

        if (isNavigationAction(control)) {
            start(control, {
                loadingText: getLoadingText(control),
                unlockAfterMs: NAVIGATION_FALLBACK_MS
            });
        }
    }, true);

    document.addEventListener('submit', function(event) {
        const form = event.target;

        if (event.defaultPrevented || shouldSkipForm(form)) {
            return;
        }

        const submitter = getSubmitter(event, form);
        ensureSubmitterValue(form, submitter);
        rememberLastAction(submitter || form);

        const token = start(submitter, {
            loadingText: submitter && submitter.dataset.loadingText ?
                submitter.dataset.loadingText :
                (form.dataset.loadingText || 'Memproses...')
        });

        window.setTimeout(function() {
            if (event.defaultPrevented) {
                finish(token);
            }
        }, 80);
    });

    if (nativeFormSubmit && !window.__appActionFormSubmitPatched) {
        window.HTMLFormElement.prototype.submit = function() {
            prepareProgrammaticSubmit(this);

            return nativeFormSubmit.apply(this, arguments);
        };

        window.__appActionFormSubmitPatched = true;
    }

    if ($ && $.ajaxSetup) {
        $(document).ajaxSend(function(event, jqXHR) {
            const trigger = getRecentAction();

            if (!trigger) {
                return;
            }

            const token = start(trigger, {
                loadingText: getLoadingText(trigger)
            });

            if (jqXHR && typeof jqXHR.always === 'function') {
                jqXHR.always(function() {
                    finish(token);
                });
            }
        });
    }

    if (typeof nativeFetch === 'function' && !window.__appActionFetchPatched) {
        window.fetch = function() {
            const trigger = getRecentAction();
            const token = trigger ? start(trigger, {
                loadingText: getLoadingText(trigger)
            }) : null;

            try {
                const response = nativeFetch.apply(this, arguments);

                if (token && response && typeof response.finally === 'function') {
                    return response.finally(function() {
                        finish(token);
                    });
                }

                if (token && response && typeof response.then === 'function') {
                    return response.then(function(value) {
                        finish(token);
                        return value;
                    }, function(error) {
                        finish(token);
                        throw error;
                    });
                }

                if (token) {
                    finish(token);
                }

                return response;
            } catch (error) {
                if (token) {
                    finish(token);
                }

                throw error;
            }
        };

        window.__appActionFetchPatched = true;
    }

    window.AppActionState = {
        finish: finish,
        finishAll: finishAll,
        isBusy: isBusy,
        nativeFormSubmit: nativeFormSubmit,
        prepareProgrammaticSubmit: prepareProgrammaticSubmit,
        rememberLastAction: rememberLastAction,
        start: start
    };
})(window, document, window.jQuery);
