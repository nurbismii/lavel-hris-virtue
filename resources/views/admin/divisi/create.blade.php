@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">Buat Divisi</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('divisi.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Departemen</label>
                            <select name="departemen_id" class="form-select form-control">
                                <option value="">Semua Departemen</option>

                                @php
                                $groupedDepts = [];
                                foreach ($departemens as $d) {
                                $groupedDepts[$d->perusahaan['nama_perusahaan']][] = $d;
                                }
                                @endphp

                                @foreach($groupedDepts as $perusahaan => $departemens)
                                <optgroup label="{{ $perusahaan }}">
                                    @foreach($departemens as $d)
                                    <option value="{{ $d->id }}">{{ $d->departemen }}</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Divisi</label>
                            <input type="text" name="nama_divisi" class="form-control" required>
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