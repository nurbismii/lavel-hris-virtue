(function($) {
    const root = document.querySelector('.employee-edit-shell');

    if (!root || !$) {
        return;
    }

    const config = {
        oldPerusahaan: root.dataset.oldPerusahaan || '',
        oldDepartemen: root.dataset.oldDepartemen || '',
        oldDivisi: root.dataset.oldDivisi || '',
        oldProvinsi: root.dataset.oldProvinsi || '',
        oldKabupaten: root.dataset.oldKabupaten || '',
        oldKecamatan: root.dataset.oldKecamatan || '',
        oldKelurahan: root.dataset.oldKelurahan || '',
        departemenUrl: root.dataset.departemenUrl || '',
        divisiUrl: root.dataset.divisiUrl || '',
        provincesUrl: root.dataset.provincesUrl || '',
        kabupatensBaseUrl: root.dataset.kabupatensBaseUrl || '',
        kecamatansBaseUrl: root.dataset.kecamatansBaseUrl || '',
        kelurahansBaseUrl: root.dataset.kelurahansBaseUrl || '',
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildOptions(defaultLabel, items, valueKey, textKey) {
        let options = `<option value="">${escapeHtml(defaultLabel)}</option>`;

        (Array.isArray(items) ? items : []).forEach((item) => {
            options += `<option value="${escapeHtml(item[valueKey])}">${escapeHtml(item[textKey])}</option>`;
        });

        return options;
    }

    function setSelectLoading(selector, label) {
        $(selector)
            .html(`<option value="">${escapeHtml(label || 'Memuat...')}</option>`)
            .prop('disabled', true);
    }

    function setSelectReady(selector, options) {
        $(selector)
            .html(options)
            .prop('disabled', false);
    }

    function showAjaxError(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: message,
                confirmButtonText: 'OK'
            });

            return;
        }

        window.alert(message);
    }

    function loadCompanyHierarchy() {
        $('#perusahaan_id').on('change', function() {
            const perusahaan = $(this).val();

            setSelectLoading('#departemen_id', 'Memuat departemen...');
            setSelectReady('#divisi_id', '<option value="">-- Pilih Divisi --</option>');

            if (!perusahaan || !config.departemenUrl) {
                setSelectReady('#departemen_id', '<option value="">-- Pilih Departemen --</option>');
                return;
            }

            $.get(config.departemenUrl, { area: perusahaan }, function(data) {
                setSelectReady('#departemen_id', buildOptions('-- Pilih Departemen --', data, 'id', 'departemen'));

                if (config.oldDepartemen && perusahaan === config.oldPerusahaan) {
                    $('#departemen_id').val(config.oldDepartemen).trigger('change');
                }
            }).fail(function() {
                setSelectReady('#departemen_id', '<option value="">Departemen gagal dimuat</option>');
                showAjaxError('Departemen gagal dimuat. Silakan cek koneksi lalu coba lagi.');
            });
        });

        $('#departemen_id').on('change', function() {
            const departemen = $(this).val();

            setSelectLoading('#divisi_id', 'Memuat divisi...');

            if (!departemen || !config.divisiUrl) {
                setSelectReady('#divisi_id', '<option value="">-- Pilih Divisi --</option>');
                return;
            }

            $.get(config.divisiUrl, { departemen: departemen }, function(data) {
                setSelectReady('#divisi_id', buildOptions('-- Pilih Divisi --', data, 'id', 'nama_divisi'));

                if (config.oldDivisi && departemen === config.oldDepartemen) {
                    $('#divisi_id').val(config.oldDivisi);
                }
            }).fail(function() {
                setSelectReady('#divisi_id', '<option value="">Divisi gagal dimuat</option>');
                showAjaxError('Divisi gagal dimuat. Silakan cek koneksi lalu coba lagi.');
            });
        });

        if (config.oldPerusahaan) {
            $('#perusahaan_id').trigger('change');
        }
    }

    function loadDocumentPreview() {
        const modalElement = document.getElementById('employeeDocumentPreviewModal');

        if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }

        const previewModal = new bootstrap.Modal(modalElement);
        const titleElement = document.getElementById('employeeDocumentPreviewModalLabel');
        const fileNameElement = document.getElementById('employeeDocumentPreviewFileName');
        const imageWrap = document.getElementById('employeeDocumentPreviewImageWrap');
        const imageElement = document.getElementById('employeeDocumentPreviewImage');
        const pdfWrap = document.getElementById('employeeDocumentPreviewPdfWrap');
        const pdfElement = document.getElementById('employeeDocumentPreviewPdf');
        const openButton = document.getElementById('employeeDocumentPreviewOpen');
        const downloadButton = document.getElementById('employeeDocumentPreviewDownload');

        function resetPreview() {
            imageWrap.classList.remove('d-none');
            pdfWrap.classList.add('d-none');
            imageElement.removeAttribute('src');
            pdfElement.removeAttribute('src');
        }

        document.querySelectorAll('[data-document-preview]').forEach((button) => {
            button.addEventListener('click', function() {
                const fileUrl = this.dataset.fileUrl;
                const fileType = this.dataset.fileType || 'image';
                const fileLabel = this.dataset.fileLabel || 'Preview Dokumen';
                const fileName = this.dataset.fileName || '';

                titleElement.textContent = fileLabel;
                fileNameElement.textContent = fileName;
                openButton.href = fileUrl;
                downloadButton.href = fileUrl;

                resetPreview();

                if (fileType === 'pdf') {
                    imageWrap.classList.add('d-none');
                    pdfWrap.classList.remove('d-none');
                    pdfElement.src = fileUrl + '#toolbar=1&navpanes=0&view=FitH';
                } else {
                    imageElement.src = fileUrl;
                }

                previewModal.show();
            });
        });

        modalElement.addEventListener('hidden.bs.modal', resetPreview);
    }

    function loadRegionHierarchy() {
        if (!config.provincesUrl) {
            return;
        }

        $.get(config.provincesUrl, function(data) {
            setSelectReady('#provinsi_id', buildOptions('-- Pilih Provinsi --', data, 'id', 'provinsi'));

            if (config.oldProvinsi) {
                $('#provinsi_id').val(config.oldProvinsi).trigger('change');
            }
        }).fail(function() {
            setSelectReady('#provinsi_id', '<option value="">Provinsi gagal dimuat</option>');
            showAjaxError('Provinsi gagal dimuat. Silakan cek koneksi lalu coba lagi.');
        });

        $('#provinsi_id').on('change', function() {
            const provinsi = $(this).val();

            setSelectLoading('#kabupaten_id', 'Memuat kabupaten...');
            setSelectReady('#kecamatan_id', '<option value="">-- Pilih Kecamatan --</option>');
            setSelectReady('#kelurahan_id', '<option value="">-- Pilih Kelurahan --</option>');

            if (!provinsi || !config.kabupatensBaseUrl) {
                setSelectReady('#kabupaten_id', '<option value="">-- Pilih Kabupaten --</option>');
                return;
            }

            $.get(`${config.kabupatensBaseUrl}/${provinsi}`, function(data) {
                setSelectReady('#kabupaten_id', buildOptions('-- Pilih Kabupaten --', data, 'id', 'kabupaten'));

                if (config.oldKabupaten && provinsi === config.oldProvinsi) {
                    $('#kabupaten_id').val(config.oldKabupaten).trigger('change');
                }
            }).fail(function() {
                setSelectReady('#kabupaten_id', '<option value="">Kabupaten gagal dimuat</option>');
                showAjaxError('Kabupaten gagal dimuat. Silakan cek koneksi lalu coba lagi.');
            });
        });

        $('#kabupaten_id').on('change', function() {
            const kabupaten = $(this).val();

            setSelectLoading('#kecamatan_id', 'Memuat kecamatan...');
            setSelectReady('#kelurahan_id', '<option value="">-- Pilih Kelurahan --</option>');

            if (!kabupaten || !config.kecamatansBaseUrl) {
                setSelectReady('#kecamatan_id', '<option value="">-- Pilih Kecamatan --</option>');
                return;
            }

            $.get(`${config.kecamatansBaseUrl}/${kabupaten}`, function(data) {
                setSelectReady('#kecamatan_id', buildOptions('-- Pilih Kecamatan --', data, 'id', 'kecamatan'));

                if (config.oldKecamatan && kabupaten === config.oldKabupaten) {
                    $('#kecamatan_id').val(config.oldKecamatan).trigger('change');
                }
            }).fail(function() {
                setSelectReady('#kecamatan_id', '<option value="">Kecamatan gagal dimuat</option>');
                showAjaxError('Kecamatan gagal dimuat. Silakan cek koneksi lalu coba lagi.');
            });
        });

        $('#kecamatan_id').on('change', function() {
            const kecamatan = $(this).val();

            setSelectLoading('#kelurahan_id', 'Memuat kelurahan...');

            if (!kecamatan || !config.kelurahansBaseUrl) {
                setSelectReady('#kelurahan_id', '<option value="">-- Pilih Kelurahan --</option>');
                return;
            }

            $.get(`${config.kelurahansBaseUrl}/${kecamatan}`, function(data) {
                setSelectReady('#kelurahan_id', buildOptions('-- Pilih Kelurahan --', data, 'id', 'kelurahan'));

                if (config.oldKelurahan && kecamatan === config.oldKecamatan) {
                    $('#kelurahan_id').val(config.oldKelurahan);
                }
            }).fail(function() {
                setSelectReady('#kelurahan_id', '<option value="">Kelurahan gagal dimuat</option>');
                showAjaxError('Kelurahan gagal dimuat. Silakan cek koneksi lalu coba lagi.');
            });
        });
    }

    function bindFileInputState() {
        document.querySelectorAll('.employee-document-row input[type="file"]').forEach((input) => {
            input.addEventListener('change', function() {
                const row = this.closest('.employee-document-row');
                const help = row ? row.querySelector('.employee-document-input__help') : null;
                const file = this.files && this.files.length ? this.files[0] : null;

                if (!row || !help) {
                    return;
                }

                if (!help.dataset.originalText) {
                    help.dataset.originalText = help.textContent.trim();
                }

                row.classList.toggle('employee-document-row--has-new-file', Boolean(file));
                help.textContent = file
                    ? `File dipilih: ${file.name}. Simpan perubahan untuk mengunggah.`
                    : help.dataset.originalText;
            });
        });
    }

    function bindContractTimeline() {
        document.querySelectorAll('[data-contract-timeline-collapse]').forEach((collapseElement) => {
            const item = collapseElement.closest('[data-contract-timeline-item]');
            const toggle = item ? item.querySelector('[data-contract-timeline-toggle]') : null;
            const label = toggle ? toggle.querySelector('.employee-contract-timeline__toggle-text') : null;

            function syncTimelineState(isOpen) {
                if (item) {
                    item.classList.toggle('employee-contract-timeline__item--active', isOpen);
                }

                if (toggle) {
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }

                if (label) {
                    label.textContent = isOpen ? 'Tutup detail' : 'Lihat detail';
                }
            }

            syncTimelineState(collapseElement.classList.contains('show'));

            collapseElement.addEventListener('shown.bs.collapse', function() {
                syncTimelineState(true);
            });

            collapseElement.addEventListener('hidden.bs.collapse', function() {
                syncTimelineState(false);
            });
        });
    }

    function bindResignStatusFields() {
        const statusField = document.getElementById('status_resign');
        const resignDateField = document.getElementById('tgl_resign');
        const resignDatePlaceholder = document.getElementById('tgl_resign_placeholder');
        const exitCategoryField = document.getElementById('kategori_keluar');

        if (!statusField) {
            return;
        }

        function isActiveEmployee() {
            return String(statusField.value || '').trim().toUpperCase() === 'AKTIF';
        }

        function syncResignFields() {
            const active = isActiveEmployee();
            const placeholder = exitCategoryField ? exitCategoryField.dataset.activePlaceholder || '-' : '-';

            if (resignDateField && resignDatePlaceholder) {
                if (active) {
                    if (resignDateField.value) {
                        resignDateField.dataset.previousValue = resignDateField.value;
                    }

                    resignDateField.value = '';
                    resignDateField.disabled = true;
                    resignDateField.classList.add('d-none');
                    resignDatePlaceholder.classList.remove('d-none');
                } else {
                    resignDateField.disabled = false;
                    resignDateField.classList.remove('d-none');
                    resignDatePlaceholder.classList.add('d-none');

                    if (!resignDateField.value && resignDateField.dataset.previousValue) {
                        resignDateField.value = resignDateField.dataset.previousValue;
                    }
                }
            }

            if (exitCategoryField) {
                if (active) {
                    if (exitCategoryField.value && exitCategoryField.value !== placeholder) {
                        exitCategoryField.dataset.previousValue = exitCategoryField.value;
                    }

                    exitCategoryField.value = placeholder;
                    exitCategoryField.readOnly = true;
                } else {
                    exitCategoryField.readOnly = false;

                    if (exitCategoryField.value === placeholder) {
                        exitCategoryField.value = exitCategoryField.dataset.previousValue || '';
                    }
                }
            }
        }

        statusField.addEventListener('change', syncResignFields);
        syncResignFields();
    }

    function bindMarriageStatusFields() {
        const maritalStatusField = document.getElementById('status_perkawinan');
        const marriageDateField = document.getElementById('tanggal_menikah');
        const marriageDatePlaceholder = document.getElementById('tanggal_menikah_placeholder');

        if (!maritalStatusField || !marriageDateField || !marriageDatePlaceholder) {
            return;
        }

        function isUnmarriedEmployee() {
            return String(maritalStatusField.value || '').trim().toLowerCase() === 'belum kawin';
        }

        function syncMarriageDateField() {
            if (isUnmarriedEmployee()) {
                if (marriageDateField.value) {
                    marriageDateField.dataset.previousValue = marriageDateField.value;
                }

                marriageDateField.value = '';
                marriageDateField.disabled = true;
                marriageDateField.classList.add('d-none');
                marriageDatePlaceholder.classList.remove('d-none');

                return;
            }

            marriageDateField.disabled = false;
            marriageDateField.classList.remove('d-none');
            marriageDatePlaceholder.classList.add('d-none');

            if (!marriageDateField.value && marriageDateField.dataset.previousValue) {
                marriageDateField.value = marriageDateField.dataset.previousValue;
            }
        }

        maritalStatusField.addEventListener('change', syncMarriageDateField);
        syncMarriageDateField();
    }

    function bindSubmitState() {
        const form = document.getElementById('employeeEditForm');
        const button = document.getElementById('employeeSaveButton');

        if (!form || !button) {
            return;
        }

        form.addEventListener('submit', function() {
            const loadingText = button.dataset.loadingText || 'Menyimpan...';

            if (!button.dataset.originalText) {
                button.dataset.originalText = button.innerHTML;
            }

            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>${escapeHtml(loadingText)}`;
        });
    }

    $(function() {
        loadCompanyHierarchy();
        loadDocumentPreview();
        loadRegionHierarchy();
        bindFileInputState();
        bindContractTimeline();
        bindResignStatusFields();
        bindMarriageStatusFields();
        bindSubmitState();
    });
})(window.jQuery);
