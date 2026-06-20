<script>
    (function() {
        const cvUpdateModalEl = document.getElementById('cvUpdatePreviewModal');

        if (!cvUpdateModalEl || cvUpdateModalEl.dataset.bound === '1') {
            return;
        }

        cvUpdateModalEl.dataset.bound = '1';

        const cvUpdateModal = new bootstrap.Modal(cvUpdateModalEl);
        const cvUpdateEmployee = document.getElementById('cvUpdatePreviewEmployee');
        const cvUpdateLoading = document.getElementById('cvUpdatePreviewLoading');
        const cvUpdateError = document.getElementById('cvUpdatePreviewError');
        const cvUpdateEmpty = document.getElementById('cvUpdatePreviewEmpty');
        const cvUpdateContent = document.getElementById('cvUpdatePreviewContent');
        const cvUpdateRows = document.getElementById('cvUpdatePreviewRows');
        const cvUpdateSkipped = document.getElementById('cvUpdatePreviewSkipped');
        const cvUpdateConfirm = document.getElementById('cvUpdateConfirmButton');
        let pendingCvUpdateUrl = null;

        function escapeHtml(value) {
            return $('<div>').text(value === null || value === undefined || value === '' ? '-' : value).html();
        }

        function errorMessageFromXhr(xhr, fallbackMessage) {
            let message = fallbackMessage || 'Request gagal diproses. Silakan coba lagi.';

            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }

            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                const firstKey = Object.keys(xhr.responseJSON.errors)[0];

                if (firstKey && xhr.responseJSON.errors[firstKey][0]) {
                    message = xhr.responseJSON.errors[firstKey][0];
                }
            }

            if (xhr.status === 401 || xhr.status === 419) {
                message = 'Sesi login berakhir. Silakan login ulang.';
            }

            if (xhr.status === 403) {
                message = 'Anda tidak memiliki akses untuk memperbarui data ini.';
            }

            if (xhr.status === 0) {
                message = 'Koneksi bermasalah atau request diblokir. Silakan cek jaringan Anda.';
            }

            return message;
        }

        function resetCvUpdateModal() {
            pendingCvUpdateUrl = null;
            cvUpdateEmployee.textContent = '-';
            cvUpdateLoading.classList.remove('d-none');
            cvUpdateError.classList.add('d-none');
            cvUpdateEmpty.classList.add('d-none');
            cvUpdateContent.classList.add('d-none');
            cvUpdateSkipped.classList.add('d-none');
            cvUpdateError.textContent = '';
            cvUpdateEmpty.textContent = '';
            cvUpdateRows.innerHTML = '';
            cvUpdateSkipped.innerHTML = '';
            cvUpdateConfirm.disabled = true;
        }

        function renderSkippedItems(items) {
            const rows = items.map(function(item) {
                return `<li><strong>${escapeHtml(item.label)}</strong>: ${escapeHtml(item.reason)}</li>`;
            }).join('');

            return `<div class="cv-update-skipped__title">Field dilewati</div><ul>${rows}</ul>`;
        }

        function renderCvUpdatePreview(payload, updateUrl) {
            cvUpdateLoading.classList.add('d-none');

            const changes = payload.changes || [];
            const skipped = payload.skipped || [];

            if (!changes.length) {
                cvUpdateEmpty.textContent = payload.message || 'Tidak ada perubahan yang bisa diperbarui dari CV Maker.';
                cvUpdateEmpty.classList.remove('d-none');

                if (skipped.length) {
                    cvUpdateSkipped.innerHTML = renderSkippedItems(skipped);
                    cvUpdateSkipped.classList.remove('d-none');
                    cvUpdateContent.classList.remove('d-none');
                }

                return;
            }

            cvUpdateRows.innerHTML = changes.map(function(change) {
                return `<tr>
                    <td>${escapeHtml(change.label)}</td>
                    <td>${escapeHtml(change.old)}</td>
                    <td class="cv-update-table__new">${escapeHtml(change.new)}</td>
                </tr>`;
            }).join('');

            if (skipped.length) {
                cvUpdateSkipped.innerHTML = renderSkippedItems(skipped);
                cvUpdateSkipped.classList.remove('d-none');
            }

            pendingCvUpdateUrl = updateUrl;
            cvUpdateConfirm.disabled = false;
            cvUpdateContent.classList.remove('d-none');
        }

        $(document).on('click', '.js-cv-update-preview', function() {
            const button = $(this);
            const previewUrl = button.data('preview-url');
            const updateUrl = button.data('update-url');
            const employeeName = button.data('employee-name') || 'Karyawan';
            const originalHtml = button.html();

            if (!previewUrl || !updateUrl) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak bisa update',
                    text: 'URL update CV Maker belum tersedia untuk data ini.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            resetCvUpdateModal();
            cvUpdateEmployee.textContent = employeeName;
            cvUpdateModal.show();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.ajax({
                url: previewUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(payload) {
                    renderCvUpdatePreview(payload, updateUrl);
                },
                error: function(xhr) {
                    cvUpdateLoading.classList.add('d-none');
                    cvUpdateError.text(errorMessageFromXhr(xhr, 'Preview update gagal dimuat.'));
                    cvUpdateError.classList.remove('d-none');
                },
                complete: function() {
                    button.prop('disabled', false).html(originalHtml);
                }
            });
        });

        $('#cvUpdateConfirmButton').on('click', function() {
            if (!pendingCvUpdateUrl) {
                return;
            }

            const button = $(this);
            const originalHtml = button.html();

            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengupdate...');

            $.ajax({
                url: pendingCvUpdateUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(payload) {
                    cvUpdateModal.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: payload.message || 'Data HRIS berhasil diperbarui dari CV Maker.',
                        confirmButtonText: 'OK'
                    }).then(function() {
                        if (typeof window.onCvMakerHrisUpdated === 'function') {
                            window.onCvMakerHrisUpdated(payload);
                        }
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: errorMessageFromXhr(xhr, 'Data HRIS gagal diperbarui.'),
                        confirmButtonText: 'OK'
                    });
                },
                complete: function() {
                    button.prop('disabled', false).html(originalHtml);
                }
            });
        });
    })();
</script>
