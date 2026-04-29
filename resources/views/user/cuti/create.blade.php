@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/cuti-form.css') }}">
@endpush

@section('content')
<div class="container-fluid leave-form-page">
    <div class="page-inner leave-form-inner">

        {{-- PAGE HEADER TANPA HERO CARD --}}
        <div class="leave-page-header mb-4">
            <div>
                <span class="page-kicker">
                    <i class="fas fa-umbrella-beach"></i>
                    Form Pengajuan
                </span>
                <h3 class="page-title mb-1">Pengajuan Cuti</h3>
                <p class="page-subtitle mb-0">
                    Lengkapi tanggal cuti dan keterangan pengajuan dengan benar.
                </p>
            </div>

            <div class="leave-balance-box">
                <small>Cuti tersedia</small>
                <strong>{{ $karyawan->sisa_cuti }} hari</strong>
            </div>
        </div>

        <div class="card leave-card">
            <div class="card-body">

                <form action="{{ route('cuti.store') }}" method="POST">
                    @csrf

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
                                        name="nik_karyawan"
                                        class="form-control"
                                        value="{{ $karyawan->nik }}"
                                        readonly
                                        required>
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
                                        readonly
                                        required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Pengajuan</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-calendar"></i>
                                    <input
                                        type="date"
                                        name="tanggal"
                                        class="form-control"
                                        value="{{ old('tanggal', now()->format('Y-m-d')) }}"
                                        readonly
                                        required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Cuti Tersedia</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-calendar-check"></i>
                                    <input
                                        type="text"
                                        name="sisa_cuti"
                                        class="form-control"
                                        value="{{ $karyawan->sisa_cuti }}"
                                        readonly
                                        required>
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
                                    Tanggal Mulai Cuti
                                    <sup class="text-danger">*</sup>
                                </label>
                                <div class="input-modern">
                                    <i class="fas fa-calendar-plus"></i>
                                    <input
                                        type="date"
                                        name="tanggal_mulai"
                                        class="form-control @error('tanggal_mulai') is-invalid @enderror"
                                        value="{{ old('tanggal_mulai') }}"
                                        required>
                                </div>
                                @error('tanggal_mulai')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">
                                    Tanggal Berakhir Cuti
                                    <sup class="text-danger">*</sup>
                                </label>
                                <div class="input-modern">
                                    <i class="fas fa-calendar-minus"></i>
                                    <input
                                        type="date"
                                        name="tanggal_berakhir"
                                        class="form-control @error('tanggal_berakhir') is-invalid @enderror"
                                        value="{{ old('tanggal_berakhir') }}"
                                        required>
                                </div>
                                @error('tanggal_berakhir')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Jumlah Pengajuan Cuti</label>
                                <div class="input-modern readonly">
                                    <i class="fas fa-hourglass-half"></i>
                                    <input
                                        type="text"
                                        name="jumlah_hari"
                                        id="jumlah_hari"
                                        class="form-control"
                                        value="{{ old('jumlah_hari') }}"
                                        placeholder="Otomatis dihitung"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="leave-info-mini">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <strong>Perhitungan otomatis</strong>
                                        <span>Jumlah cuti dihitung dari tanggal mulai sampai tanggal berakhir.</span>
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
                                        placeholder="Tuliskan alasan atau keterangan pengajuan cuti">{{ old('keterangan') }}</textarea>
                                </div>
                                @error('keterangan')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <div class="alert alert-danger d-none leave-alert" id="alertCuti">
                                    Jumlah cuti melebihi sisa cuti yang tersedia!
                                </div>
                            </div>

                            {{-- BUTTON --}}
                            <div class="col-md-12">
                                <div class="form-actions">
                                    <button type="submit" id="submit-cuti" class="btn btn-submit-leave">
                                        <i class="fas fa-save me-1"></i>
                                        Buat Pengajuan
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
        const sisaCuti = parseInt(document.querySelector('input[name="sisa_cuti"]').value || 0);
        const alertCuti = document.getElementById('alertCuti');
        const submitBtn = document.getElementById('submit-cuti');

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

            if (selisih > sisaCuti) {
                alertCuti.classList.remove('d-none');
                alertCuti.innerText = 'Cuti tidak cukup! Sisa cuti hanya ' + sisaCuti + ' hari.';
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