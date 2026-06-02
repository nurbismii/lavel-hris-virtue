(function () {
    document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
        form.addEventListener('submit', function (event) {
            if (form.dataset.swalConfirmed === '1') {
                return;
            }

            const message = form.dataset.confirmSubmit || 'Lanjutkan aksi ini?';

            event.preventDefault();

            window.AppDialog.confirm({
                title: 'Konfirmasi',
                text: message,
                icon: 'warning',
                confirmButtonText: 'Ya, lanjutkan',
                cancelButtonText: 'Batal'
            }).then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                form.dataset.swalConfirmed = '1';

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            });
        });
    });
})();
