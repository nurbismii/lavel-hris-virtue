@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-file-alt text-primary me-2"></i>
                    Form pengajuan izin
                </h4>
                <small class="text-muted">
                    Pilih izin berbayar atau tidak berbayar
                </small>
            </div>

            <a href="{{ route('izin.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-long-arrow-alt-left me-1"></i> Kembali
            </a>
        </div>

        <div class="card shadow-sm border-0">


            <div class="card-body">
                <form action="{{ route('izin.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- PILIH TIPE IZIN --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold">Jenis Izin</label>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe" value="PAID" id="paidRadio">
                            <label class="form-check-label" for="paidRadio">
                                Izin Berbayar
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe" value="UNPAID" id="unpaidRadio">
                            <label class="form-check-label" for="unpaidRadio">
                                Izin Tidak Berbayar
                            </label>
                        </div>
                    </div>

                    {{-- KHUSUS PAID --}}
                    <div id="paidOptions" style="display:none;">
                        <label class="form-label fw-bold">Kategori Izin Berbayar</label>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin Menikah ( 3 Hari )">
                            <label class="form-check-label">
                                Izin Menikah ( 3 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin menikahkan anak ( 2 Hari )">
                            <label class="form-check-label">
                                Izin menikahkan anak ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin Khitan / Baptis anak ( 2 Hari )">
                            <label class="form-check-label">
                                Izin Khitan / Baptis anak ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin istri melahirkan / Keguguran ( 2 Hari )">
                            <label class="form-check-label">
                                Izin istri melahirkan / Keguguran ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Izin Duka keluarga ( 2 Hari )">
                            <label class="form-check-label">
                                Izin Duka keluarga ( 2 Hari )
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="tipe_izin"
                                value="Cuti melahirkan ( 3 Bulan )">
                            <label class="form-check-label">
                                Cuti melahirkan ( 3 Bulan )
                            </label>
                        </div>
                    </div>

                    {{-- TANGGAL --}}
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tanggal Berakhir</label>
                            <input type="date" name="tanggal_berakhir" class="form-control">
                        </div>
                    </div>

                    {{-- KETERANGAN --}}
                    <div class="mt-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control"></textarea>
                    </div>

                    {{-- UPLOAD --}}
                    <div class="mt-3">
                        <label class="form-label">Upload Bukti (Opsional)</label>
                        <input type="file" name="foto" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">
                        Ajukan Izin
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

    paidRadio.addEventListener('change', function() {
        paidOptions.style.display = 'block';
    });

    unpaidRadio.addEventListener('change', function() {
        paidOptions.style.display = 'none';
    });
</script>
@endsection