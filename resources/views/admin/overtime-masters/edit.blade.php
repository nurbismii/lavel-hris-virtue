@extends('layouts.app')

@section('title', 'Edit Rule Lembur')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Edit Rule Lembur</h4>
                <small class="text-muted">{{ $rule->code }} - {{ $rule->name }}</small>
            </div>
            <a href="{{ route('overtime-masters.index') }}" class="btn btn-light">Kembali</a>
        </div>

        @include('admin.overtime-masters._form')
    </div>
</div>
@endsection
