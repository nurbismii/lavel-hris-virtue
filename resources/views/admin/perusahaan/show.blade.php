@extends('layouts.app')

@push('styles')
<style>
    .accordion-header {
        position: relative;
    }

    .accordion-header form {
        z-index: 3;
    }

    .accordion-header .accordion-button {
        z-index: 1;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fw-bold">Detail Perusahaan</h3>
            <a href="{{ route('perusahaan.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        {{-- INFO PERUSAHAAN --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">
                Informasi Perusahaan
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <th width="25%">Kode Perusahaan</th>
                        <td>: {{ $perusahaan->kode_perusahaan }}</td>
                    </tr>
                    <tr>
                        <th>Nama Perusahaan</th>
                        <td>: {{ $perusahaan->nama_perusahaan }}</td>
                    </tr>
                    <tr>
                        <th>Alamat</th>
                        <td>: {{ $perusahaan->alamat ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Keterangan</th>
                        <td>: {{ $perusahaan->keterangan ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- DEPARTEMEN & DIVISI --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Struktur Organisasi</span>

                <div>
                    <a href="{{ route('departemen.create', $perusahaan->id) }}" target="_blank"
                        class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Departemen
                    </a>

                    <a href="{{ route('divisi.create', $perusahaan->id) }}" target="_blank"
                        class="btn btn-sm btn-success">
                        <i class="fas fa-plus"></i> Divisi
                    </a>

                    <a class="btn btn-sm btn-warning" id="btnMergeDivisi">
                        <i class="fas fa-plus"></i>
                        Merge Selected Divisi
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="accordion" id="accordionDepartemen">

                    @forelse ($perusahaan->departemen as $dept)
                    <div class="accordion-item mb-2">
                        <h2 class="accordion-header" id="heading{{ $dept->id }}">
                            <div class="d-flex align-items-center">

                                <button class="accordion-button collapsed fw-bold pe-5"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#collapse{{ $dept->id }}">

                                    <i class="fas fa-building me-2 text-primary"></i>
                                    {{ $dept->departemen }}

                                    <span class="badge bg-secondary ms-2">
                                        {{ $dept->divisi->count() }} Divisi
                                    </span>
                                </button>

                                <form action="{{ route('departemen.destroy', $dept->id) }}"
                                    method="POST"
                                    class="position-absolute end-0 me-2 form-delete-departemen">

                                    @csrf
                                    @method('DELETE')

                                    <button type="button" class="btn btn-xs btn-danger btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </h2>

                        <div id="collapse{{ $dept->id }}"
                            class="accordion-collapse collapse"
                            data-bs-parent="#accordionDepartemen">

                            <div class="accordion-body p-2">

                                @if ($dept->divisi->count())
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" id="checkAllDivisi">
                                            </th>
                                            <th>Divisi</th>
                                            <th width="150" class="text-center">Jumlah Karyawan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($dept->divisi as $div)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="check-divisi" value="{{ $div->id }}">
                                            </td>
                                            <td>
                                                <span class="divisi-text">{{ $div->nama_divisi }}</span>
                                                <input type="text" class="form-control form-control-sm divisi-input d-none" value="{{ $div->nama_divisi }}">
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info">
                                                    {{ $div->karyawan_count }}
                                                </span>
                                            </td>
                                            <td width="200">

                                                <button class="btn btn-warning btn-sm btn-edit-divisi">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <button class="btn btn-success btn-sm btn-save-divisi d-none"
                                                    data-id="{{ $div->id }}">
                                                    <i class="fas fa-check"></i>
                                                </button>

                                                <button class="btn btn-secondary btn-sm btn-cancel-divisi d-none">
                                                    <i class="fas fa-times"></i>
                                                </button>

                                                <a href="{{ route('divisi.destroy', $div->id) }}"
                                                    class="btn btn-danger btn-sm"
                                                    data-confirm-delete="true">
                                                    <i class="fas fa-trash"></i>
                                                </a>

                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @else
                                <p class="text-muted mb-0">
                                    <i class="fas fa-info-circle"></i>
                                    Belum ada divisi
                                </p>
                                @endif

                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center mb-0">
                        Belum ada departemen
                    </p>
                    @endforelse

                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMergeDivisi">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Merge Divisi</h5>
            </div>

            <div class="modal-body">

                <label>Pindahkan ke divisi</label>

                <select class="form-control" id="targetDivisi">

                    @foreach($perusahaan->departemen as $dept)

                    <optgroup label="{{ $dept->departemen }}">

                        @foreach($dept->divisi as $div)
                        <option value="{{ $div->id }}">
                            {{ $div->nama_divisi }}
                        </option>
                        @endforeach

                    </optgroup>

                    @endforeach

                </select>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Batal
                </button>

                <button class="btn btn-warning" id="confirmMerge">
                    Merge
                </button>
            </div>

        </div>
    </div>
</div>



@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        document.querySelectorAll('.form-delete-departemen .btn-delete')
            .forEach(button => {

                button.addEventListener('click', function(e) {
                    e.stopPropagation();

                    const form = this.closest('form');

                    Swal.fire({
                        title: 'Hapus Departemen?',
                        html: `
                        <small>
                            Semua <b>divisi</b> di dalam departemen ini juga akan terhapus.<br>
                            Tindakan ini <b>tidak dapat dibatalkan</b>.
                        </small>
                    `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        cancelButtonText: 'Batal',
                        confirmButtonText: 'Ya, Hapus',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

    });
</script>

<script>
    $(document).on('click', '.btn-edit-divisi', function() {

        let row = $(this).closest('tr');

        row.find('.divisi-text').addClass('d-none');
        row.find('.divisi-input').removeClass('d-none');

        row.find('.btn-edit-divisi').addClass('d-none');
        row.find('.btn-save-divisi').removeClass('d-none');
        row.find('.btn-cancel-divisi').removeClass('d-none');
    });


    $(document).on('click', '.btn-cancel-divisi', function() {

        let row = $(this).closest('tr');

        row.find('.divisi-text').removeClass('d-none');
        row.find('.divisi-input').addClass('d-none');

        row.find('.btn-edit-divisi').removeClass('d-none');
        row.find('.btn-save-divisi').addClass('d-none');
        row.find('.btn-cancel-divisi').addClass('d-none');
    });


    $(document).on('click', '.btn-save-divisi', function() {

        let btn = $(this);
        let row = btn.closest('tr');

        let id = btn.data('id');
        let nama = row.find('.divisi-input').val();

        let url = "{{ route('divisi.update', ':id') }}";
        url = url.replace(':id', id);

        $.ajax({
            url: url,
            type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                nama_divisi: nama
            },
            success: function(res) {

                row.find('.divisi-text').text(res.nama_divisi);

                row.find('.divisi-text').removeClass('d-none');
                row.find('.divisi-input').addClass('d-none');

                row.find('.btn-edit-divisi').removeClass('d-none');
                row.find('.btn-save-divisi').addClass('d-none');
                row.find('.btn-cancel-divisi').addClass('d-none');

            }
        });

    });
</script>

<script>
    let selectedDivisi = [];

    $('#btnMergeDivisi').click(function() {

        selectedDivisi = [];

        $('.check-divisi:checked').each(function() {
            selectedDivisi.push($(this).val());
        });

        if (selectedDivisi.length < 1) {
            alert('Pilih minimal 1 divisi');
            return;
        }

        $('#modalMergeDivisi').modal('show');

    });

    $('#confirmMerge').click(function() {

        let target = $('#targetDivisi').val();

        $.ajax({

            url: "{{ route('divisi.merge') }}",
            type: "POST",

            data: {

                _token: "{{ csrf_token() }}",
                source_divisi: selectedDivisi,
                target_divisi: target

            },

            success: function(res) {

                location.reload();

            }

        });

    });
</script>
@endpush

@endsection