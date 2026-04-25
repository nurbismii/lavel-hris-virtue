(function () {
    document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
        form.addEventListener('submit', function (event) {
            const message = form.dataset.confirmSubmit || 'Lanjutkan aksi ini?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();
