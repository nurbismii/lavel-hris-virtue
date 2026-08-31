<!DOCTYPE html>
<html lang="{{ app(\App\Services\Localization\LocaleService::class)->htmlLang() }}">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>PT VDNI | V-People</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-fonts-url" content="{{ versioned_asset('assets/css/fonts.min.css') }}">
    <link rel="icon" href="{{ versioned_asset('assets/img/kaiadmin/icon-2.png') }}" type="image/x-icon" />

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
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/custom.css') }}" />

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
                            <img src="{{ versioned_asset('assets/img/kaiadmin/icon-2.png') }}" alt="" class="navbar-brand app-brand__icon" />
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
                <div class="mobile-top-language d-lg-none">
                    @include('partials.language-switcher', [
                    'class' => 'language-switcher--mobile-top',
                    'id' => 'languageSwitcherMobileTopDropdown',
                    ])
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

    <div class="modal fade" id="approvalRejectReasonModal" tabindex="-1" aria-labelledby="approvalRejectReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-semibold" id="approvalRejectReasonModalLabel">
                            {{ __('common.dialog.reject_reason') }}
                        </h5>
                        <small class="text-muted">
                            {{ __('common.dialog.reject_reason_help') }}
                        </small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.dialog.cancel') }}"></button>
                </div>
                <div class="modal-body">
                    <label for="approvalRejectReasonInput" class="form-label">
                        {{ __('common.dialog.reason') }}
                    </label>
                    <textarea
                        id="approvalRejectReasonInput"
                        class="form-control"
                        rows="4"
                        maxlength="500"
                        placeholder="{{ __('common.dialog.reject_reason_placeholder') }}"></textarea>
                    <div class="invalid-feedback">
                        {{ __('common.dialog.reject_reason_required') }}
                    </div>
                    <div class="form-text text-end">
                        <span id="approvalRejectReasonCounter">0</span>/500
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        {{ __('common.dialog.cancel') }}
                    </button>
                    <button type="button" class="btn btn-danger" id="approvalRejectReasonSubmit">
                        {{ __('common.dialog.reject_submission') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="approvalConfirmModal" tabindex="-1" aria-labelledby="approvalConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="approvalConfirmModalLabel">
                        {{ __('common.dialog.approve_confirm_title') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.dialog.cancel') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="approvalConfirmModalMessage">
                        {{ __('common.dialog.approve_confirm_text') }}
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        {{ __('common.dialog.cancel') }}
                    </button>
                    <button type="button" class="btn btn-primary" id="approvalConfirmSubmit">
                        {{ __('common.dialog.continue') }}
                    </button>
                </div>
            </div>
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
    <script>
        if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
            jQuery.extend(true, jQuery.fn.dataTable.defaults, {
                language: @json(trans('datatables'))
            });
        }
    </script>

    <!-- Bootstrap Notify -->
    <script src="{{ versioned_asset('assets/js/plugin/bootstrap-notify/bootstrap-notify.min.js') }}"></script>

    <!-- Sweet Alert -->
    <script src="{{ versioned_asset('assets/js/plugin/sweetalert/sweetalert.min.js') }}"></script>
    <script>
        window.AppDialog = window.AppDialog || {
            alert: function(title, text, icon) {
                const message = text || title;
                const type = icon || 'warning';

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    return window.Swal.fire({
                        title: title,
                        text: text || '',
                        icon: type,
                        confirmButtonText: @json(__('common.dialog.ok'))
                    });
                }

                if (typeof window.swal === 'function') {
                    return window.swal({
                        title: title,
                        text: text || '',
                        icon: type,
                        button: @json(__('common.dialog.ok'))
                    });
                }

                window.alert(message);
                return Promise.resolve();
            },
            confirm: function(options) {
                const config = Object.assign({
                    title: @json(__('common.dialog.confirm')),
                    text: @json(__('common.dialog.process_confirm_text')),
                    icon: 'warning',
                    confirmButtonText: @json(__('common.dialog.continue')),
                    cancelButtonText: @json(__('common.dialog.cancel')),
                    dangerMode: false
                }, options || {});

                if (window.Swal && typeof window.Swal.fire === 'function') {
                    return window.Swal.fire({
                        title: config.title,
                        text: config.text,
                        icon: config.icon,
                        showCancelButton: true,
                        confirmButtonText: config.confirmButtonText,
                        cancelButtonText: config.cancelButtonText,
                        reverseButtons: true
                    }).then(function(result) {
                        // SweetAlert2 lama mengembalikan `value`, sedangkan versi baru
                        // memakai `isConfirmed`. Dukung keduanya agar callback form tetap jalan.
                        return !!(result && (result.isConfirmed === true || result.value === true));
                    });
                }

                if (typeof window.swal === 'function') {
                    return window.swal({
                        title: config.title,
                        text: config.text,
                        icon: config.icon,
                        buttons: [config.cancelButtonText, config.confirmButtonText],
                        dangerMode: config.dangerMode
                    }).then(function(confirmed) {
                        return !!confirmed;
                    });
                }

                return Promise.resolve(window.confirm(config.text || config.title));
            },
            confirmForm: function(form, options) {
                return this.confirm(options).then(function(confirmed) {
                    if (!confirmed) {
                        return false;
                    }

                    form.dataset.swalConfirmed = '1';
                    form.submit();
                    return true;
                });
            }
        };

        document.addEventListener('submit', function(event) {
            const form = event.target;

            if (!form || !form.matches('form[data-swal-confirm]') || form.dataset.swalConfirmed === '1') {
                return;
            }

            event.preventDefault();

            window.AppDialog.confirmForm(form, {
                title: form.dataset.swalTitle || @json(__('common.dialog.confirm')),
                text: form.dataset.swalConfirm || @json(__('common.dialog.process_confirm_text')),
                icon: form.dataset.swalIcon || 'warning',
                confirmButtonText: form.dataset.swalConfirmButton || @json(__('common.dialog.continue')),
                cancelButtonText: form.dataset.swalCancelButton || @json(__('common.dialog.cancel')),
                dangerMode: form.dataset.swalDanger === '1'
            });
        });
    </script>

    <!-- Kaiadmin JS -->
    <script src="{{ versioned_asset('assets/js/kaiadmin.min.js') }}"></script>

    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="{{ versioned_asset('assets/js/app-layout.js') }}"></script>
    <script>
        (function() {
            const modalElement = document.getElementById('approvalRejectReasonModal');
            const reasonInput = document.getElementById('approvalRejectReasonInput');
            const reasonCounter = document.getElementById('approvalRejectReasonCounter');
            const rejectSubmitButton = document.getElementById('approvalRejectReasonSubmit');
            const confirmModalElement = document.getElementById('approvalConfirmModal');
            const confirmModalMessage = document.getElementById('approvalConfirmModalMessage');
            const confirmSubmitButton = document.getElementById('approvalConfirmSubmit');
            let pendingRejectForm = null;
            let pendingRejectSubmitter = null;
            let pendingConfirmForm = null;
            let pendingConfirmSubmitter = null;
            let rejectReasonModal = null;
            let approvalConfirmModal = null;

            const isApprovalForm = function(form) {
                return form instanceof HTMLFormElement && form.action.includes('/approval/');
            };

            const getRejectActionValue = function(form, submitter) {
                if (submitter && submitter.name === 'action') {
                    return submitter.value;
                }

                const actionField = form.querySelector('[name="action"]');

                return actionField ? actionField.value : null;
            };

            const ensureHiddenField = function(form, name, value) {
                let field = form.querySelector('input[type="hidden"][name="' + name + '"]');

                if (!field) {
                    field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = name;
                    form.appendChild(field);
                }

                field.value = value;
            };

            const openBootstrapModal = function(element) {
                if (window.bootstrap && bootstrap.Modal) {
                    const instance = bootstrap.Modal.getOrCreateInstance ?
                        bootstrap.Modal.getOrCreateInstance(element) :
                        new bootstrap.Modal(element);

                    instance.show();

                    return instance;
                }

                if (window.jQuery && typeof window.jQuery(element).modal === 'function') {
                    window.jQuery(element).modal('show');

                    return {
                        hide: function() {
                            window.jQuery(element).modal('hide');
                        }
                    };
                }

                element.classList.add('show');
                element.style.display = 'block';
                element.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');

                return {
                    hide: function() {
                        element.classList.remove('show');
                        element.style.display = 'none';
                        element.setAttribute('aria-hidden', 'true');
                        document.body.classList.remove('modal-open');
                    }
                };
            };

            const resetModal = function() {
                reasonInput.value = '';
                reasonInput.classList.remove('is-invalid');
                reasonCounter.textContent = '0';
                rejectSubmitButton.disabled = false;
            };

            reasonInput.addEventListener('input', function() {
                reasonInput.classList.remove('is-invalid');
                reasonCounter.textContent = String(reasonInput.value.length);
            });

            const openRejectReasonModal = function(form, submitter) {
                resetModal();

                pendingRejectForm = form;
                pendingRejectSubmitter = submitter && submitter.name === 'action' ? submitter : null;
                rejectReasonModal = openBootstrapModal(modalElement);

                setTimeout(function() {
                    reasonInput.focus();
                }, 200);
            };

            const openApprovalConfirmModal = function(form, submitter, message) {
                pendingConfirmForm = form;
                pendingConfirmSubmitter = submitter && submitter.name === 'action' ? submitter : null;
                confirmModalMessage.textContent = message;
                confirmSubmitButton.disabled = false;
                approvalConfirmModal = openBootstrapModal(confirmModalElement);
            };

            const handleApprovalAction = function(event, form, submitter) {
                if (!isApprovalForm(form)) {
                    return false;
                }

                const actionValue = getRejectActionValue(form, submitter);

                if (actionValue !== '2') {
                    if (actionValue === '1' && form.dataset.approvalConfirmMessage) {
                        event.preventDefault();
                        event.stopPropagation();
                        openApprovalConfirmModal(form, submitter, form.dataset.approvalConfirmMessage);

                        return true;
                    }

                    return false;
                }

                const noteField = form.querySelector('[name="note"]');
                const existingNote = noteField ? noteField.value.trim() : '';

                if (existingNote !== '') {
                    return false;
                }

                event.preventDefault();
                event.stopPropagation();
                openRejectReasonModal(form, submitter);

                return true;
            };

            document.addEventListener('click', function(event) {
                const submitter = event.target.closest('button, input[type="submit"], input[type="image"]');

                if (!submitter || submitter.disabled) {
                    return;
                }

                const form = submitter.form || submitter.closest('form');

                if (submitter.classList.contains('js-approval-reject') && form instanceof HTMLFormElement) {
                    resetModal();
                    pendingRejectForm = form;
                    pendingRejectSubmitter = submitter.name === 'action' ? submitter : null;
                    return;
                }

                handleApprovalAction(event, form, submitter);
            }, true);

            document.addEventListener('submit', function(event) {
                const form = event.target;
                const submitter = event.submitter || document.activeElement;

                handleApprovalAction(event, form, submitter);
            }, true);

            rejectSubmitButton.addEventListener('click', function() {
                const note = reasonInput.value.trim();

                if (note === '') {
                    reasonInput.classList.add('is-invalid');
                    reasonInput.focus();
                    return;
                }

                if (!pendingRejectForm) {
                    return;
                }

                rejectSubmitButton.disabled = true;
                ensureHiddenField(pendingRejectForm, 'note', note);

                if (pendingRejectSubmitter) {
                    ensureHiddenField(pendingRejectForm, pendingRejectSubmitter.name, pendingRejectSubmitter.value);
                }

                pendingRejectForm.submit();
            });

            confirmSubmitButton.addEventListener('click', function() {
                if (!pendingConfirmForm) {
                    return;
                }

                confirmSubmitButton.disabled = true;

                if (pendingConfirmSubmitter) {
                    ensureHiddenField(pendingConfirmForm, pendingConfirmSubmitter.name, pendingConfirmSubmitter.value);
                }

                pendingConfirmForm.submit();
            });

            modalElement.addEventListener('hidden.bs.modal', function() {
                pendingRejectForm = null;
                pendingRejectSubmitter = null;
                resetModal();
            });

            confirmModalElement.addEventListener('hidden.bs.modal', function() {
                pendingConfirmForm = null;
                pendingConfirmSubmitter = null;
                confirmModalMessage.textContent = @json(__('common.dialog.approve_confirm_text'));
                confirmSubmitButton.disabled = false;
            });
        })();
    </script>
    <script src="{{ versioned_asset('assets/js/action-state.js') }}"></script>

    @auth
    <script>
        window.AppRealtimeNotifications = {
            enabled: @json(config('broadcasting.default') === 'pusher' && filled(config('broadcasting.connections.pusher.key'))),
            userId: @json((string) auth()->id()),
            pusherKey: @json(config('broadcasting.connections.pusher.key')),
            pusherCluster: @json(config('broadcasting.connections.pusher.options.cluster')),
            forceTLS: @json((bool) data_get(config('broadcasting.connections.pusher.options'), 'useTLS', true)),
            authEndpoint: @json(url('/broadcasting/auth')),
            latestUrl: @json(route('notifications.latest')),
            fallbackInterval: 60000,
            inboxUrl: @json(route('kotak-masuk.index')),
            desktopIconUrl: @json(url(versioned_asset('assets/img/kaiadmin/icon-2.png'))),
            desktopBadgeUrl: @json(url(versioned_asset('assets/img/kaiadmin/icon-2.png'))),
        };
    </script>
    @endauth
    <script src="{{ versioned_asset('js/app.js') }}"></script>

    @stack('scripts')

</body>

</html>
