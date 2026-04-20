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

    .upload-progress-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.04);
        height: 100%;
    }

    .upload-progress-card__meta {
        color: #64748b;
        font-size: 0.82rem;
    }

    .upload-progress-card__counts {
        font-size: 0.82rem;
        color: #475569;
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

        @if($canManageMasterData)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                        <div>
                            <h5 class="mb-1 fw-semibold">Progress Upload Dokumen</h5>
                            <small class="text-muted">Status import ZIP diperbarui otomatis setiap 5 detik.</small>
                        </div>
                        <span class="badge bg-light text-dark border">Queue {{ config('queue.connections.' . config('queue.default') . '.queue', 'default') }}</span>
                    </div>
                    <div id="employee-upload-progress-list" data-progress-url="{{ route('karyawan.upload-progress') }}">
                        @include('admin.karyawan.partials.upload-progress-cards', ['items' => $uploadProgressStatuses, 'emptyMessage' => 'Belum ada progress upload dokumen yang berjalan atau baru selesai.'])
                    </div>
                </div>
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
                <form action="{{ route('karyawan.bulk-upload-documents') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-light border small">
                            Upload satu file ZIP per jenis dokumen.
                            Isi ZIP harus memakai nama file yang mengandung NIK karyawan, misalnya <code>2200112233.jpg</code> atau <code>2200112233.pdf</code>.
                            ZIP akan diproses di background dan hasil akhirnya dikirim ke notifikasi.
                        </div>

                        <div class="alert alert-warning small">
                            Batas upload ZIP dari aplikasi ini disiapkan sampai sekitar <code>500MB</code> per file ZIP. Pastikan worker queue aktif agar proses berjalan di background.
                        </div>

                        <div class="alert alert-info small mb-0">
                            <div class="fw-semibold mb-1">Panduan cepat</div>
                            <div>Queue aktif saat ini: <code>{{ config('queue.default') }}</code></div>
                            <div>Nama queue saat ini: <code>{{ config('queue.connections.' . config('queue.default') . '.queue', 'default') }}</code></div>
                            <div>Perintah worker yang disarankan: <code>php artisan queue:work --queue={{ config('queue.connections.' . config('queue.default') . '.queue', 'default') }} --tries=1 --timeout=300</code></div>
                            <div>Chunk ZIP yang disarankan: <code>EMPLOYEE_MEDIA_ZIP_CHUNK_SIZE=5</code></div>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Upload ZIP</button>
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

@if($canManageMasterData)
<script>
    (function() {
        const container = document.getElementById('employee-upload-progress-list');

        if (!container) {
            return;
        }

        const progressUrl = container.dataset.progressUrl;

        function statusBadge(item) {
            return `<span class="badge bg-${item.status_class}">${item.status_label}</span>`;
        }

        function renderItems(items) {
            if (!Array.isArray(items) || items.length === 0) {
                container.innerHTML = '<div class="alert alert-light border mb-0 small text-muted">Belum ada progress upload dokumen yang berjalan atau baru selesai.</div>';
                return;
            }

            container.innerHTML = `<div class="row g-3">${items.map((item) => `
                <div class="col-md-6 col-xl-4">
                    <div class="upload-progress-card p-3">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">${item.label}</div>
                                <div class="upload-progress-card__meta">Update ${item.updated_at_human}</div>
                            </div>
                            ${statusBadge(item)}
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar bg-${item.status_class}" role="progressbar" style="width: ${item.progress_percentage}%;" aria-valuenow="${item.progress_percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between upload-progress-card__counts">
                            <span>${item.processed_entries}/${item.total_entries || 0} file</span>
                            <span>${item.progress_percentage}%</span>
                        </div>
                        <div class="upload-progress-card__meta mt-2">
                            Berhasil ${item.success_count} file, dilewati ${item.skipped_count} file.
                        </div>
                    </div>
                </div>
            `).join('')}</div>`;
        }

        async function refreshProgress() {
            try {
                const response = await fetch(progressUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                renderItems(data.items || []);
            } catch (error) {
                console.error('Gagal memuat progress upload dokumen.', error);
            }
        }

        window.setInterval(refreshProgress, 5000);
    })();
</script>
@endif

@endpush

@endsection
