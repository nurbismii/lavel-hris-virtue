<!DOCTYPE html>
<html lang="{{ app(\App\Services\Localization\LocaleService::class)->htmlLang() }}">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>PT VDNI | V-People</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-fonts-url" content="{{ versioned_asset('assets/css/fonts.min.css') }}">
    <link rel="icon" href="{{ versioned_asset('assets/img/kaiadmin/favicon-1.png') }}" type="image/x-icon" />

    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

    <!-- Fonts and icons -->
    <script src="{{ versioned_asset('assets/js/plugin/webfont/webfont.min.js') }}"></script>
    <script src="{{ versioned_asset('assets/js/app-font-loader.js') }}"></script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/plugins.min.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/kaiadmin.min.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/app-layout.css') }}" />

    @stack('styles')
</head>

<body>
    @php
        $appHomeUrl = auth()->check()
            ? route(auth()->user()->preferredHomeRouteName())
            : url('/');
    @endphp
    <div class="wrapper">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- End Sidebar -->
        @include('sweetalert::alert')
        <div class="main-panel">
            <div class="main-header">
                <div class="main-header-logo">
                    <!-- Logo Header -->
                    <div class="logo-header" data-background-color="white">
                        <a href="{{ $appHomeUrl }}" class="logo app-brand app-brand--header text-decoration-none" aria-label="V-People">
                            <img src="{{ versioned_asset('assets/img/kaiadmin/favicon-1.png') }}" alt="" class="navbar-brand app-brand__icon" />
                            <span class="app-brand__text">V-People</span>
                        </a>
                        <div class="nav-toggle">
                            <button class="btn btn-toggle toggle-sidebar">
                                <i class="gg-menu-right"></i>
                            </button>
                            <button class="btn btn-toggle sidenav-toggler">
                                <i class="gg-menu-left"></i>
                            </button>
                        </div>
                    </div>
                    <!-- End Logo Header -->
                </div>
                <!-- Navbar Header -->
                @include('partials.navbar')
                <!-- End Navbar -->
            </div>

            <div class="container">
                @yield('content')
            </div>

            <footer class="footer">
                <div class="container-fluid d-flex justify-content-between">
                    <nav class="pull-left">
                        <ul class="nav">
                            <li class="nav-item">
                                <a class="nav-link" href="#"> {{ __('common.help') }} </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#"> {{ __('common.licenses') }} </a>
                            </li>
                        </ul>
                    </nav>
                    <div>
                        {{ __('common.created_by') }}
                        <a href="#">PT VDNI</a>.
                    </div>
                </div>
            </footer>

            @include('partials.mobile-bottom-nav')
        </div>
    </div>
    <!--   Core JS Files   -->
    <script src="{{ versioned_asset('assets/js/core/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ versioned_asset('assets/js/core/popper.min.js') }}"></script>
    <script src="{{ versioned_asset('assets/js/core/bootstrap.min.js') }}"></script>

    <!-- jQuery Scrollbar -->
    <script src="{{ versioned_asset('assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js') }}"></script>

    <!-- Chart JS -->
    <script src="{{ versioned_asset('assets/js/plugin/chart.js/chart.min.js') }}"></script>

    <!-- jQuery Sparkline -->
    <script src="{{ versioned_asset('assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js') }}"></script>

    <!-- Chart Circle -->
    <script src="{{ versioned_asset('assets/js/plugin/chart-circle/circles.min.js') }}"></script>

    <!-- Datatables -->
    <script src="{{ versioned_asset('assets/js/plugin/datatables/datatables.min.js') }}"></script>

    <!-- Bootstrap Notify -->
    <script src="{{ versioned_asset('assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js') }}"></script>

    <!-- Sweet Alert -->
    <script src="{{ versioned_asset('assets/js/plugin/sweetalert/sweetalert.min.js') }}"></script>

    <!-- Kaiadmin JS -->
    <script src="{{ versioned_asset('assets/js/kaiadmin.min.js') }}"></script>

    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="{{ versioned_asset('assets/js/app-layout.js') }}"></script>

    @stack('scripts')

</body>

</html>
