@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">Buat Departemen</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('departemen.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departemen</label>
                            <input type="text" name="departemen" class="form-control">
                            <input type="hidden" name="perusahaan_id" value="{{ $perusahaan->id }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kepala Departemen</label>
                            <input type="text" name="kepala_dept" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">No Telepon Departemen</label>
                            <input type="text" name="no_telp_departemen" class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Pengeluaran</label>
                            <select name="status_pengeluaran" class="form-control form-select" required>
                                <option value="">-- Pilih status --</option>
                                <option value="PRODUKSI 生产">PRODUKSI 生产</option>
                                <option value="NON PRODUKSI 生产 非生产">NON PRODUKSI 生产 非生产</option>
                            </select>
                        </div>

                        {{-- BUTTON --}}
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Buat
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection