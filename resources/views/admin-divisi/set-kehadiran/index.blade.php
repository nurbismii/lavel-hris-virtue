@extends('layouts.app')

@push('styles')
<style>
    .attendance-select {
        min-width: 85px !important;
        font-size: 12px;
        padding: 2px 6px;
    }

    .attendance-select option {
        color: #000;
    }

    .dataTables_filter {
        position: sticky;
        top: 0;
        background: white;
        z-index: 1050;
        padding: 10px 0;
    }

    .dataTables_paginate {
        position: sticky;
        bottom: 0;
        background: white;
        z-index: 1050;
        padding: 10px 0;
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

<link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.4.0/css/fixedHeader.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-cog text-primary me-2"></i>
                    Setting Hari Off
                </h4>

                <small class="text-muted d-block">
                    Setting kehadiran: jika tercentang maka OFF, jika tidak dicentang maka HADIR.
                    (Cut Off {{ formatDateIndonesia($start) }} - {{ formatDateIndonesia($end) }})
                </small>
                <small class="text-muted d-block">Checked = OFF</small>
            </div>

            <div class="ms-md-auto pt-3 pt-md-0">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBulkFaceReference">
                    Bulk Foto Referensi Presensi
                </button>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Periode</label>
                <input type="month" name="periode" value="{{ $periode }}" class="form-control">
            </div>

            @if($isDepartmentReadonly)
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <input type="text" class="form-control" value="{{ optional($departemen)->departemen ?? '-' }}" readonly>
            </div>
            @else
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <select id="filter_departemen" name="departemen" class="form-select form-control">
                    <option value="">Pilih Departemen</option>
                    @php
                    $groupedDepts = [];
                    foreach ($departemens as $dept) {
                    $groupedDepts[optional($dept->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $dept;
                    }
                    @endphp

                    @foreach($groupedDepts as $perusahaan => $deptItems)
                    <optgroup label="{{ $perusahaan }}">
                        @foreach($deptItems as $dept)
                        <option value="{{ $dept->id }}" {{ (string) $selectedDepartemenId === (string) $dept->id ? 'selected' : '' }}>
                            {{ $dept->departemen }}
                        </option>
                        @endforeach
                    </optgroup>
                    @endforeach
                </select>
            </div>
            @endif

            @if($isDivisionReadonly)
            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <input type="text" class="form-control" value="{{ optional($divisis->first())->nama_divisi ?? '-' }}" readonly>
                <input type="hidden" id="filter_divisi" name="divisi" value="{{ $selectedDivisiId }}">
            </div>
            @else
            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <select id="filter_divisi" name="divisi" class="form-select form-control" {{ !$selectedDepartemenId ? 'disabled' : '' }}>
                    <option value="">Semua Divisi</option>
                    @foreach ($divisis as $v)
                    <option value="{{ $v->id }}" {{ (string) $selectedDivisiId === (string) $v->id ? 'selected' : '' }}>
                        {{ $v->nama_divisi }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">
                    Tampilkan
                </button>
            </div>
        </form>

        @if($isDivisionScoped && !$isDivisionReadonly)
        <div class="alert alert-light border small">
            Akun Admin Divisi ini memiliki akses ke beberapa divisi. Pilih divisi yang ingin ditampilkan pada periode ini.
        </div>
        @endif

        @if(!$selectedDepartemenId)
        <div class="alert alert-info">
            Pilih departemen terlebih dahulu untuk menampilkan setting hari off.
        </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="table-set-kehadiran" class="table table-hover table-striped mb-0 table-xs small text-sm nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>NIK</th>
                                <th>Divisi</th>
                                <th>Departemen</th>
                                <th>Posisi</th>

                                @foreach($dates as $date)
                                <th class="text-center">
                                    <div>{{ $date->format('d') }}</div>
                                    <div style="font-size:11px; color:#666;">
                                        {{ $date->translatedFormat('D') }}
                                    </div>
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $index => $employee)
                            <tr>
                                <td>{{ ++$index }}</td>
                                <td>{{ $employee->nama_karyawan }}</td>
                                <td>{{ $employee->nik }}</td>
                                <td>{{ optional($employee->divisi)->nama_divisi ?? '-' }}</td>
                                <td>{{ optional($employee->departemen)->departemen ?? '-' }}</td>
                                <td>{{ optional($employee)->posisi ?? '-' }}</td>

                                @foreach($dates as $date)
                                @php
                                $empAttendance = $offData->get($employee->nik);
                                $isOff = $empAttendance
                                ? $empAttendance->firstWhere('tanggal', $date->toDateString())
                                : null;
                                $checked = $isOff ? 'checked' : '';
                                @endphp

                                <td class="text-center">
                                    <input type="checkbox" class="attendance-checkbox" data-employee="{{ $employee->nik }}" data-date="{{ $date->toDateString() }}" {{ $checked }}>
                                </td>
                                @endforeach
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ 6 + count($dates) }}" class="text-center text-muted py-4">
                                    {{ $selectedDepartemenId ? 'Tidak ada data karyawan untuk filter yang dipilih.' : 'Pilih departemen untuk mulai menampilkan data.' }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBulkFaceReference" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalBulkFaceReferenceLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalBulkFaceReferenceLabel">Bulk Upload Foto Referensi Presensi</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('set-kehadiran.bulk-upload-face-reference') }}" method="POST" enctype="multipart/form-data" class="js-bulk-upload-form" data-redirect-url="{{ route('set-kehadiran.index', request()->only(['periode', 'departemen', 'divisi'])) }}">
                @csrf
                <input type="hidden" name="periode" value="{{ $periode }}">
                <input type="hidden" name="departemen" value="{{ $selectedDepartemenId }}">
                <input type="hidden" name="divisi" value="{{ $selectedDivisiId }}">

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

                    <label class="form-label">Pilih ZIP Foto Referensi</label>
                    <input type="file" name="face_reference_zip" class="form-control" accept=".zip,application/zip" required>
                    <small class="text-muted d-block mt-2">
                        Satu ZIP ini hanya akan dipasangkan ke karyawan dalam scope akses Anda. Maksimal sekitar 500MB per ZIP.
                    </small>

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

