(function () {
    'use strict';
    function initialize() {
        const workspace = document.querySelector('[data-cv-workspace]');
        if (!workspace) return;
        const nav = workspace.querySelector('.cv-workspace-nav');
        if (!nav) return;
        const panes = Array.from(workspace.querySelectorAll('[data-cv-pane]'));
        const tabs = Array.from(nav.querySelectorAll('[data-cv-tab]')).filter(function (tab) {
            const exists = panes.some(function (pane) { return pane.dataset.cvPane === tab.dataset.cvTab; });
            tab.hidden = !exists;
            return exists;
        });
        if (!tabs.length) return;
        nav.hidden = false;
        nav.setAttribute('role', 'tablist');
        tabs.forEach(function (tab) { tab.setAttribute('role', 'tab'); });
        panes.forEach(function (pane) { pane.setAttribute('role', 'tabpanel'); pane.tabIndex = 0; });
        function activate(key, updateHash) {
            if (!tabs.some(function (tab) { return tab.dataset.cvTab === key; })) key = tabs[0].dataset.cvTab;
            tabs.forEach(function (tab) {
                const active = tab.dataset.cvTab === key;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
            });
            panes.forEach(function (pane) { pane.hidden = pane.dataset.cvPane !== key; });
            if (updateHash) window.history.replaceState(null, '', '#cv-' + key);
            if (key === 'employees' && window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#cvMakerCompareTable')) {
                jQuery('#cvMakerCompareTable').DataTable().columns.adjust();
            }
        }
        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () { activate(tab.dataset.cvTab, true); });
            tab.addEventListener('keydown', function (event) {
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                if (next === undefined) return;
                event.preventDefault();
                tabs[next].focus();
                activate(tabs[next].dataset.cvTab, true);
            });
        });
        function restore() { activate(window.location.hash.replace(/^#cv-/, ''), false); }
        window.addEventListener('hashchange', restore);
        restore();
        const count = workspace.querySelector('[data-cv-filter-count]');
        function updateFilterCount() {
            if (!count) return;
            const active = Array.from(workspace.querySelectorAll('.cv-compare-filter-panel select')).filter(function (select) {
                return Array.from(select.selectedOptions).some(function (option) { return option.value !== ''; });
            }).length;
            count.textContent = active ? active + ' filter aktif' : 'Semua data';
        }
        if (window.jQuery) {
            jQuery(workspace).on('change', '.cv-compare-filter-panel select', updateFilterCount);
            jQuery('#cvMakerCompareTable').on('draw.dt', updateFilterCount);
        }
        updateFilterCount();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}());
