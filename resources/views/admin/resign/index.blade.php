@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="d-flex align-items-center justify-content-between pt-2 pb-4">
            <h3 class="fw-bold mb-0">Data Resign</h3>

            <div class="ms-md-auto py-2 py-md-0">
                <a class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportResign">
                    Bulk Resign
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">

                {{-- FILTER --}}
                <div class="row mb-3 g-2">
                    <div class="col-md-3">
                        <label class="form-label small">Periode Awal</label>
                        <input type="date" id="periode_awal" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">Periode Akhir</label>
                        <input type="date" id="periode_akhir" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">Tipe Resign</label>
                        <select id="tipe" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="RESIGN SESUAI PROSEDUR">Resign Sesuai Prosedur</option>
                            <option value="RESIGN TIDAK SESUAI PROSEDUR">Resign Tidak Sesuai Prosedur</option>
                            <option value="PUTUS KONTRAK">Putus Kontrak</option>
                            <option value="PHK">PHK</option>
                            <option value="PHK PENSIUN">PHK Pensiun</option>
                            <option value="PHK PIDANA">PHK Pidana</option>
                            <option value="PHK MENINGGAL DUNIA">PHK Meninggal Dunia</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button id="btnFilter" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                    </div>
                </div>

                {{-- TABLE --}}
                <div class="table-responsive">
                    <table id="table-resign"
                        class="table table-bordered table-striped table-sm nowrap w-100">
                        <thead class="table-light">
                            <tr>
                                <th>NIK</th>
                                <th>Nama</th>
                                <th>Tanggal Resign</th>
                                <th>Tipe</th>
                                <th>Periode Awal</th>
                                <th>Periode Akhir</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImportResign" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalImportResignLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="modalImportResignLabel">Import Resign</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('resign.store') }}" method="POST" enctype="multipart/form-data">
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
<script>
    let table = $('#table-resign').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
            url: "{{ route('resign.index') }}",
            data: function(d) {
                d.periode_awal = $('#periode_awal').val();
                d.periode_akhir = $('#periode_akhir').val();
                d.tipe = $('#tipe').val();
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
                data: 'tanggal_keluar',
                name: 'tanggal_keluar'
            },
            {
                data: 'tipe',
                name: 'tipe'
            },
            {
                data: 'periode_awal',
                name: 'periode_awal'
            },
            {
                data: 'periode_akhir',
                name: 'periode_akhir'
            },
            {
                data: 'aksi',
                orderable: false,
                searchable: false
            }
        ],
        order: [
            [2, 'desc']
        ] // kolom index 1, urut terbaru dulu
    });

    /* reload saat filter berubah */
    $('#btnFilter').on('click', function() {
        table.ajax.reload();
    });

    $('#tipe').on('change', function() {
        table.ajax.reload();
    });
</script>

<script>
    $(document).on('click', '.btn-delete', function() {

        let id = $(this).data('id');
        let nama = $(this).data('nama');

        Swal.fire({
            title: 'Yakin hapus data?',
            html: `Data resign <b>${nama}</b> akan dihapus.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {

            if (result.isConfirmed) {

                let url = '{{ route("resign.destroy", ":id") }}';
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
                            title: 'Berhasil!',
                            text: 'Data berhasil dihapus.',
                            timer: 1500,
                            showConfirmButton: false
                        });

                        $('#table-resign').DataTable().ajax.reload(null, false);
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