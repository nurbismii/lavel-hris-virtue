@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-karyawan-index.css') }}">
<style>
    .table-scroll-wrapper {
        overflow-x: auto;
    }

    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_paginate {
        white-space: nowrap;
    }

    .dataTables_scrollBody {
        overflow-x: auto !important;
    }

    .bulk-upload-feedback {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
        padding: 14px 16px;
    }

    .bulk-upload-feedback__title {
        font-size: 0.92rem;
        font-weight: 600;
        color: #0f172a;
    }

    .bulk-upload-feedback__text {
        font-size: 0.84rem;
        color: #475569;
    }

    .bulk-upload-feedback__error {
        font-size: 0.84rem;
        color: #b91c1c;
    }
</style>
@endpush

@section('content')
<div class="container-fluid karyawan-page">
    <div class="page-inner">
        <div class="karyawan-page-header d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-users text-primary me-2"></i>
                    Data Karyawan
                </h4>
                <small class="text-muted">
                    Daftar keseluruhan karyawan VDNI/VDNIP/OSS
                </small>
            </div>

            @if($canManageMasterData)
            <div class="karyawan-actions ms-md-auto py-2 py-md-0 d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalBulkDocuments">
                    <i class="fas fa-folder-open me-1"></i>
                    Bulk Dokumen
                </a>
                <a class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportEmployee">
                    <i class="fas fa-file-import me-1"></i>
                    Bulk Karyawan
                </a>
            </div>
            @endif
        </div>

        @if(!auth()->user()->canAccessAllEmployees())
        <div class="alert alert-light border mb-3">
            Data karyawan dibatasi sesuai scope role Anda: {{ auth()->user()->role->scope_label ?? 'Akun sendiri' }}.
        </div>
        @endif

        <div class="col-md-12">
            <div class="card karyawan-card">
                <div class="card-body">
                    <div class="karyawan-filter-panel">
                        <div class="karyawan-filter-panel__header">
                            <div>
                                <div class="karyawan-filter-panel__title">Filter Data</div>
                                <small class="text-muted">Pilih perusahaan, departemen, divisi, dan status karyawan.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-light border" id="btnResetFilter">
                                Reset
                            </button>
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-xl-3 col-md-6">
                                <label class="form-label">Perusahaan</label>
                                <div class="company-filter">
                                    <button class="btn btn-light border dropdown-toggle company-filter__toggle" type="button" id="companyFilterDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                        <span id="filterAreaLabel">Semua perusahaan</span>
                                    </button>
                                    <div class="dropdown-menu company-filter__menu" aria-labelledby="companyFilterDropdown">
                                        <div class="company-filter__menu-header">
                                            <span>Pilih perusahaan</span>
                                            <button type="button" class="btn btn-link btn-sm p-0" id="btnClearAreaFilter">Kosongkan</button>
                                        </div>
                                        @forelse ($areas as $area)
                                        <label class="company-filter__option">
                                            <input type="checkbox" class="form-check-input filter-area-check" value="{{ $area->kode_perusahaan }}">
                                            <span>{{ $area->kode_perusahaan }}</span>
                                        </label>
                                        @empty
                                        <div class="company-filter__empty">Tidak ada perusahaan tersedia.</div>
                                        @endforelse
                                    </div>
                                </div>
                                <select id="filter_area" class="d-none" multiple aria-hidden="true">
                                    @foreach ($areas as $area)
                                    <option value="{{ $area->kode_perusahaan }}">{{ $area->kode_perusahaan }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label class="form-label" for="filter_departemen">Departemen</label>
                                <select id="filter_departemen" class="form-select">
                                    <option value="">Semua Departemen</option>
                                    @php
                                    $groupedDepts = [];
                                    foreach ($departemens as $d) {
                                    $groupedDepts[optional($d->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $d;
                                    }
                                    @endphp

                                    @foreach($groupedDepts as $perusahaan => $departemenItems)
                                    <optgroup label="{{ $perusahaan }}">
                                        @foreach($departemenItems as $d)
                                        <option value="{{ $d->id }}">{{ $d->departemen }}</option>
                                        @endforeach
                                    </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label class="form-label" for="filter_divisi">Divisi</label>
                                <select id="filter_divisi" class="form-select">
                                    <option value="">Semua Divisi</option>
                                    @foreach ($divisis as $v)
                                    <option value="{{ $v->id }}">{{ $v->nama_divisi }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <label class="form-label" for="filter_resign">Status</label>
                                <select id="filter_resign" class="form-select">
                                    <option value="">Semua Kategori Resign</option>
                                    <option value="AKTIF" selected>Aktif</option>
                                    <option value="RESIGN SESUAI PROSEDUR">Resign Sesuai Prosedur</option>
                                    <option value="RESIGN TIDAK SESUAI PROSEDUR">Resign Tidak Sesuai Prosedur</option>
                                    <option value="RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN">Resign Tidak Sesuai Prosedur-Pengajuan</option>
                                    <option value="RESIGN TIDAK SESUAI PROSEDUR-KABUR">Resign Tidak Sesuai Prosedur-Kabur</option>
                                    <option value="RESIGN TIDAK SESUAI PROSEDUR-PAYROLL">Resign Tidak Sesuai Prosedur-Payroll</option>
                                    <option value="PB RESIGN">PB Resign</option>
                                    <option value="PUTUS KONTRAK">Putus Kontrak</option>
                                    <option value="PHK">PHK</option>
                                    <option value="PHK PENSIUN">PHK Pensiun</option>
                                    <option value="PHK PENSIUN DINI">PHK Pensiun Dini</option>
                                    <option value="PHK PIDANA">PHK Pidana</option>
                                    <option value="PHK MENINGGAL DUNIA">PHK Meninggal Dunia</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="karyawan-table-section">
                        <table id="multi-filter-select" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead>
                                <tr>
                                    <th>NIK</th>
                                    <th>Nama</th>
                                    <th>Area</th>
                                    <th>Departemen</th>
                                    <th>Divisi</th>
                                    <th>Posisi</th>
                                    <th>Status</th>
                                    <th>Dokumen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecruitmentDocuments" tabindex="-1" aria-labelledby="modalRecruitmentDocumentsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content recruitment-documents-modal">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalRecruitmentDocumentsLabel">Dokumen Recruitment</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="recruitmentDocumentsLoading" class="recruitment-documents-state">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Mengambil dokumen dari recruitment...
                </div>
                <div id="recruitmentDocumentsError" class="alert alert-warning d-none mb-0"></div>
                <div id="recruitmentDocumentsEmpty" class="alert alert-light border d-none mb-0">
                    Dokumen recruitment tidak ditemukan untuk No KTP karyawan ini.
                </div>
                <div id="recruitmentDocumentsList" class="recruitment-documents-list d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDocumentPreview" tabindex="-1" aria-labelledby="modalDocumentPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content document-preview-modal">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalDocumentPreviewLabel">Preview Dokumen</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="document-preview-frame-wrap">
                    <iframe id="documentPreviewFrame" class="document-preview-frame" title="Preview dokumen karyawan"></iframe>
                    <img id="documentPreviewImage" class="document-preview-image d-none" alt="Preview dokumen karyawan">
                </div>
                <div class="document-preview-help">
                    Jika preview tidak tampil, gunakan tombol download untuk membuka file langsung.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="documentPreviewDownload" class="btn btn-primary">Download</a>
            </div>
        </div>
    </div>
</div>

@if($canManageMasterData)
<div class="modal fade" id="modalBulkDocuments" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalBulkDocumentsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalBulkDocumentsLabel">Bulk Upload Dokumen Karyawan</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('karyawan.bulk-upload-documents') }}" method="POST" enctype="multipart/form-data" class="js-bulk-upload-form" data-redirect-url="{{ route('karyawan.index') }}">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-light border small">
                        Upload satu file ZIP per jenis dokumen.
                        Isi ZIP harus memakai nama file yang mengandung NIK karyawan, misalnya <code>2200112233.jpg</code>, <code>2200112233.jpeg</code>, atau <code>2200112233.pdf</code>.
                        ZIP akan diproses di background dan hasil akhirnya dikirim ke notifikasi.
                    </div>

                    <div class="alert alert-warning small">
                        <div class="fw-semibold mb-1">Panduan cepat</div>
                        Batas upload ZIP dari aplikasi ini disiapkan sampai sekitar <code>500MB</code> per file ZIP. Pastikan worker queue aktif agar proses berjalan di background.
                        <div class="mt-2">
                            <a href="{{ asset('upload-templates/contoh-zip-dokumen-karyawan.txt') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                Download Template ZIP
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ZIP Foto Karyawan</label>
                            <input type="file" name="bulk_photo_zip" class="form-control" accept=".zip,application/zip">
                            <small class="text-muted">Satu ZIP khusus isi foto karyawan. Maksimal sekitar 500MB per ZIP.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ZIP KTP</label>
                            <input type="file" name="bulk_ktp_zip" class="form-control" accept=".zip,application/zip">
                            <small class="text-muted">Satu ZIP khusus isi file KTP. Maksimal sekitar 500MB per ZIP.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ZIP KK</label>
                            <input type="file" name="bulk_kk_zip" class="form-control" accept=".zip,application/zip">
                            <small class="text-muted">Satu ZIP khusus isi file KK. Maksimal sekitar 500MB per ZIP.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ZIP SIM</label>
                            <input type="file" name="bulk_sim_zip" class="form-control" accept=".zip,application/zip">
                            <small class="text-muted">Satu ZIP khusus isi file SIM. Maksimal sekitar 500MB per ZIP.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">ZIP SIO</label>
                            <input type="file" name="bulk_sio_zip" class="form-control" accept=".zip,application/zip">
                            <small class="text-muted">Satu ZIP khusus isi file SIO. Maksimal sekitar 500MB per ZIP.</small>
                        </div>
                    </div>

                    @if($errors->has('bulk_documents_zip'))
                    <div class="alert alert-warning mt-3 mb-0">
                        {{ $errors->first('bulk_documents_zip') }}
                    </div>
                    @endif

                    <div class="bulk-upload-feedback mt-3 d-none" data-upload-feedback>
                        <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                            <div class="bulk-upload-feedback__title">Upload sedang berjalan</div>
                            <div class="bulk-upload-feedback__text" data-upload-percent>0%</div>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" data-upload-progress-bar></div>
                        </div>
                        <div class="bulk-upload-feedback__text mt-2 mb-0" data-upload-status>Menyiapkan upload ZIP ke server...</div>
                        <div class="bulk-upload-feedback__error mt-2 d-none" data-upload-error></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary" data-submit-label="Upload ZIP">Upload ZIP</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportEmployee" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalImportEmployeeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalImportEmployeeLabel">Import Karyawan</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('karyawan.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                            <label for="exampleFormControlFile1">Pilih file excel</label>
                            <input type="file" name="file" class="form-control-file" id="exampleFormControlFile1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<!-- Datatables -->
<script>
    (function() {
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

        document.querySelectorAll('.js-bulk-upload-form').forEach((form) => {
            const modal = form.closest('.modal');

            if (modal) {
                modal.addEventListener('hidden.bs.modal', function() {
                    setUploadState(form, {
                        mode: 'idle'
                    });
                });
            }

            form.addEventListener('submit', function(event) {
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

                xhr.upload.addEventListener('progress', function(progressEvent) {
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

                xhr.addEventListener('load', function() {
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

                        window.setTimeout(function() {
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

                xhr.addEventListener('error', function() {
                    setUploadState(form, {
                        mode: 'error',
                        percent: 100,
                        message: 'Koneksi ke server terputus saat upload berlangsung.',
                        error: 'Server tidak merespons. Kemungkinan batas upload atau timeout di hosting masih terlalu kecil.'
                    });
                });

                xhr.addEventListener('abort', function() {
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
    })();
</script>

<script>
    function selectedAreaCodes() {
        return $('.filter-area-check:checked').map(function() {
            return this.value;
        }).get();
    }

    function syncAreaFilter() {
        const areas = selectedAreaCodes();
        const label = areas.length
            ? (areas.length <= 2 ? areas.join(', ') : `${areas.length} perusahaan dipilih`)
            : 'Semua perusahaan';

        $('#filter_area').val(areas);
        $('#filterAreaLabel').text(label);
        $('#companyFilterDropdown').toggleClass('is-active', areas.length > 0);
    }

    function resetDepartmentAndDivision(disableDepartment = true) {
        $('#filter_departemen')
            .html('<option value="">Semua Departemen</option>')
            .val('')
            .prop('disabled', disableDepartment);

        $('#filter_divisi')
            .html('<option value="">Semua Divisi</option>')
            .val('')
            .prop('disabled', true);
    }

    function reloadDepartmentsForSelectedAreas() {
        const areas = selectedAreaCodes();

        resetDepartmentAndDivision(!areas.length);

        if (!areas.length) {
            table.draw();
            return;
        }

        $('#filter_departemen').html('<option value="">Loading...</option>');

        $.get("{{ route('ajax.departemen.by.area') }}", {
            area: areas
        }, function(res) {
            let opt = '<option value="">Semua Departemen</option>';
            res.forEach(r => {
                opt += `<option value="${r.id}">${r.departemen}</option>`;
            });
            $('#filter_departemen').html(opt).prop('disabled', false);
            table.draw();
        }).fail(function() {
            resetDepartmentAndDivision(true);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Departemen gagal dimuat. Silakan coba lagi.',
                confirmButtonText: 'OK'
            });
        });
    }

    let table = $('#multi-filter-select').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,

        dom: "<'row mb-2'<'col-md-6'l><'col-md-6 text-end'f>>" +
            "<'table-scroll-wrapper'tr>" +
            "<'row mt-2'<'col-md-6'i><'col-md-6 text-end'p>>",

        ajax: {
            url: "{{ route('karyawan.index') }}",
            data: function(d) {
                d.area = selectedAreaCodes();
                d.departemen = $('#filter_departemen').val();
                d.divisi = $('#filter_divisi').val();
                d.status_resign = $('#filter_resign').val();
            }
        },

        fixedColumns: {
            rightColumns: 1
        },

        columns: [{
                data: 'nik'
            },
            {
                data: 'nama_karyawan'
            },
            {
                data: 'area_kerja'
            },
            {
                data: 'departemen'
            },
            {
                data: 'divisi'
            },
            {
                data: 'posisi'
            },
            {
                data: 'status_resign'
            },
            {
                data: 'dokumen',
                orderable: false,
                searchable: false
            },
            {
                data: 'aksi',
                orderable: false,
                searchable: false
            },
        ]
    });

    syncAreaFilter();
    resetDepartmentAndDivision(true);

    $('.filter-area-check').on('change', function() {
        syncAreaFilter();
        $('#filter_area').trigger('change');
    });

    $('#btnClearAreaFilter').on('click', function() {
        $('.filter-area-check').prop('checked', false);
        syncAreaFilter();
        $('#filter_area').trigger('change');
    });

    $('#filter_area').on('change', function() {
        reloadDepartmentsForSelectedAreas();
    });

    $('#filter_departemen').on('change', function() {
        let departemen = $(this).val();

        $('#filter_divisi').html('<option value="">Loading...</option>').prop('disabled', true);

        if (!departemen) {
            $('#filter_divisi').html('<option value="">Semua Divisi</option>').prop('disabled', true);
            table.draw();
            return;
        }

        $.get("{{ route('ajax.divisi.by.departemen') }}", {
            departemen
        }, function(res) {
            let opt = '<option value="">Semua Divisi</option>';
            res.forEach(r => {
                opt += `<option value="${r.id}">${r.nama_divisi}</option>`;
            });
            $('#filter_divisi').html(opt).prop('disabled', false);
            table.draw();
        }).fail(function() {
            $('#filter_divisi').html('<option value="">Divisi gagal dimuat</option>').prop('disabled', true);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Divisi gagal dimuat. Silakan coba lagi.',
                confirmButtonText: 'OK'
            });
        });
    });

    $('#filter_divisi, #filter_resign').on('change', function() {
        table.draw();
    });

    $('#btnResetFilter').on('click', function() {
        $('.filter-area-check').prop('checked', false);
        syncAreaFilter();
        resetDepartmentAndDivision(true);
        $('#filter_resign').val('AKTIF');
        table.draw();
    });

    const documentPreviewModalEl = document.getElementById('modalDocumentPreview');
    const documentPreviewFrame = document.getElementById('documentPreviewFrame');
    const documentPreviewImage = document.getElementById('documentPreviewImage');
    const documentPreviewTitle = document.getElementById('modalDocumentPreviewLabel');
    const documentPreviewDownload = document.getElementById('documentPreviewDownload');
    const documentPreviewModal = documentPreviewModalEl ? new bootstrap.Modal(documentPreviewModalEl) : null;
    const recruitmentDocumentsModalEl = document.getElementById('modalRecruitmentDocuments');
    const recruitmentDocumentsModal = recruitmentDocumentsModalEl ? new bootstrap.Modal(recruitmentDocumentsModalEl) : null;
    const recruitmentDocumentsTitle = document.getElementById('modalRecruitmentDocumentsLabel');
    const recruitmentDocumentsLoading = document.getElementById('recruitmentDocumentsLoading');
    const recruitmentDocumentsError = document.getElementById('recruitmentDocumentsError');
    const recruitmentDocumentsEmpty = document.getElementById('recruitmentDocumentsEmpty');
    const recruitmentDocumentsList = document.getElementById('recruitmentDocumentsList');

    function isPreviewImage(url, mime = '') {
        if (mime.toLowerCase().startsWith('image/')) {
            return true;
        }

        try {
            const pathname = new URL(url, window.location.origin).pathname.toLowerCase();
            return /\.(jpg|jpeg|png|webp)$/i.test(pathname);
        } catch (error) {
            return false;
        }
    }

    function resetRecruitmentDocumentsModal() {
        recruitmentDocumentsLoading.classList.remove('d-none');
        recruitmentDocumentsError.classList.add('d-none');
        recruitmentDocumentsEmpty.classList.add('d-none');
        recruitmentDocumentsList.classList.add('d-none');
        recruitmentDocumentsError.textContent = '';
        recruitmentDocumentsList.innerHTML = '';
    }

    function renderRecruitmentDocuments(documents) {
        recruitmentDocumentsLoading.classList.add('d-none');
        recruitmentDocumentsList.innerHTML = '';

        if (!documents.length) {
            recruitmentDocumentsEmpty.classList.remove('d-none');
            return;
        }

        documents.forEach(function(documentItem) {
            const previewUrl = documentItem.preview_url || documentItem.download_url || '';
            const downloadUrl = documentItem.download_url || documentItem.preview_url || '';

            if (!previewUrl && !downloadUrl) {
                return;
            }

            const button = document.createElement('button');
            const label = documentItem.label || documentItem.type || 'Dokumen';
            button.type = 'button';
            button.className = 'recruitment-document-item js-external-document-preview';
            button.dataset.previewUrl = previewUrl;
            button.dataset.downloadUrl = downloadUrl;
            button.dataset.documentLabel = label;
            button.dataset.documentMime = documentItem.mime || '';

            const title = document.createElement('span');
            title.className = 'recruitment-document-item__title';
            title.textContent = label;

            const meta = document.createElement('span');
            meta.className = 'recruitment-document-item__meta';
            meta.textContent = documentItem.expires_at
                ? `Link berlaku sampai ${documentItem.expires_at}`
                : 'Klik untuk preview';

            button.appendChild(title);
            button.appendChild(meta);
            recruitmentDocumentsList.appendChild(button);
        });

        if (!recruitmentDocumentsList.children.length) {
            recruitmentDocumentsEmpty.classList.remove('d-none');
            return;
        }

        recruitmentDocumentsList.classList.remove('d-none');
    }

    $(document).on('click', '.js-recruitment-documents', function(event) {
        event.preventDefault();

        if (!recruitmentDocumentsModal || !recruitmentDocumentsTitle || !recruitmentDocumentsLoading || !recruitmentDocumentsError || !recruitmentDocumentsEmpty || !recruitmentDocumentsList) {
            return;
        }

        const url = this.dataset.url;
        const employeeName = this.dataset.employeeName || 'Karyawan';
        const noKtp = this.dataset.noKtp || '-';

        recruitmentDocumentsTitle.textContent = `Dokumen Recruitment - ${employeeName}`;
        resetRecruitmentDocumentsModal();
        recruitmentDocumentsModal.show();

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Request dokumen recruitment gagal.');
                }

                return response.json();
            })
            .then(function(payload) {
                recruitmentDocumentsLoading.classList.add('d-none');

                if (!payload.found) {
                    recruitmentDocumentsEmpty.textContent = payload.message || `Dokumen recruitment tidak ditemukan untuk No KTP ${noKtp}.`;
                    recruitmentDocumentsEmpty.classList.remove('d-none');
                    return;
                }

                renderRecruitmentDocuments(payload.documents || []);
            })
            .catch(function(error) {
                recruitmentDocumentsLoading.classList.add('d-none');
                recruitmentDocumentsError.textContent = error.message || 'Dokumen recruitment gagal dimuat.';
                recruitmentDocumentsError.classList.remove('d-none');
            });
    });

    $(document).on('click', '.js-document-preview, .js-external-document-preview', function(event) {
        event.preventDefault();

        if (!documentPreviewModal || !documentPreviewFrame || !documentPreviewImage || !documentPreviewDownload || !documentPreviewTitle) {
            window.open(this.dataset.previewUrl || this.href, '_blank');
            return;
        }

        const previewUrl = this.dataset.previewUrl || this.href;
        const downloadUrl = this.dataset.downloadUrl || this.href;
        const documentLabel = this.dataset.documentLabel || this.textContent.trim() || 'Dokumen';
        const documentMime = this.dataset.documentMime || '';

        if (this.classList.contains('js-external-document-preview') && recruitmentDocumentsModalEl && recruitmentDocumentsModalEl.classList.contains('show')) {
            recruitmentDocumentsModal.hide();
        }

        documentPreviewTitle.textContent = `Preview ${documentLabel}`;
        documentPreviewDownload.href = downloadUrl;
        documentPreviewFrame.src = 'about:blank';
        documentPreviewFrame.classList.add('d-none');
        documentPreviewImage.src = '';
        documentPreviewImage.classList.add('d-none');
        documentPreviewModal.show();

        window.setTimeout(function() {
            if (isPreviewImage(downloadUrl, documentMime)) {
                documentPreviewImage.src = previewUrl;
                documentPreviewImage.classList.remove('d-none');
                return;
            }

            documentPreviewFrame.classList.remove('d-none');
            documentPreviewFrame.src = previewUrl;
        }, 80);
    });

    if (documentPreviewModalEl && documentPreviewFrame && documentPreviewImage) {
        documentPreviewModalEl.addEventListener('hidden.bs.modal', function() {
            documentPreviewFrame.src = 'about:blank';
            documentPreviewFrame.classList.remove('d-none');
            documentPreviewImage.src = '';
            documentPreviewImage.classList.add('d-none');
            documentPreviewDownload.href = '#';
            documentPreviewTitle.textContent = 'Preview Dokumen';
        });
    }

    function handleKaryawanAjaxError(xhr, fallbackMessage) {
        let message = fallbackMessage || 'Request gagal diproses. Silakan coba lagi.';

        if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }

        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            const errors = xhr.responseJSON.errors;
            const firstKey = Object.keys(errors)[0];

            if (firstKey && errors[firstKey][0]) {
                message = errors[firstKey][0];
            }
        }

        if (xhr.status === 401 || xhr.status === 419) {
            message = 'Sesi login berakhir. Silakan login ulang.';
        }

        if (xhr.status === 403) {
            message = 'Anda tidak memiliki akses untuk melakukan tindakan ini.';
        }

        if (xhr.status === 404) {
            message = 'Data karyawan tidak ditemukan atau URL hapus tidak valid.';
        }

        if (xhr.status === 0) {
            message = 'Koneksi bermasalah atau request diblokir. Silakan cek jaringan Anda.';
        }

        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: message,
            confirmButtonText: 'OK'
        });
    }

    $(document).on('click', '.btn-delete', function() {
        const button = $(this);
        const url = button.data('url');
        const nama = button.data('nama');
        const originalHtml = button.html();

        if (!url) {
            handleKaryawanAjaxError({ status: 404 }, 'URL hapus karyawan tidak tersedia.');
            return;
        }

        Swal.fire({
            title: 'Yakin?',
            text: `Hapus data karyawan ${nama}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

                $.ajax({
                    url: url,
                    type: 'DELETE',
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response && response.success === false) {
                            Swal.fire('Gagal', response.message || 'Data karyawan gagal dihapus.', 'error');
                            return;
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: response.message || 'Data karyawan berhasil dihapus.',
                            confirmButtonText: 'OK'
                        }).then(function() {
                            table.ajax.reload(null, false);
                        });
                    },
                    error: function(xhr) {
                        handleKaryawanAjaxError(xhr, 'Data karyawan gagal dihapus.');
                    },
                    complete: function() {
                        button.prop('disabled', false).html(originalHtml);
                    }
                });
            }
        });
    });
</script>

<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>

@endpush

@endsection
