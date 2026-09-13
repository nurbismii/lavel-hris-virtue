@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/passkeys.css') }}">
@endpush
@push('scripts')
<script src="{{ versioned_asset('vendor/sweetalert/sweetalert.all.js') }}"></script>
<script src="{{ versioned_asset('assets/js/passkeys.js') }}" defer></script>
@endpush
