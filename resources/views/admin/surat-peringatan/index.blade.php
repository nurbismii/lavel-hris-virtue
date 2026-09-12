@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex align-items-center justify-content-between pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-file-alt text-primary me-2"></i>
                    {{ __('Data Pelanggaran') }}
                </h4>
                <small class="text-muted">
                    {{ __('Daftar pelanggaran karyawan yang tercatat dalam sistem. Kelola data pelanggaran dengan mudah dan efisien.') }}
                </small>
            </div>

            <div class="ms-md-auto py-2 py-md-0 d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-primary" href="{{ route('warning-letter-requests.create') }}"><i class="fas fa-plus me-1"></i> {{ __('Buat Pelanggaran') }}</a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('warning-letter-requests.index') }}">{{ __('Pengajuan & Approval') }}</a>
                @if(auth()->user()->hasRole(['Super Admin', 'HR']))
                <a class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportSuratPeringatan">
                    {{ __('Bulk Pelanggaran') }}
                </a>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">

                {{-- FILTER --}}
                <div class="row mb-3 g-2">
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('Tanggal mulai') }}</label>
                        <input type="date" id="tgl_mulai" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">{{ __('Tanggal Berakhir') }}</label>
                        <input type="date" id="tgl_berakhir" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">{{ __('Level Surat Peringatan') }}</label>
                        <select id="level_sp" class="form-select form-control-sm">
                            <option value="">{{ __('-- Semua level surat peringatan --') }}</option>
                            <option value="SP1">{{ __('Surat Peringatan 1') }}</option>
                            <option value="SP2">{{ __('Surat Peringatan 2') }}</option>
                            <option value="SP3">{{ __('Surat Peringatan 3') }}</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button id="btnFilter" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter"></i> {{ __('Terapkan Filter') }}
                        </button>
                    </div>
                </div>

                {{-- TABLE --}}
                <div class="table-responsive">
                    <table id="table-surat-peringatan"
                        class="table table-bordered table-striped table-sm nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('tables.nik') }}</th>
                                <th>{{ __('tables.name') }}</th>
                                <th>{{ __('tables.warning_letter') }}</th>
                                <th>{{ __('tables.start') }}</th>
                                <th>{{ __('tables.end') }}</th>
                                <th width="120">{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportSuratPeringatan" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalImportSuratPeringatanLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalImportSuratPeringatanLabel">{{ __('Import Surat Peringatan') }}</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <form action="{{ route('surat-peringatan.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="col-md-6 col-lg-6">
                        <div class="form-group">
                            <label for="exampleFormControlFile1">{{ __('Pilih file excel') }}</label>
                            <input type="file" name="file" class="form-control-file" id="exampleFormControlFile1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Tutup') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Import') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let table = $('#table-surat-peringatan').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,

        ajax: {
            url: "{{ route('surat-peringatan.index') }}",
            data: function(d) {
                d.tgl_mulai = $('#tgl_mulai').val();
                d.tgl_berakhir = $('#tgl_berakhir').val();
                d.level_sp = $('#level_sp').val();
            }
        },
        columns: [{
                data: 'nik_karyawan',
                name: 'nik_karyawan'
            },
            {
                data: 'nama_karyawan',
                name: 'employee.nama_karyawan'
            },
            {
                data: 'level_sp',
                name: 'level_sp'
            },
            {
                data: 'tgl_mulai',
                name: 'tgl_mulai'
            },
            {
                data: 'tgl_berakhir',
                name: 'tgl_berakhir'
            },
            {
                data: 'aksi',
                orderable: false,
                searchable: false
            }
        ],
        order: [
            [3, 'desc']
        ] // kolom index 1, urut terbaru dulu
    });

    /* reload saat filter berubah */
    $('#btnFilter').on('click', function() {
        table.ajax.reload();
    });

    $('#level_sp').on('change', function() {
        table.ajax.reload();
    });
</script>

<script>
    $(document).on('click', '.btn-delete', function() {

        let id = $(this).data('id');
        let nama = $(this).data('nama');

        Swal.fire({
            title: @json(__('Yakin hapus data?')),
            html: `Data resign <b>${nama}</b> akan dihapus.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: @js(__('Ya, hapus!')),
            cancelButtonText: @json(__('Batal'))
        }).then((result) => {

            if (result.isConfirmed) {

                let url = '{{ route("surat-peringatan.destroy", ":id") }}';
                url = url.replace(':id', id);

                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function() {

                        Swal.fire({
                            icon: 'success',
                            title: @json(__('Berhasil!')),
                            text: @json(__('Data berhasil dihapus.')),
                            timer: 1500,
                            showConfirmButton: false
                        });

                        $('#table-surat-peringatan').DataTable().ajax.reload(null, false);
                    },
                    error: function() {
                        Swal.fire(
                            'Gagal!',
                            'Terjadi kesalahan saat menghapus data.',
                            'error'
                        );
                    }
                });
            }
        });
    });
</script>


@endpush

@endsection
