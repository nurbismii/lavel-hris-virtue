<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>PT VDNI | V-People</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ versioned_asset('assets/img/kaiadmin/favicon-1.png') }}" type="image/x-icon" />

    <!-- DataTables Responsive CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

    <!-- Fonts and icons -->
    <script src="{{ versioned_asset('assets/js/plugin/webfont/webfont.min.js') }}"></script>
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
                urls: ["{{ versioned_asset('assets/css/fonts.min.css') }}"],
            },
            active: function() {
                sessionStorage.fonts = true;
            },
        });
    </script>

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
                        <a href="{{ $appHomeUrl }}" class="logo text-decoration-none">
                            <span class="logo-industrial logo-industrial--header">PT VDNI - HRIS</span>
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

    <script>
        (function() {
            const IMAGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            const TARGET_BYTES = Math.round(1.8 * 1024 * 1024);
            const MAX_DIMENSION = 2400;
            const MIN_DIMENSION = 1000;
            const MIN_QUALITY = 0.45;

            function isCompressibleImage(file) {
                return file && IMAGE_MIME_TYPES.includes((file.type || '').toLowerCase());
            }

            function readFileAsDataUrl(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(new Error('Gagal membaca file gambar.'));
                    reader.readAsDataURL(file);
                });
            }

            function loadImage(source) {
                return new Promise((resolve, reject) => {
                    const image = new Image();
                    image.onload = () => resolve(image);
                    image.onerror = () => reject(new Error('Gagal memuat file gambar.'));
                    image.src = source;
                });
            }

            function canvasToBlob(canvas, quality) {
                return new Promise((resolve, reject) => {
                    canvas.toBlob((blob) => {
                        if (!blob) {
                            reject(new Error('Gagal membuat file hasil kompres.'));
                            return;
                        }

                        resolve(blob);
                    }, 'image/jpeg', quality);
                });
            }

            function drawImageToCanvas(image, width, height) {
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const context = canvas.getContext('2d', { alpha: false });
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, width, height);
                context.drawImage(image, 0, 0, width, height);

                return canvas;
            }

            function scaleDimensions(width, height, maxDimension) {
                if (Math.max(width, height) <= maxDimension) {
                    return { width, height };
                }

                const ratio = maxDimension / Math.max(width, height);

                return {
                    width: Math.max(1, Math.round(width * ratio)),
                    height: Math.max(1, Math.round(height * ratio)),
                };
            }

            async function compressImageFile(file) {
                if (!isCompressibleImage(file) || file.size <= TARGET_BYTES) {
                    return file;
                }

                const imageSource = await readFileAsDataUrl(file);
                const image = await loadImage(imageSource);
                let dimensions = scaleDimensions(image.width, image.height, MAX_DIMENSION);
                let quality = 0.9;
                let blob = null;

                while (true) {
                    const canvas = drawImageToCanvas(image, dimensions.width, dimensions.height);
                    blob = await canvasToBlob(canvas, quality);

                    if (blob.size <= TARGET_BYTES) {
                        break;
                    }

                    if (quality > MIN_QUALITY) {
                        quality = Math.max(MIN_QUALITY, quality - 0.08);
                        continue;
                    }

                    if (Math.max(dimensions.width, dimensions.height) <= MIN_DIMENSION) {
                        break;
                    }

                    dimensions = {
                        width: Math.max(1, Math.round(dimensions.width * 0.9)),
                        height: Math.max(1, Math.round(dimensions.height * 0.9)),
                    };
                    quality = 0.82;
                }

                if (!blob || blob.size >= file.size) {
                    return file;
                }

                const originalName = file.name.replace(/\.[^.]+$/, '');

                return new File(
                    [blob],
                    originalName + '.jpg',
                    {
                        type: 'image/jpeg',
                        lastModified: Date.now(),
                    }
                );
            }

            async function compressInputFiles(input) {
                if (!input.files || !input.files.length || typeof DataTransfer === 'undefined') {
                    return {
                        compressedCount: 0,
                        savedBytes: 0,
                    };
                }

                const dataTransfer = new DataTransfer();
                let compressedCount = 0;
                let savedBytes = 0;

                for (const file of Array.from(input.files)) {
                    const compressedFile = await compressImageFile(file);

                    if (compressedFile !== file) {
                        compressedCount += 1;
                        savedBytes += Math.max(file.size - compressedFile.size, 0);
                    }

                    dataTransfer.items.add(compressedFile);
                }

                input.files = dataTransfer.files;

                return {
                    compressedCount,
                    savedBytes,
                };
            }

            function toggleFormSubmitting(form, isSubmitting) {
                const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

                buttons.forEach((button) => {
                    if (!button.dataset.originalText) {
                        button.dataset.originalText = button.tagName === 'INPUT' ? button.value : button.innerHTML;
                    }

                    button.disabled = isSubmitting;

                    if (isSubmitting) {
                        if (button.tagName === 'INPUT') {
                            button.value = 'Memproses gambar...';
                        } else {
                            button.innerHTML = 'Memproses gambar...';
                        }
                    } else if (button.dataset.originalText) {
                        if (button.tagName === 'INPUT') {
                            button.value = button.dataset.originalText;
                        } else {
                            button.innerHTML = button.dataset.originalText;
                        }
                    }
                });
            }

            document.addEventListener('submit', async function(event) {
                const form = event.target;

                if (!(form instanceof HTMLFormElement) || form.dataset.autoCompressImages !== 'true') {
                    return;
                }

                if (form.dataset.compressionDone === 'true') {
                    return;
                }

                event.preventDefault();

                if (form.dataset.compressionInProgress === 'true') {
                    return;
                }

                form.dataset.compressionInProgress = 'true';
                toggleFormSubmitting(form, true);

                try {
                    const inputs = form.querySelectorAll('input[type="file"][data-compress-images="true"]');
                    let compressedCount = 0;
                    let savedBytes = 0;

                    for (const input of Array.from(inputs)) {
                        const result = await compressInputFiles(input);
                        compressedCount += result.compressedCount;
                        savedBytes += result.savedBytes;
                    }

                    if (compressedCount > 0 && window.$ && $.notify) {
                        $.notify({
                            icon: 'fa fa-compress',
                            title: 'Kompresi aktif',
                            message: `${compressedCount} gambar dikompres sebelum upload. Hemat sekitar ${(savedBytes / (1024 * 1024)).toFixed(2)} MB.`
                        }, {
                            type: 'info',
                            placement: {
                                from: 'top',
                                align: 'right'
                            },
                            delay: 3000
                        });
                    }

                    form.dataset.compressionDone = 'true';
                    HTMLFormElement.prototype.submit.call(form);
                } catch (error) {
                    form.dataset.compressionInProgress = 'false';
                    toggleFormSubmitting(form, false);

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kompresi gagal',
                            text: error.message || 'Gagal memproses gambar sebelum upload.',
                        });
                    } else {
                        alert(error.message || 'Gagal memproses gambar sebelum upload.');
                    }
                }
            });
        })();
    </script>

    @stack('scripts')

</body>

</html>
