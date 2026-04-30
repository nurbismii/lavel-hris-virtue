@extends('layouts.app')

@section('title', 'Tambah Rule Lembur')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Tambah Rule Lembur</h4>
                <small class="text-muted">Tambahkan rentang jam dan pengali untuk master upah lembur.</small>
            </div>
            <a href="{{ route('overtime-masters.index') }}" class="btn btn-light">Kembali</a>
        </div>

        @include('admin.overtime-masters._form')
    </div>
</div>
@endsection
