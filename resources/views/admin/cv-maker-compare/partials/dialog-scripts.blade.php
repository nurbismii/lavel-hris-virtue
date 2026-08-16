<script>
    window.CvMakerDialog = window.CvMakerDialog || {
        fire: function(options) {
            const config = options || {};
            const appDialog = window.AppDialog;

            if (config.showCancelButton) {
                if (appDialog && typeof appDialog.confirm === 'function') {
                    return appDialog.confirm({
                        title: config.title || 'Konfirmasi',
                        text: config.text || '',
                        icon: config.icon || 'warning',
                        confirmButtonText: config.confirmButtonText || 'Lanjutkan',
                        cancelButtonText: config.cancelButtonText || 'Batal',
                        dangerMode: config.icon === 'warning' || config.icon === 'error'
                    }).then(function(confirmed) {
                        return { isConfirmed: !!confirmed };
                    });
                }

                return Promise.resolve({
                    isConfirmed: window.confirm(config.text || config.title || 'Lanjutkan proses?')
                });
            }

            if (appDialog && typeof appDialog.alert === 'function') {
                return Promise.resolve(appDialog.alert(
                    config.title || '',
                    config.text || '',
                    config.icon || 'info'
                )).then(function() {
                    return { isConfirmed: true };
                });
            }

            window.alert(config.text || config.title || 'Proses selesai.');
            return Promise.resolve({ isConfirmed: true });
        }
    };
</script>
