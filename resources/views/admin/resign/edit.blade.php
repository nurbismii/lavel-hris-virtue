@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">Edit Data Resign</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('resign.update', $resign->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <h5 class="fw-bold mb-3">Data Utama</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-control" value="{{ $resign->nik_karyawan }}" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Karyawan</label>
                            <input type="text" class="form-control" value="{{ $resign->employee->nama_karyawan ?? '-' }}" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal Keluar</label>
                            <input type="date" class="form-control" name="tanggal_keluar" value="{{ $resign->tanggal_keluar }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="tipe" class="form-control form-select" required>
                                <option value="RESIGN SESUAI PROSEDUR" {{ old('tipe', $resign->tipe) == 'RESIGN SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Sesuai Prosedur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR" {{ old('tipe', $resign->tipe) == 'RESIGN TIDAK SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedur</option>
                                <option value="PUTUS KONTRAK" {{ old('tipe', $resign->tipe) == 'PUTUS KONTRAK' ? 'selected' : '' }}>Putus Kontrak</option>
                                <option value="PHK" {{ old('tipe', $resign->tipe) == 'PHK' ? 'selected' : '' }}>PHK</option>
                                <option value="PHK PENSIUN" {{ old('tipe', $resign->tipe) == 'PHK PENSIUN' ? 'selected' : '' }}>PHK Pensiun</option>
                                <option value="PHK PIDANA" {{ old('tipe', $resign->tipe) == 'PHK PIDANA' ? 'selected' : '' }}>PHK Pidana</option>
                                <option value="PHK MENINGGAL DUNIA" {{ old('tipe', $resign->tipe) == 'PHK MENINGGAL DUNIA' ? 'selected' : '' }}>PHK MENINGGAL DUNIA</option>
                            </select>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update
                            </button>
                            <a href="{{ route('resign.index') }}" class="btn btn-secondary">
                                Kembali
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection