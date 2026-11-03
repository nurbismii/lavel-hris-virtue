@if(config('hris.mobile_app.required', true) && !request()->routeIs('password.*'))
    <script src="{{ versioned_asset('assets/js/mobile-app-guard.js?policy=ios-browser') }}"
        data-download-url="{{ route('mobile.download') }}"
        data-ios-required="{{ config('hris.mobile_app.ios_required', false) ? '1' : '0' }}"></script>
@endif
