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

    function loadCompanyHierarchy() {
        $('#perusahaan_id').on('change', function() {
            const perusahaan = $(this).val();

            $('#departemen_id').html('<option value="">Loading...</option>');
            $('#divisi_id').html('<option value="">-- Pilih Divisi --</option>');

            if (!perusahaan || !config.departemenUrl) {
                $('#departemen_id').html('<option value="">-- Pilih Departemen --</option>');
                return;
            }

            $.get(config.departemenUrl, { area: perusahaan }, function(data) {
                $('#departemen_id').html(buildOptions('-- Pilih Departemen --', data, 'id', 'departemen'));

                if (config.oldDepartemen && perusahaan === config.oldPerusahaan) {
                    $('#departemen_id').val(config.oldDepartemen).trigger('change');
                }
            }).fail(function() {
                $('#departemen_id').html('<option value="">Departemen gagal dimuat</option>');
            });
        });

        $('#departemen_id').on('change', function() {
            const departemen = $(this).val();

            $('#divisi_id').html('<option value="">Loading...</option>');

            if (!departemen || !config.divisiUrl) {
                $('#divisi_id').html('<option value="">-- Pilih Divisi --</option>');
                return;
            }

            $.get(config.divisiUrl, { departemen: departemen }, function(data) {
                $('#divisi_id').html(buildOptions('-- Pilih Divisi --', data, 'id', 'nama_divisi'));

                if (config.oldDivisi && departemen === config.oldDepartemen) {
                    $('#divisi_id').val(config.oldDivisi);
                }
            }).fail(function() {
                $('#divisi_id').html('<option value="">Divisi gagal dimuat</option>');
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
            $('#provinsi_id').html(buildOptions('-- Pilih Provinsi --', data, 'id', 'provinsi'));

            if (config.oldProvinsi) {
                $('#provinsi_id').val(config.oldProvinsi).trigger('change');
            }
        }).fail(function() {
            $('#provinsi_id').html('<option value="">Provinsi gagal dimuat</option>');
        });

        $('#provinsi_id').on('change', function() {
            const provinsi = $(this).val();

            $('#kabupaten_id').html('<option>Loading...</option>');
            $('#kecamatan_id').html('<option value="">-- Pilih Kecamatan --</option>');
            $('#kelurahan_id').html('<option value="">-- Pilih Kelurahan --</option>');

            if (!provinsi || !config.kabupatensBaseUrl) {
                return;
            }

            $.get(`${config.kabupatensBaseUrl}/${provinsi}`, function(data) {
                $('#kabupaten_id').html(buildOptions('-- Pilih Kabupaten --', data, 'id', 'kabupaten'));

                if (config.oldKabupaten && provinsi === config.oldProvinsi) {
                    $('#kabupaten_id').val(config.oldKabupaten).trigger('change');
                }
            }).fail(function() {
                $('#kabupaten_id').html('<option value="">Kabupaten gagal dimuat</option>');
            });
        });

        $('#kabupaten_id').on('change', function() {
            const kabupaten = $(this).val();

            $('#kecamatan_id').html('<option>Loading...</option>');
            $('#kelurahan_id').html('<option value="">-- Pilih Kelurahan --</option>');

            if (!kabupaten || !config.kecamatansBaseUrl) {
                return;
            }

            $.get(`${config.kecamatansBaseUrl}/${kabupaten}`, function(data) {
                $('#kecamatan_id').html(buildOptions('-- Pilih Kecamatan --', data, 'id', 'kecamatan'));

                if (config.oldKecamatan && kabupaten === config.oldKabupaten) {
                    $('#kecamatan_id').val(config.oldKecamatan).trigger('change');
                }
            }).fail(function() {
                $('#kecamatan_id').html('<option value="">Kecamatan gagal dimuat</option>');
            });
        });

        $('#kecamatan_id').on('change', function() {
            const kecamatan = $(this).val();

            $('#kelurahan_id').html('<option>Loading...</option>');

            if (!kecamatan || !config.kelurahansBaseUrl) {
                return;
            }

            $.get(`${config.kelurahansBaseUrl}/${kecamatan}`, function(data) {
                $('#kelurahan_id').html(buildOptions('-- Pilih Kelurahan --', data, 'id', 'kelurahan'));

                if (config.oldKelurahan && kecamatan === config.oldKecamatan) {
                    $('#kelurahan_id').val(config.oldKelurahan);
                }
            }).fail(function() {
                $('#kelurahan_id').html('<option value="">Kelurahan gagal dimuat</option>');
            });
        });
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
        bindSubmitState();
    });
})(window.jQuery);
