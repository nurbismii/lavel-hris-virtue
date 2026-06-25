@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>{{ __('common.process_failed') }}</strong>
        <div class="mt-1">
            {{ $errors->first() }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $sessionKey => $alertClass)
    @if(session($sessionKey))
        <div class="alert alert-{{ $alertClass }} alert-dismissible fade show" role="alert">
            {{ session($sessionKey) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
@endforeach
