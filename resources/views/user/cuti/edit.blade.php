@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/cuti-form.css') }}">
@endpush

@section('content')
@php

$sisaCutiAktual = (int) ($karyawan->sisa_cuti ?? 0);
$jumlahCutiLama = (int) ($cuti->jumlah ?? 0);

$maksimalCutiEdit = $sisaCutiAktual + $jumlahCutiLama;
@endphp

<div class="container-fluid leave-form-page">
    <div class="page-inner leave-form-inner">

        {{-- PAGE HEADER TANPA HERO CARD --}}
        <div class="leave-page-header mb-4">
            <div>
                <span class="page-kicker">
                    <i class="fas fa-edit"></i>
                    Form Perubahan
                </span>
                <h3 class="page-title mb-1">Edit Pengajuan Cuti</h3>
                <p class="page-subtitle mb-0">
                    Perbarui tanggal cuti dan keterangan pengajuan sesuai kebutuhan.
                </p>
            </div>

            <div class="leave-balance-box">
                <small>Cuti tersedia</small>
                <strong>{{ $maksimalCutiEdit }} hari</strong>
            </div>
        </div>

        <div class="card leave-card">
            <div class="card-body">

                <form action="{{ route('cuti.update', $cuti->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- INFORMASI KARYAWAN --}}
                    <div class="form-section mb-4">
                        <div class="section-heading">
                            <div>
                                <span class="section-kicker">
                                    <i class="fas fa-id-card"></i>
                                    Data Karyawan
                                </span>
                                <h5 class="section-title">Informasi Pemohon</h5>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">NIK</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-id-badge"></i>
                                    <input
                                        type="text"
                                        class="form-control"
                                        value="{{ $cuti->nik_karyawan }}"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Nama</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-user"></i>
                                    <input
                                        type="text"
                                        class="form-control"
                                        value="{{ $karyawan->nama_karyawan }}"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Pengajuan</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-calendar-day"></i>
                                    <input
                                        type="date"
                                        class="form-control"
                                        value="{{ $cuti->tanggal }}"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Sisa Cuti Saat Ini</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-calendar-check"></i>
                                    <input
                                        type="text"
                                        name="sisa_cuti"
                                        class="form-control"
                                        value="{{ $karyawan->sisa_cuti }}"
                                        data-max-edit-cuti="{{ $maksimalCutiEdit }}"
                                        readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- DETAIL CUTI --}}
                    <div class="form-section">
                        <div class="section-heading">
                            <div>
                                <span class="section-kicker">
                                    <i class="fas fa-calendar-alt"></i>
                                    Detail Cuti
                                </span>
                                <h5 class="section-title">Periode Pengajuan</h5>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    Tanggal Mulai
                                    <sup class="text-danger">*</sup>
                                </label>
                                <div class="input-modern">
                                    <i class="fas fa-calendar-plus"></i>
                                    <input
                                        type="date"
                                        name="tanggal_mulai"
                                        class="form-control @error('tanggal_mulai') is-invalid @enderror"
                                        value="{{ old('tanggal_mulai', $cuti->tanggal_mulai) }}"
                                        required>
                                </div>
                                @error('tanggal_mulai')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    Tanggal Berakhir
                                    <sup class="text-danger">*</sup>
                                </label>
                                <div class="input-modern">
                                    <i class="fas fa-calendar-minus"></i>
                                    <input
                                        type="date"
                                        name="tanggal_berakhir"
                                        class="form-control @error('tanggal_berakhir') is-invalid @enderror"
                                        value="{{ old('tanggal_berakhir', $cuti->tanggal_berakhir) }}"
                                        required>
                                </div>
                                @error('tanggal_berakhir')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Jumlah Hari</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-hourglass-half"></i>
                                    <input
                                        type="text"
                                        name="jumlah_hari"
                                        id="jumlah_hari"
                                        class="form-control"
                                        value="{{ old('jumlah_hari', $cuti->jumlah) }}"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="leave-info-mini">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <strong>Mode edit pengajuan</strong>
                                        <span>Jumlah cuti akan dihitung ulang otomatis berdasarkan tanggal terbaru.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Keterangan</label>
                                <div class="textarea-modern">
                                    <textarea
                                        class="form-control @error('keterangan') is-invalid @enderror"
                                        name="keterangan"
                                        rows="5"
                                        placeholder="Tuliskan alasan atau keterangan pengajuan cuti">{{ old('keterangan', $cuti->keterangan) }}</textarea>
                                </div>
                                @error('keterangan')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <div class="alert alert-danger d-none leave-alert" id="alertCuti"></div>
                            </div>

                            {{-- BUTTON --}}
                            <div class="col-md-12">
                                <div class="form-actions">
                                    <button type="submit" id="submit-cuti" class="btn btn-submit-leave">
                                        <i class="fas fa-save me-1"></i>
                                        Update Pengajuan
                                    </button>

                                    <a href="{{ route('cuti.index') }}" class="btn btn-back">
                                        <i class="fas fa-arrow-left me-1"></i>
                                        Kembali
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tanggalMulai = document.querySelector('input[name="tanggal_mulai"]');
        const tanggalBerakhir = document.querySelector('input[name="tanggal_berakhir"]');
        const jumlahHariInput = document.getElementById('jumlah_hari');
        const sisaCutiInput = document.querySelector('input[name="sisa_cuti"]');
        const alertCuti = document.getElementById('alertCuti');
        const submitBtn = document.getElementById('submit-cuti');

        const maksimalCutiEdit = parseInt(sisaCutiInput.dataset.maxEditCuti || sisaCutiInput.value || 0);

        function hitungCuti() {
            if (!tanggalMulai.value || !tanggalBerakhir.value) {
                jumlahHariInput.value = '';
                alertCuti.classList.add('d-none');
                submitBtn.disabled = false;
                return;
            }

            let start = new Date(tanggalMulai.value);
            let end = new Date(tanggalBerakhir.value);

            if (end < start) {
                jumlahHariInput.value = 0;
                alertCuti.classList.remove('d-none');
                alertCuti.innerText = 'Tanggal berakhir tidak boleh sebelum tanggal mulai!';
                submitBtn.disabled = true;
                return;
            }

            let selisih = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;

            jumlahHariInput.value = selisih;

            if (selisih > maksimalCutiEdit) {
                alertCuti.classList.remove('d-none');
                alertCuti.innerText = 'Cuti tidak cukup! Maksimal cuti yang dapat diajukan adalah ' + maksimalCutiEdit + ' hari.';
                submitBtn.disabled = true;
            } else {
                alertCuti.classList.add('d-none');
                submitBtn.disabled = false;
            }
        }

        tanggalMulai.addEventListener('change', hitungCuti);
        tanggalBerakhir.addEventListener('change', hitungCuti);

        hitungCuti();
    });
</script>
@endpush