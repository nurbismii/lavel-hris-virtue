@extends('layouts.app')

@section('title', __('Edit Rule Lembur'))

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">{{ __('Edit Rule Lembur') }}</h4>
                <small class="text-muted">{{ $rule->code }} - {{ $rule->name }}</small>
            </div>
            <a href="{{ route('overtime-masters.index') }}" class="btn btn-light">{{ __('Kembali') }}</a>
        </div>

        @include('admin.overtime-masters._form')
    </div>
</div>
@endsection
