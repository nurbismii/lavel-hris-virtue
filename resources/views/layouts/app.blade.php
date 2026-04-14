<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>PT VDNI | V-People</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('assets/img/kaiadmin/favicon-1.png') }}" type="image/x-icon" />

    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

    <style>
        .logo-industrial {
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #1f2937;
            padding-left: 3px;
        }

        .mobile-bottom-nav {
            display: none;
        }

        @media (max-width: 991.98px) {
            .main-panel {
                padding-bottom: calc(92px + env(safe-area-inset-bottom));
            }

            .sidebar,
            .main-header-logo .nav-toggle,
            .sidebar .nav-toggle {
                display: none !important;
            }

            .nav_open .sidebar,
            html.nav_open .sidebar {
                display: none !important;
                transform: translate3d(-270px, 0, 0) !important;
            }

            .nav_open .main-panel,
            html.nav_open .main-panel {
                transform: none !important;
            }

            .topbar-toggler.more {
                display: none !important;
            }

            .mobile-bottom-nav {
                position: fixed;
                right: 12px;
                bottom: 12px;
                left: 12px;
                z-index: 1050;
                display: flex;
                align-items: stretch;
                justify-content: space-between;
                gap: 6px;
                padding: 10px 8px calc(10px + env(safe-area-inset-bottom));
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(15, 23, 42, 0.08);
                border-radius: 22px;
                box-shadow: 0 14px 32px rgba(15, 23, 42, 0.14);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
            }

            .mobile-bottom-nav__group {
                position: relative;
                flex: 1 1 20%;
                min-width: 0;
            }

            .mobile-bottom-nav__item {
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 4px;
                padding: 8px 4px;
                color: #64748b;
                background: transparent;
                border: 0;
                border-radius: 16px;
                text-decoration: none;
                transition: all 0.2s ease;
            }

            .mobile-bottom-nav__item.is-active,
            .mobile-bottom-nav__group.is-open .mobile-bottom-nav__item {
                color: #0d6efd;
                background: rgba(13, 110, 253, 0.12);
            }

            .mobile-bottom-nav__item i {
                font-size: 18px;
            }

            .mobile-bottom-nav__label {
                font-size: 10px;
                line-height: 1.2;
                font-weight: 600;
                text-align: center;
                letter-spacing: 0.1px;
            }

            .mobile-bottom-nav__popup {
                position: absolute;
                bottom: calc(100% + 12px);
                left: 50%;
                min-width: 190px;
                padding: 8px;
                background: #ffffff;
                border: 1px solid rgba(15, 23, 42, 0.08);
                border-radius: 18px;
                box-shadow: 0 18px 36px rgba(15, 23, 42, 0.18);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translate(-50%, 12px);
                transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
            }

            .mobile-bottom-nav__popup::after {
                content: "";
                position: absolute;
                bottom: -7px;
                left: 50%;
                width: 14px;
                height: 14px;
                background: #ffffff;
                border-right: 1px solid rgba(15, 23, 42, 0.08);
                border-bottom: 1px solid rgba(15, 23, 42, 0.08);
                transform: translateX(-50%) rotate(45deg);
            }

            .mobile-bottom-nav__popup--end {
                right: 0;
                left: auto;
                transform: translateY(12px);
            }

            .mobile-bottom-nav__popup--end::after {
                right: 24px;
                left: auto;
                transform: rotate(45deg);
            }

            .mobile-bottom-nav__popup.is-open {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translate(-50%, 0);
            }

            .mobile-bottom-nav__popup--end.is-open {
                transform: translateY(0);
            }

            .mobile-bottom-nav__popup-item {
                width: 100%;
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 11px 12px;
                color: #1f2937;
                background: transparent;
                border: 0;
                border-radius: 12px;
                text-decoration: none;
                font-size: 13px;
                font-weight: 600;
                text-align: left;
                transition: background-color 0.2s ease, color 0.2s ease;
            }

            .mobile-bottom-nav__popup-item:hover,
            .mobile-bottom-nav__popup-item:focus {
                color: #0d6efd;
                background: rgba(13, 110, 253, 0.08);
            }

            .mobile-bottom-nav__popup-item--danger:hover,
            .mobile-bottom-nav__popup-item--danger:focus {
                color: #dc3545;
                background: rgba(220, 53, 69, 0.08);
            }
        }
    </style>

    <!-- Fonts and icons -->
    <script src="{{ asset('/assets/js/plugin/webfont/webfont.min.js') }}"></script>
    <script>
        WebFont.load({
            google: {
                families: ["Public Sans:300,400,500,600,700"]
            },
            custom: {
                families: [
                    "Font Awesome 5 Solid",
                    "Font Awesome 5 Regular",
                    "Font Awesome 5 Brands",
                    "simple-line-icons",
                ],
                urls: ["{{ asset('/assets/css/fonts.min.css') }}"],
            },
            active: function() {
                sessionStorage.fonts = true;
            },
        });
    </script>

    <!-- CSS Files -->
    <link rel="stylesheet" href="{{ asset('/assets/css/bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('/assets/css/plugins.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('/assets/css/kaiadmin.min.css') }}" />

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
                        <a href="{{ $appHomeUrl }}" class="logo text-decoration-none">
                            <span class="logo-industrial">PT VDNI - HRIS</span>
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
                                <a class="nav-link" href="#"> Help </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#"> Licenses </a>
                            </li>
                        </ul>
                    </nav>
                    <div>
                        Created by
                        <a href="#">PT VDNI</a>.
                    </div>
                </div>
            </footer>

            @include('partials.mobile-bottom-nav')
        </div>
    </div>
    <!--   Core JS Files   -->
    <script src="{{ asset('/assets/js/core/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/popper.min.js') }}"></script>
    <script src="{{ asset('/assets/js/core/bootstrap.min.js') }}"></script>

    <!-- jQuery Scrollbar -->
    <script src="{{ asset('/assets/js/plugin/jquery-scrollbar/jquery.scrollbar.min.js') }}"></script>

    <!-- Chart JS -->
    <script src="{{ asset('/assets/js/plugin/chart.js/chart.min.js') }}"></script>

    <!-- jQuery Sparkline -->
    <script src="{{ asset('/assets/js/plugin/jquery.sparkline/jquery.sparkline.min.js') }}"></script>

    <!-- Chart Circle -->
    <script src="{{ asset('/assets/js/plugin/chart-circle/circles.min.js') }}"></script>

    <!-- Datatables -->
    <script src="{{ asset('/assets/js/plugin/datatables/datatables.min.js') }}"></script>

    <!-- Bootstrap Notify -->
    <script src="{{ asset('/assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js') }}"></script>

    <!-- Sweet Alert -->
    <script src="{{ asset('/assets/js/plugin/sweetalert/sweetalert.min.js') }}"></script>

    <!-- Kaiadmin JS -->
    <script src="{{ asset('/assets/js/kaiadmin.min.js') }}"></script>

    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

    @stack('scripts')

</body>

</html>
