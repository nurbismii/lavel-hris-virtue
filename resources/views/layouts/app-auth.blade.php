<!DOCTYPE html>
<html lang="{{ app(\App\Services\Localization\LocaleService::class)->htmlLang() }}">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>PT VDNI | V-People</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="{{ versioned_asset('assets/img/kaiadmin/favicon-1.png') }}" type="image/x-icon" />

    <!-- CSS Files -->
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/plugins.min.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/kaiadmin.min.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/app-auth.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/app-auth2.css') }}" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/auth-modern.css') }}">

    @stack('styles')

</head>

<body>
    @include('partials.language-switcher', ['class' => 'language-switcher--auth'])

    @yield('content')

    <!--   Core JS Files   -->
    <script src="{{ versioned_asset('assets/js/core/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ versioned_asset('assets/js/core/popper.min.js') }}"></script>
    <script src="{{ versioned_asset('assets/js/core/bootstrap.min.js') }}"></script>

    @stack('scripts')
</body>

</html>
