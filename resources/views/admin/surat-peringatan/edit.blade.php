@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">Edit Data Resign</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('surat-peringatan.update', $suratPeringatan->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <h5 class="fw-bold mb-3">Data Utama</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-control" value="{{ $suratPeringatan->nik_karyawan }}" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama karyawan</label>
                            <input type="text" class="form-control" value="{{ $suratPeringatan->employee->nama_karyawan ?? '-' }}" required readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal mulai</label>
                            <input type="date" class="form-control" name="tgl_mulai" value="{{ $suratPeringatan->tgl_mulai }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal berakhir</label>
                            <input type="date" class="form-control" name="tgl_berakhir" value="{{ $suratPeringatan->tgl_berakhir }}">
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Pilih level surat peringatan</label>
                            <select name="level_sp" class="form-control form-select" required>
                                <option value="SP1" {{ old('level_sp', $suratPeringatan->level_sp) == 'SP1' ? 'selected' : '' }}>Surat Peringatan 1</option>
                                <option value="SP2" {{ old('level_sp', $suratPeringatan->level_sp) == 'SP2' ? 'selected' : '' }}>Surat Peringatan 2</option>
                                <option value="SP3" {{ old('level_sp', $suratPeringatan->level_sp) == 'SP3' ? 'selected' : '' }}>Surat Peringatan 3</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" id="" rows="10">{{ $suratPeringatan->keterangan }}</textarea>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update
                            </button>
                            <a href="{{ route('surat-peringatan.index') }}" class="btn btn-secondary">
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