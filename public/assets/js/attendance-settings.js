(function () {
    const page = document.querySelector('.attendance-settings-page');

    if (!page) {
        return;
    }

    const updateUrl = page.dataset.updateUrl;
    const divisionsUrl = page.dataset.divisionsUrl;
    const csrfToken = page.dataset.csrfToken;
    const dirtyCells = new Map();
    let debounceTimer = null;

    function setUploadState(form, state) {
        const feedback = form.querySelector('[data-upload-feedback]');
        const progressBar = form.querySelector('[data-upload-progress-bar]');
        const percentLabel = form.querySelector('[data-upload-percent]');
        const statusLabel = form.querySelector('[data-upload-status]');
        const errorLabel = form.querySelector('[data-upload-error]');
        const submitButton = form.querySelector('button[type="submit"]');
        const submitLabel = submitButton ? (submitButton.dataset.submitLabel || submitButton.textContent.trim()) : 'Upload';

        if (!feedback || !progressBar || !percentLabel || !statusLabel || !errorLabel || !submitButton) {
            return;
        }

        if (state.mode === 'idle') {
            feedback.classList.add('d-none');
            errorLabel.classList.add('d-none');
            errorLabel.textContent = '';
            progressBar.style.width = '0%';
            progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');
            progressBar.classList.remove('bg-danger', 'bg-success');
            percentLabel.textContent = '0%';
            statusLabel.textContent = 'Menyiapkan upload ZIP ke server...';
            submitButton.disabled = false;
            submitButton.textContent = submitLabel;
            return;
        }

        feedback.classList.remove('d-none');
        progressBar.style.width = `${state.percent}%`;
        percentLabel.textContent = `${state.percent}%`;
        statusLabel.textContent = state.message;
        submitButton.disabled = true;
        submitButton.textContent = state.buttonLabel || 'Sedang Upload...';

        if (state.mode === 'error') {
            progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
            progressBar.classList.add('bg-danger');
            errorLabel.textContent = state.error || 'Upload gagal diproses.';
            errorLabel.classList.remove('d-none');
            submitButton.disabled = false;
            submitButton.textContent = submitLabel;
            return;
        }

        if (state.mode === 'success') {
            progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
            progressBar.classList.add('bg-success');
            errorLabel.classList.add('d-none');
            return;
        }

        progressBar.classList.remove('bg-danger', 'bg-success');
        errorLabel.classList.add('d-none');
    }

    function bindBulkUploads() {
        document.querySelectorAll('.js-bulk-upload-form').forEach((form) => {
            const modal = form.closest('.modal');

            if (modal) {
                modal.addEventListener('hidden.bs.modal', function () {
                    setUploadState(form, {
                        mode: 'idle'
                    });
                });
            }

            form.addEventListener('submit', function (event) {
                if (!window.FormData || !window.XMLHttpRequest) {
                    return;
                }

                event.preventDefault();

                const xhr = new XMLHttpRequest();
                xhr.open(form.method || 'POST', form.action);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('Accept', 'application/json');

                setUploadState(form, {
                    mode: 'uploading',
                    percent: 0,
                    message: 'Mengunggah file ZIP ke server. Jangan tutup halaman ini.',
                    buttonLabel: 'Mengunggah...'
                });

                xhr.upload.addEventListener('progress', function (progressEvent) {
                    if (!progressEvent.lengthComputable) {
                        return;
                    }

                    const percent = Math.max(1, Math.min(99, Math.round((progressEvent.loaded / progressEvent.total) * 100)));

                    setUploadState(form, {
                        mode: 'uploading',
                        percent: percent,
                        message: `Upload berjalan ${percent}%. Setelah selesai, file akan dimasukkan ke antrean background.`,
                        buttonLabel: 'Mengunggah...'
                    });
                });

                xhr.addEventListener('load', function () {
                    let payload = {};

                    try {
                        payload = xhr.responseText ? JSON.parse(xhr.responseText) : {};
                    } catch (error) {
                        payload = {};
                    }

                    if (xhr.status >= 200 && xhr.status < 300) {
                        setUploadState(form, {
                            mode: 'success',
                            percent: 100,
                            message: payload.message || 'Upload selesai dikirim. Halaman akan dimuat ulang.',
                            buttonLabel: 'Selesai'
                        });

                        window.setTimeout(function () {
                            window.location.href = payload.redirect_url || form.dataset.redirectUrl || window.location.href;
                        }, 900);

                        return;
                    }

                    const validationMessage = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
                    const errorMessage = validationMessage
                        || payload.message
                        || 'Upload gagal atau server tidak memberikan respons yang valid.';

                    setUploadState(form, {
                        mode: 'error',
                        percent: 100,
                        message: 'Upload berhenti sebelum selesai diproses.',
                        error: errorMessage
                    });
                });

                xhr.addEventListener('error', function () {
                    setUploadState(form, {
                        mode: 'error',
                        percent: 100,
                        message: 'Koneksi ke server terputus saat upload berlangsung.',
                        error: 'Server tidak merespons. Kemungkinan batas upload atau timeout di hosting masih terlalu kecil.'
                    });
                });

                xhr.addEventListener('abort', function () {
                    setUploadState(form, {
                        mode: 'error',
                        percent: 100,
                        message: 'Upload dibatalkan.',
                        error: 'Proses upload dibatalkan sebelum selesai.'
                    });
                });

                xhr.send(new FormData(form));
            });
        });
    }

    function bindDivisionFilter() {
        if (!window.jQuery || !divisionsUrl) {
            return;
        }

        jQuery(function ($) {
            const filterDepartemen = $('#filter_departemen');
            const filterDivisi = $('#filter_divisi');

            if (!filterDepartemen.length || !filterDivisi.length) {
                return;
            }

            filterDepartemen.on('change', function () {
                const departemen = $(this).val();

                filterDivisi.prop('disabled', true).html('<option value="">Loading...</option>');

                if (!departemen) {
                    filterDivisi.html('<option value="">Semua Divisi</option>').prop('disabled', true);
                    return;
                }

                $.get(divisionsUrl, {
                    departemen: departemen
                }).done(function (response) {
                    filterDivisi.empty().append(new Option('Semua Divisi', ''));

                    response.forEach(function (item) {
                        filterDivisi.append(new Option(item.nama_divisi, item.id));
                    });

                    filterDivisi.prop('disabled', false);
                }).fail(function () {
                    filterDivisi.html('<option value="">Gagal memuat divisi</option>').prop('disabled', true);
                });
            });
        });
    }

    async function sendBatch() {
        const payload = Array.from(dirtyCells.values());

        if (payload.length === 0 || !updateUrl || !csrfToken) {
            return;
        }

        try {
            const response = await fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    data: payload.map((item) => ({
                        employee_id: item.employee_id,
                        tanggal: item.tanggal,
                        status: item.status,
                        auto_status: item.auto_status
                    }))
                })
            });

            let responseData = null;

            try {
                responseData = await response.json();
            } catch (error) {
                responseData = null;
            }

            if (!response.ok) {
                throw new Error(responseData && responseData.message ? responseData.message : 'Update gagal');
            }

            payload.forEach((item) => {
                const shouldResetToAuto = item.status === item.auto_status;
                const finalStatus = shouldResetToAuto ? item.auto_status : item.status;

                item.element.data('status', finalStatus);
                item.element.data('manual-status', shouldResetToAuto ? '' : item.status);
                item.element.closest('td').removeClass('table-warning')
                    .addClass('table-success')
                    .removeClass('schedule-cell--auto-off schedule-cell--manual-off schedule-cell--manual-hadir');

                item.element.siblings('.schedule-cell__meta').remove();

                if (shouldResetToAuto && item.auto_status === 'OFF') {
                    item.element.closest('td').addClass('schedule-cell--auto-off');
                    item.element.after('<span class="schedule-cell__meta schedule-cell__meta--auto">AUTO</span>');
                } else if (!shouldResetToAuto && item.status === 'OFF') {
                    item.element.closest('td').addClass('schedule-cell--manual-off');
                    item.element.after('<span class="schedule-cell__meta schedule-cell__meta--manual">MANUAL</span>');
                } else if (!shouldResetToAuto && item.status === 'HADIR') {
                    item.element.closest('td').addClass('schedule-cell--manual-hadir');
                    item.element.after('<span class="schedule-cell__meta schedule-cell__meta--manual">MANUAL</span>');
                }

                setTimeout(() => {
                    item.element.closest('td').removeClass('table-success');
                }, 800);
            });

            dirtyCells.clear();
        } catch (error) {
            payload.forEach((item) => {
                const oldStatus = item.element.data('status');

                item.element.prop('checked', oldStatus === 'OFF');
                item.element.closest('td').removeClass('table-warning');
            });

            alert(error.message || 'Update gagal');
        }
    }

    function bindAttendanceChanges() {
        if (!window.jQuery) {
            return;
        }

        jQuery(document).on('change', '.attendance-checkbox', function () {
            const checkbox = jQuery(this);
            const employee = checkbox.data('employee');
            const date = checkbox.data('date');
            const newStatus = checkbox.is(':checked') ? 'OFF' : 'HADIR';
            const oldStatus = checkbox.data('status');
            const autoStatus = checkbox.data('auto-status');

            if (newStatus === oldStatus) {
                return;
            }

            dirtyCells.set(`${employee}_${date}`, {
                employee_id: employee,
                tanggal: date,
                status: newStatus,
                auto_status: autoStatus,
                element: checkbox
            });

            checkbox.closest('td').addClass('table-warning');

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(sendBatch, 700);
        });
    }

    bindBulkUploads();
    bindDivisionFilter();
    bindAttendanceChanges();
})();
