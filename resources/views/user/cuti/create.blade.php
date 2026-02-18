@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">Pengajuan Cuti</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('cuti.store') }}" method="POST">
                    @csrf
                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIK</label>
                            <input type="text" name="nik_karyawan" class="form-control" value="{{ $karyawan->nik }}" readonly required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama</label>
                            <input type="text" class="form-control" value="{{ $karyawan->nama_karyawan }}" readonly required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal pengajuan</label>
                            <input type="date" name="tanggal" class="form-control" value="{{ now()->format('Y-m-d') }}" readonly required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cuti tersedia</label>
                            <input type="text" name="sisa_cuti" class="form-control" value="{{ $karyawan->sisa_cuti }}" readonly required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal mulai cuti</label>
                            <input type="date" name="tanggal_mulai" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal berakhir cuti</label>
                            <input type="date" name="tanggal_berakhir" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jumlah pengajuan cuti</label>
                            <input type="text" name="jumlah_hari" id="jumlah_hari" class="form-control" readonly>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan" rows="5"></textarea>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-danger d-none text-danger" id="alertCuti">
                                Jumlah cuti melebihi sisa cuti yang tersedia!
                            </div>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" id="submit-cuti" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Buat
                            </button>
                            <a href="{{ route('cuti.index') }}" class="btn btn-secondary">
                                Kembali
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {

        const tanggalMulai = document.querySelector('input[name="tanggal_mulai"]');
        const tanggalBerakhir = document.querySelector('input[name="tanggal_berakhir"]');
        const jumlahHariInput = document.getElementById('jumlah_hari');
        const sisaCuti = parseInt(document.querySelector('input[name="sisa_cuti"]').value);
        const alertCuti = document.getElementById('alertCuti');
        const submitBtn = document.getElementById('submit-cuti');

        function hitungCuti() {
            if (tanggalMulai.value && tanggalBerakhir.value) {

                let start = new Date(tanggalMulai.value);
                let end = new Date(tanggalBerakhir.value);

                if (end < start) {
                    jumlahHariInput.value = 0;
                    alertCuti.classList.remove('d-none');
                    alertCuti.innerText = "Tanggal berakhir tidak boleh sebelum tanggal mulai!";
                    submitBtn.disabled = true;
                    return;
                }

                let selisih = Math.floor((end - start) / (1000 * 60 * 60 * 24)) + 1;

                jumlahHariInput.value = selisih;

                if (selisih > sisaCuti) {
                    alertCuti.classList.remove('d-none');
                    alertCuti.innerText = "Cuti tidak cukup! Sisa cuti hanya " + sisaCuti + " hari.";
                    submitBtn.disabled = true;
                } else {
                    alertCuti.classList.add('d-none');
                    submitBtn.disabled = false;
                }
            }
        }

        tanggalMulai.addEventListener('change', hitungCuti);
        tanggalBerakhir.addEventListener('change', hitungCuti);

    });
</script>
@endpush

@endsection