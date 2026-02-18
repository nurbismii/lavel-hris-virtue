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
<div class="container">
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
                                            <th>Divisi</th>
                                            <th width="150" class="text-center">Jumlah Karyawan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($dept->divisi as $div)
                                        <tr>
                                            <td>{{ $div->nama_divisi }}</td>
                                            <td class="text-center">
                                                <span class="badge bg-info">
                                                    {{ $div->karyawan_count }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('divisi.destroy', $div->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-trash"></i>
                                                    </span>
                                                    <span class="text">Hapus</span>
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
@endpush

@endsection