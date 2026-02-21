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
</style>
@endpush

@section('content')
<div class="container">

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

            <div class="ms-md-auto py-2 py-md-0">
                <a class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportEmployee">
                    Bulk Karyawan
                </a>
            </div>
        </div>

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
                                    $groupedDepts[$d->perusahaan['nama_perusahaan']][] = $d;
                                    }
                                    @endphp

                                    @foreach($groupedDepts as $perusahaan => $departemens)
                                    <optgroup label="{{ $perusahaan }}">
                                        @foreach($departemens as $d)
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
                                    <option value="">Semua Status</option>
                                    <option value="AKTIF">Aktif</option>
                                    <option value="RESIGN SESUAI PROSEDUR">Resign Sesuai Prosedur</option>
                                    <option value="RESIGN TIDAK SESUAI PROSEDUR">Resign Tidak Sesuai Prosedur</option>
                                    <option value="PUTUS KONTRAK">Putus Kontrak</option>
                                    <option value="PHK">PHK</option>
                                    <option value="PHK PENSIUN">PHK Pensiun</option>
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

@endpush

@endsection