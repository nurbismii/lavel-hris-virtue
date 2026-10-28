(function () {
    const form = document.getElementById('izin-form');
    if (!form) { return; }
    const categories = form.querySelectorAll('[name="tipe_izin"]');
    function syncCategories() {
        const paid = form.querySelector('[name="tipe"]:checked');
        const show = paid && paid.value === 'PAID';
        form.querySelector('#paidOptions').classList.toggle('izin-paid-options-hidden', !show);
        categories.forEach(function (input) {
            input.disabled = !show;
            input.required = !!show;
            if (!show) { input.checked = false; }
        });
    }
    form.querySelectorAll('[name="tipe"]').forEach(function (input) { input.addEventListener('change', syncCategories); });
    syncCategories();
}());
