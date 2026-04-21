@extends('layouts.app')

@push('styles')
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
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
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
            <div class="ms-md-auto py-2 py-md-0 d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalBulkDocuments">
                    Bulk Dokumen
                </a>
                <a class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportEmployee">
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

            <div class="card">
                <div class="card-body">
                    <div class="table">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <select id="filter_area" class="form-select">
                                    <option value="">Semua Area</option>
                                    @foreach ($areas as $area)
                                    <option value="{{ $area->kode_perusahaan }}">{{ $area->kode_perusahaan }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
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

                            <div class="col-md-3">
                                <select id="filter_divisi" class="form-select">
                                    <option value="">Semua Divisi</option>
                                    @foreach ($divisis as $v)
                                    <option value="{{ $v->id }}">{{ $v->nama_divisi }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
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
                        Isi ZIP harus memakai nama file yang mengandung NIK karyawan, misalnya <code>2200112233.jpg</code> atau <code>2200112233.pdf</code>.
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

                    const errorMessage = payload.message
                        || (payload.errors ? Object.values(payload.errors).flat().join(' ') : '')
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
    let table = $('#multi-filter-select').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,

        dom: "<'row mb-2'<'col-md-6'l><'col-md-6 text-end'f>>" +
            "<'table-scroll-wrapper'tr>" +
            "<'row mt-2'<'col-md-6'i><'col-md-6 text-end'p>>",

        ajax: {
            url: "{{ route('karyawan.index') }}",
            data: function(d) {
                d.area = $('#filter_area').val();
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
                data: 'aksi',
                orderable: false,
                searchable: false
            },
        ]
    });

    // AREA berubah
    $('#filter_area').on('change', function() {
        let area = $(this).val();
        $('#filter_departemen').html('<option value="">Loading...</option>');
        $('#filter_divisi').html('<option value="">Semua Divisi</option>');

        if (!area) {
            $('#filter_departemen').html('<option value="">Semua Departemen</option>');
            table.draw();
            return;
        }

        $.get("{{ route('ajax.departemen.by.area') }}", {
            area
        }, function(res) {
            let opt = '<option value="">Semua Departemen</option>';
            res.forEach(r => {
                opt += `<option value="${r.id}">${r.departemen}</option>`;
            });
            $('#filter_departemen').html(opt);
            table.draw();
        });
    });

    // DEPARTEMEN berubah
    $('#filter_departemen').on('change', function() {
        let departemen = $(this).val();

        $('#filter_divisi').html('<option value="">Loading...</option>');

        if (!departemen) {
            $('#filter_divisi').html('<option value="">Semua Divisi</option>');
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
            $('#filter_divisi').html(opt);
            table.draw();
        });
    });

    // DIVISI & STATUS
    $('#filter_divisi, #filter_resign').on('change', function() {
        table.draw();
    });

    $('#filter_departemen, #filter_divisi').prop('disabled', true);

    $('#filter_area').on('change', function() {
        $('#filter_departemen').prop('disabled', !this.value);
        $('#filter_divisi').prop('disabled', true);
    });

    $('#filter_departemen').on('change', function() {
        $('#filter_divisi').prop('disabled', !this.value);
    });

    $(document).on('click', '.btn-delete', function() {
        let id = $(this).data('id');
        let nama = $(this).data('nama');

        Swal.fire({
            title: 'Yakin?',
            text: `Hapus data karyawan ${nama}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `admin/karyawan/${id}`,
                    type: 'DELETE',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function() {
                        Swal.fire('Berhasil', 'Data dihapus', 'success');
                        table.ajax.reload(null, false);
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