@push('scripts')
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
    $(document).ready(function() {
        $('#table-set-kehadiran').DataTable({
            processing: true,
            scrollX: true,
            scrollY: "65vh",
            scrollCollapse: true,
            paging: true,
            fixedHeader: true,
            fixedColumns: {
                leftColumns: 3
            },
            pageLength: 10,
            ordering: false
        });

        const filterDepartemen = $('#filter_departemen');
        const filterDivisi = $('#filter_divisi');

        if (filterDepartemen.length) {
            filterDepartemen.on('change', function() {
                const departemen = $(this).val();

                filterDivisi.prop('disabled', true).html('<option value="">Loading...</option>');

                if (!departemen) {
                    filterDivisi.html('<option value="">Semua Divisi</option>').prop('disabled', true);
                    return;
                }

                $.get("{{ route('ajax.divisi.by.departemen') }}", {
                    departemen: departemen
                }).done(function(response) {
                    let options = '<option value="">Semua Divisi</option>';

                    response.forEach(function(item) {
                        options += `<option value="${item.id}">${item.nama_divisi}</option>`;
                    });

                    filterDivisi.html(options).prop('disabled', false);
                }).fail(function() {
                    filterDivisi.html('<option value="">Gagal memuat divisi</option>').prop('disabled', true);
                });
            });
        }
    });
</script>

<script>
    let dirtyCells = new Map();
    let debounceTimer = null;

    $(document).on('change', '.attendance-checkbox', function() {
        let checkbox = $(this);

        let employee = checkbox.data('employee');
        let date = checkbox.data('date');

        let newStatus = checkbox.is(':checked') ? 'OFF' : 'HADIR';
        let oldStatus = checkbox.data('status');

        if (newStatus === oldStatus) return;

        let key = employee + '_' + date;

        dirtyCells.set(key, {
            employee_id: employee,
            tanggal: date,
            status: newStatus,
            element: checkbox
        });

        checkbox.closest('td').addClass('table-warning');

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(sendBatch, 700);
    });

    async function sendBatch() {
        let payload = Array.from(dirtyCells.values());

        if (payload.length === 0) return;

        try {
            let response = await fetch("{{ route('set-kehadiran.update') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({
                    data: payload.map(p => ({
                        employee_id: p.employee_id,
                        tanggal: p.tanggal,
                        status: p.status
                    }))
                })
            });

            if (!response.ok) throw new Error();

            payload.forEach(item => {
                item.element.data('status', item.status);
                item.element.closest('td').removeClass('table-warning')
                    .addClass('table-success');

                setTimeout(() => {
                    item.element.closest('td').removeClass('table-success');
                }, 800);
            });

            dirtyCells.clear();
        } catch (e) {
            payload.forEach(item => {
                let oldStatus = item.element.data('status');

                item.element.prop('checked', oldStatus === 'OFF');
                item.element.closest('td').removeClass('table-warning');
            });

            alert('Update gagal');
        }
    }
</script>

<script src="https://cdn.datatables.net/fixedheader/3.4.0/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
@endpush

@endsection
