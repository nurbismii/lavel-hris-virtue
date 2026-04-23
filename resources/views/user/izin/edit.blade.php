@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-izin.css') }}">
@endpush

@section('content')
@php
    $selectedTipe = old('tipe', $izin->tipe);
    $selectedTipe = in_array($selectedTipe, ['PAID', 'UNPAID']) ? $selectedTipe : null;
@endphp

<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-edit text-primary me-2"></i>
                    Edit pengajuan izin
                </h4>
                <small class="text-muted">
                    Perbarui izin berbayar atau tidak berbayar yang masih pending
                </small>
            </div>

            <a href="{{ route('izin.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-long-arrow-alt-left me-1"></i> Kembali
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="{{ route('izin.update', $izin->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-bold">Jenis Izin</label>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe" value="PAID" id="paidRadio" {{ $selectedTipe === 'PAID' ? 'checked' : '' }}>
                            <label class="form-check-label" for="paidRadio">
                                Izin Berbayar
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe" value="UNPAID" id="unpaidRadio" {{ $selectedTipe === 'UNPAID' ? 'checked' : '' }}>
                            <label class="form-check-label" for="unpaidRadio">
                                Izin Tidak Berbayar
                            </label>
                        </div>
                    </div>

                    <div id="paidOptions" class="{{ $selectedTipe === 'PAID' ? '' : 'izin-paid-options-hidden' }}">
                        <label class="form-label fw-bold">Kategori Izin Berbayar</label>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin" value="Izin Menikah ( 3 Hari )" {{ old('tipe_izin') === 'Izin Menikah ( 3 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                Izin Menikah ( 3 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin" value="Izin menikahkan anak ( 2 Hari )" {{ old('tipe_izin') === 'Izin menikahkan anak ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                Izin menikahkan anak ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin" value="Izin Khitan / Baptis anak ( 2 Hari )" {{ old('tipe_izin') === 'Izin Khitan / Baptis anak ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                Izin Khitan / Baptis anak ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin" value="Izin istri melahirkan / Keguguran ( 2 Hari )" {{ old('tipe_izin') === 'Izin istri melahirkan / Keguguran ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                Izin istri melahirkan / Keguguran ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin" value="Izin Duka keluarga ( 2 Hari )" {{ old('tipe_izin') === 'Izin Duka keluarga ( 2 Hari )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                Izin Duka keluarga ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin" value="Cuti melahirkan ( 3 Bulan )" {{ old('tipe_izin') === 'Cuti melahirkan ( 3 Bulan )' ? 'checked' : '' }}>
                            <label class="form-check-label">
                                Cuti melahirkan ( 3 Bulan )
                            </label>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" class="form-control" value="{{ old('tanggal_mulai', $izin->tanggal_mulai) }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tanggal Berakhir</label>
                            <input type="date" name="tanggal_berakhir" class="form-control" value="{{ old('tanggal_berakhir', $izin->tanggal_berakhir) }}">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control">{{ old('keterangan', $izin->keterangan) }}</textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Upload Bukti (Opsional)</label>
                        <input type="file" name="foto" class="form-control">
                        @if($izin->foto && $izin->foto !== '-')
                        <small class="d-block mt-2">
                            Bukti saat ini:
                            <a href="{{ asset($izin->foto) }}" target="_blank">Lihat file</a>
                        </small>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const paidRadio = document.getElementById('paidRadio');
    const unpaidRadio = document.getElementById('unpaidRadio');
    const paidOptions = document.getElementById('paidOptions');
    const paidCategoryInputs = document.querySelectorAll('input[name="tipe_izin"]');

    const syncPaidOptions = function() {
        const showPaidOptions = paidRadio.checked;

        paidOptions.classList.toggle('izin-paid-options-hidden', !showPaidOptions);

        if (!showPaidOptions) {
            paidCategoryInputs.forEach(function(input) {
                input.checked = false;
            });
        }
    };

    paidRadio.addEventListener('change', syncPaidOptions);
    unpaidRadio.addEventListener('change', syncPaidOptions);
    syncPaidOptions();
</script>
@endsection
