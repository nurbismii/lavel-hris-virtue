@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-presensi.css') }}">
@endpush

@section('content')

@php
$faceReferencePath = auth()->user()->employee->face_reference_path ?? null;
$faceReferenceUrl = $faceReferencePath ? route('presensi.face-reference') : null;
$formatPresensiClock = function ($value, $attendanceDate = null, $empty = '--:--') {
if (!$value) {
return $empty;
}

$clock = \Carbon\Carbon::parse($value);
$suffix = '';

if ($attendanceDate && $clock->toDateString() > \Carbon\Carbon::parse($attendanceDate)->toDateString()) {
$suffix = ' +1';
}

return $clock->format('H:i') . $suffix;
};
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-1">
                    <i class="fas fa-map-pin text-primary me-2"></i>
                    Presensi
                </h3>
                <small class="text-muted">
                    Silakan presensi untuk mencatat kehadiranmu
                </small>
            </div>
        </div>

        @if (!$lokasi)
        <div class="alert alert-danger">
            Lokasi presensi untuk divisi Anda belum diatur.
        </div>
        @else
        @if (!$faceReferencePath)
        <div class="alert alert-warning">
            Foto referensi wajah belum didaftarkan oleh admin. Presensi dikunci sampai foto referensi tersedia.
        </div>
        @endif

        @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Presensi belum berhasil dikirim.</strong>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif

        @if (!empty($activeOvertimeOrder))
        <div class="alert alert-info d-flex flex-column gap-1">
            <strong>Perintah lembur aktif tanggal presensi: {{ $activeOvertimeOrder->type_label }}</strong>
            <span>Jadwal: {{ $activeOvertimeOrder->overtime_date->translatedFormat('d M Y') }} | {{ $activeOvertimeOrder->time_range_text }}</span>
            <span>Karena Anda sudah menyetujui perintah lembur ini, kehadiran pada tanggal tersebut wajib dicatat melalui presensi.</span>
        </div>
        @endif

        @if($isCrossDayAttendance)
        <div class="alert alert-warning d-flex flex-column gap-1">
            <strong>Mode presensi lintas hari aktif</strong>
        </div>
        @endif

        @php
        $statusPresensiHariIni = $absensiHariIni->status_presensi ?? null;
        $nextType = null;
        $label = '';
        $btnClass = 'btn-primary';
        $btnIcon = 'fas fa-arrow-right';
        $actionTitle = 'Siap untuk langkah berikutnya';
        $actionText = 'Ambil selfie terlebih dahulu, tunggu matching berhasil, lalu lanjutkan presensi saat GPS valid.';

        if ($statusPresensiHariIni) {
        $actionTitle = 'Status presensi tanggal aktif';
        $actionText = 'Tanggal presensi ini tercatat sebagai ' . $statusPresensiHariIni . '. Presensi jam tidak diperlukan.';
        } elseif (!$absensiHariIni || !$absensiHariIni->jam_masuk) {
        $nextType = 'masuk';
        $label = 'Absen Masuk';
        $btnClass = 'btn-primary';
        $btnIcon = 'fas fa-sign-in-alt';
        $actionTitle = 'Absen masuk tersedia';
        $actionText = 'Ambil selfie dulu, pastikan matching berhasil, lalu sistem akan mengizinkan presensi masuk.';
        } elseif (!$absensiHariIni->jam_istirahat) {
        $nextType = 'istirahat';
        $label = 'Mulai Istirahat';
        $btnClass = 'btn-warning';
        $btnIcon = 'fas fa-mug-hot';
        $actionTitle = 'Mulai waktu istirahat';
        $actionText = 'Lanjutkan ke presensi istirahat setelah selfie cocok dan lokasi kamu tetap valid.';
        } elseif (!$absensiHariIni->jam_kembali_istirahat) {
        $nextType = 'kembali';
        $label = 'Kembali Istirahat';
        $btnClass = 'btn-info';
        $btnIcon = 'fas fa-undo-alt';
        $actionTitle = 'Kembali dari istirahat';
        $actionText = 'Sistem akan membuka tombol kembali setelah selfie terverifikasi dan GPS tetap sesuai.';
        } elseif (!$absensiHariIni->jam_pulang) {
        $nextType = 'pulang';
        $label = 'Absen Pulang';
        $btnClass = 'btn-danger';
        $btnIcon = 'fas fa-sign-out-alt';
        $actionTitle = 'Tutup presensi tanggal aktif';
        $actionText = 'Ambil selfie terakhir, tunggu matching, lalu lakukan presensi pulang.';
        }

        $requiresFaceStep = (bool) ($nextType && $faceReferencePath);
        @endphp

        <div class="attendance-card">
            <div class="attendance-card__body">
                <div class="attendance-card__header">
                    <div>
                        <span class="attendance-kicker">
                            <i class="fas fa-shield-alt"></i>
                            Presensi Aman
                        </span>
                    </div>

                    <div class="attendance-steps">
                        <div class="attendance-step">
                            <span class="attendance-step__label">Langkah 1</span>
                            <span class="attendance-step__value">Foto & Matching</span>
                        </div>
                        <div class="attendance-step">
                            <span class="attendance-step__label">Langkah 2</span>
                            <span class="attendance-step__value">Presensi</span>
                        </div>
                    </div>
                </div>

                <div class="attendance-wizard">
                    @if ($requiresFaceStep)
                    <section id="wizardStepFace" class="attendance-stage is-active">
                        <div class="attendance-stage__rail">
                            <span id="wizardIndicatorFace" class="attendance-stage__number is-active">1</span>
                        </div>

                        <div class="attendance-stage__main">
                            <div class="face-section__header">
                                <div>
                                    <span class="section-caption">Verifikasi Wajah</span>
                                </div>

                                <div id="blinkStatus" class="small text-muted mb-2">
                                    Arahkan wajah ke kamera, lalu kedipkan mata sekali.
                                </div>

                                <div id="faceStatusBadge" class="face-status-chip bg-light text-muted">
                                    Menyiapkan model verifikasi...
                                </div>
                            </div>

                            <img
                                id="referenceFaceImage"
                                src="{{ $faceReferenceUrl }}"
                                alt="Foto referensi wajah"
                                class="sr-reference-image">

                            <div
                                id="selfieCameraStage"
                                class="face-preview-frame"
                                data-frame-state="neutral"
                                aria-live="polite">
                                <div class="camera-stage">
                                    <div class="camera-stage__header">
                                        <div>
                                            <span class="camera-stage__eyebrow">
                                                <i class="fas fa-camera"></i>
                                                Kamera Presensi
                                            </span>
                                        </div>
                                    </div>

                                    <div class="camera-stage__media">

                                        <div class="camera-stage__mirror">
                                            <video
                                                id="selfieCamera"
                                                class="camera-live-video d-none"
                                                autoplay
                                                muted
                                                playsinline></video>
                                            <canvas
                                                id="selfieOverlay"
                                                class="camera-overlay d-none"></canvas>
                                        </div>

                                        <div class="camera-guide">
                                            <div id="cameraGuideShape" class="camera-guide__shape"></div>
                                            <div id="cameraGuideLabel" class="camera-guide__label">
                                                Posisikan wajah di dalam frame
                                            </div>
                                        </div>

                                        <div
                                            id="selfiePlaceholder"
                                            class="selfie-placeholder">
                                            <div class="selfie-placeholder__icon">
                                                <i class="fas fa-camera-retro"></i>
                                            </div>
                                            <p class="selfie-placeholder__title">Kamera depan akan aktif otomatis</p>
                                            <p class="selfie-placeholder__text">
                                                Izinkan akses kamera, arahkan wajah ke tengah frame, lalu tunggu sebentar sampai indikator hijau muncul.
                                            </p>
                                        </div>

                                        <img
                                            id="selfiePreview"
                                            alt="Preview selfie presensi"
                                            class="face-preview-image d-none">
                                    </div>

                                    <div class="camera-stage__footer">
                                        

                                        <div class="camera-action-group">
                                            <button type="button" id="retryCameraButton" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-redo-alt me-2"></i>
                                                Aktifkan Ulang Kamera
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <input
                                type="file"
                                id="selfie_capture"
                                name="selfie_capture"
                                accept="image/png,image/jpeg,image/webp"
                                capture="user"
                                form="formAbsen"
                                class="selfie-input-hidden">

                            <input
                                type="hidden"
                                id="selfie_capture_data"
                                name="selfie_capture_data"
                                form="formAbsen">
                        </div>
                    </section>
                    @endif

                    <section id="wizardStepAttendance" class="attendance-stage {{ $requiresFaceStep ? 'is-locked' : 'is-active' }}">
                        <div class="attendance-stage__rail">
                            <span
                                id="wizardIndicatorAttendance"
                                class="attendance-stage__number {{ $requiresFaceStep ? 'is-locked' : 'is-active' }}">
                                {{ $requiresFaceStep ? '2' : '1' }}
                            </span>
                        </div>

                        <div class="attendance-stage__main">
                            <div class="map-card__header">
                                <div>
                                    <span class="section-caption">Validasi Lokasi</span>
                                    <h5 class="section-title">Tahap presensi dibuka setelah selfie valid</h5>
                                </div>
                                <div class="map-chip">
                                    <i class="fas fa-map-marked-alt"></i>
                                    GPS aktif
                                </div>
                            </div>

                            @if ($requiresFaceStep)
                            <div id="attendanceStepHint" class="attendance-stage__hint">
                                <i class="fas fa-lock"></i>
                                Selesaikan selfie dan tunggu matching berhasil untuk membuka tahap presensi.
                            </div>
                            @endif

                            <div id="wizardAttendanceContent" class="{{ $requiresFaceStep ? 'd-none' : '' }}">
                                <div class="map-frame">
                                    <div id="map" class="map-surface"></div>
                                </div>

                                <div class="location-status-card">
                                    <div class="location-status-card__icon">
                                        <i class="fas fa-crosshairs"></i>
                                    </div>
                                    <div>
                                        <span class="location-status-card__label">Status Lokasi</span>
                                        <div id="distanceInfo" class="location-status-card__value">
                                            Mendeteksi lokasi...
                                        </div>
                                    </div>
                                </div>

                                @if ($statusPresensiHariIni)
                                <div class="alert alert-info mb-2 mt-4">
                                    <strong>Status hari ini:</strong> {{ $statusPresensiHariIni }}
                                </div>
                                @endif

                                <div class="attendance-summary row g-3">
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            <span class="attendance-metric__label">Masuk</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_masuk ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            <span class="attendance-metric__label">Istirahat</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_istirahat ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            <span class="attendance-metric__label">Kembali</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_kembali_istirahat ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            <span class="attendance-metric__label">Pulang</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_pulang ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="attendance-action-card">
                                    <div>
                                        <span class="attendance-action-card__caption">Langkah Berikutnya</span>
                                        <h6 class="attendance-action-card__title">{{ $actionTitle }}</h6>
                                        <p class="attendance-action-card__text">{{ $actionText }}</p>
                                    </div>

                                    <div class="d-grid">
                                        @if ($nextType)
                                        <button class="btn {{ $btnClass }} btn-absen shadow" data-type="{{ $nextType }}" disabled>
                                            <i class="{{ $btnIcon }} me-2"></i>
                                            {{ $label }}
                                        </button>
                                        @else
                                        <button class="btn btn-success shadow" disabled>
                                            <i class="fas fa-check-circle me-2"></i>
                                            Presensi Hari Ini Selesai
                                        </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <form id="formAbsen" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="lat_user" id="lat_user">
                    <input type="hidden" name="long_user" id="long_user">
                    <input type="hidden" name="accuracy" id="accuracy_user">
                    <input type="hidden" name="speed" id="speed_user">
                    <input type="hidden" name="device_info" id="device_info">
                    <input type="hidden" name="face_verified" id="face_verified" value="0">
                    <input type="hidden" name="face_distance" id="face_distance" value="">
                    <input type="hidden" name="face_detection_count" id="face_detection_count" value="0">
                    <input type="hidden" name="face_verification_meta" id="face_verification_meta" value="">
                    <input type="hidden" name="attendance_challenge_id" id="attendance_challenge_id" value="{{ $attendanceChallenge['id'] ?? '' }}">
                    <input type="hidden" name="attendance_challenge_token" id="attendance_challenge_token" value="{{ $attendanceChallenge['token'] ?? '' }}">

                    <input type="hidden" name="presensi_challenge_id" id="presensi_challenge_id" value="{{ $faceChallenge['id'] ?? '' }}">
                    <input type="hidden" name="presensi_challenge_action" id="presensi_challenge_action" value="{{ $faceChallenge['action'] ?? '' }}">

                    <input type="hidden" name="face_liveness_passed" id="face_liveness_passed" value="0">
                    <input type="hidden" name="face_liveness_type" id="face_liveness_type" value="blink">
                    <input type="hidden" name="face_liveness_score" id="face_liveness_score">
                    <input type="hidden" name="face_liveness_message" id="face_liveness_message">
                </form>
            </div>
        </div>

        <hr class="my-5">

        <div class="history-header">
            <div>
                <h5 class="history-header__title">Riwayat Presensi</h5>
                <p class="history-header__text">
                    Periode {{ formatDateIndonesia($cutoffStart) }} - {{ formatDateIndonesia($cutoffEnd) }}
                </p>
            </div>
            <div class="history-chip">
                <i class="fas fa-history"></i>
                Data presensi periode aktif
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive">
                <table id="table-presensi" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Shift</th>
                            <th>Masuk</th>
                            <th>Istirahat</th>
                            <th>Kembali</th>
                            <th>Pulang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($presensi as $item)
                        @php($fulfillment = $item->attendance_fulfillment ?? null)
                        <tr>
                            <td>{{ formatDateIndonesia($item->tanggal) }}</td>
                            <td>{{ \App\Models\Presensi::shortStatus($item->status_presensi) ?? '-' }}</td>
                            <td>{{ optional($item->resolved_shift)->code ?? 'AUTO' }}</td>
                            <td>{{ $formatPresensiClock($item->jam_masuk, $item->tanggal, '-') }}</td>
                            <td>{{ $formatPresensiClock($item->jam_istirahat, $item->tanggal, '-') }}</td>
                            <td>{{ $formatPresensiClock($item->jam_kembali_istirahat, $item->tanggal, '-') }}</td>
                            <td>{{ $formatPresensiClock($item->jam_pulang, $item->tanggal, '-') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-muted">
                                Tidak ada data pada periode ini
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@php($googleMapsApiKey = config('services.google_maps.api_key'))
<script src="https://maps.googleapis.com/maps/api/js?v=3{{ $googleMapsApiKey ? '&key=' . urlencode($googleMapsApiKey) : '' }}"></script>
<script src="{{ versioned_asset('vendor/face-api/face-api.min.js') }}"></script>

@if ($lokasi)
<script>
    let map;
    let markerUser;
    let markerOffice;
    let circleOffice;
    let currentDistance = 0;
    let stableStartTime = null;
    let gpsReady = false;
    let faceApiReady = false;
    let faceVerificationPassed = false;
    let referenceFaceDescriptor = null;
    let positionHistory = [];
    let gpsEvidenceReady = false;
    let gpsLogInFlight = false;
    let cameraStream = null;
    let cameraDetectionIntervalId = null;
    let cameraValidationStartedAt = null;
    let cameraPreviewUrl = null;
    let cameraIsProcessing = false;
    let cameraVerificationLocked = false;
    let blinkLivenessPassed = false;
    let blinkLivenessScore = null;
    let blinkLivenessMessage = '';
    let faceMatchedReady = false;
    let latestFaceMatchCapturePayload = null;
    let livenessCaptureInProgress = false;

    const gpsValidationDelayMs = 2500;
    const cameraValidationDelayMs = 900;
    const liveDetectionIntervalMs = 320;
    const faceDistanceThreshold = 0.5;
    const selfieMaxCaptureWidth = 720;
    const selfieJpegQuality = 0.82;
    const faceModelPath = @json(asset('vendor/face-api/weights'));
    const faceReferencePath = @json($faceReferenceUrl);
    const attendanceChallenge = @json($attendanceChallenge);
    const manualSelfieEnabled = false;
    const attendanceSubmitBaseUrl = @json(url('/absen'));
    const gpsLogUrl = @json(url('/api/gps-log'));

    function attendanceChallengeExpiresAt() {
        if (!attendanceChallenge || !attendanceChallenge.expires_at) {
            return 0;
        }

        return Date.parse(attendanceChallenge.expires_at) || 0;
    }

    function attendanceChallengeElapsedMs() {
        if (!attendanceChallenge || !attendanceChallenge.issued_at) {
            return 0;
        }

        const issuedAt = Date.parse(attendanceChallenge.issued_at) || Date.now();

        return Math.max(Date.now() - issuedAt, 0);
    }

    function resetFaceMatchedReady() {
        faceMatchedReady = false;
        latestFaceMatchCapturePayload = null;
    }

    function isAttendanceChallengeReady() {
        return Boolean(
            attendanceChallenge &&
            attendanceChallenge.id &&
            attendanceChallenge.token &&
            attendanceChallengeExpiresAt() > Date.now() + 5000
        );
    }

    function isLikelyAndroidWebView() {
        const userAgent = navigator.userAgent || '';

        return /; wv\)/i.test(userAgent) ||
            /\bwv\b/i.test(userAgent) ||
            /Version\/[\d.]+ Chrome\/[\d.]+ Mobile Safari\/[\d.]+/i.test(userAgent) ||
            Boolean(window.ReactNativeWebView);
    }

    function emphasizeManualCameraFallback(enabled = true) {
        const manualButton = document.getElementById('manualSelfieButton');
        const manualButtonIcon = manualButton ? manualButton.querySelector('i') : null;

        if (!manualButton) {
            return;
        }

        if (!manualSelfieEnabled) {
            manualButton.classList.add('d-none');
            manualButton.disabled = true;
            return;
        }

        manualButton.classList.toggle('btn-outline-secondary', !enabled);
        manualButton.classList.toggle('btn-primary', enabled);
        manualButton.classList.toggle('btn-outline-primary', false);

        if (manualButtonIcon) {
            manualButtonIcon.className = enabled ? 'fas fa-camera me-2' : 'fas fa-camera-retro me-2';
        }

        const labelNode = Array.from(manualButton.childNodes).find(function(node) {
            return node.nodeType === Node.TEXT_NODE;
        });

        if (labelNode) {
            labelNode.textContent = enabled ? ' Gunakan Kamera Manual' : ' Kamera Manual';
        } else {
            manualButton.appendChild(document.createTextNode(enabled ? ' Gunakan Kamera Manual' : ' Kamera Manual'));
        }
    }

    function describeCameraAccessError(error) {
        const isWebView = isLikelyAndroidWebView();
        const errorName = error && error.name ? error.name : '';

        if (errorName === 'NotAllowedError' || errorName === 'PermissionDeniedError') {
            return isWebView ?
                'Izin kamera belum diberikan oleh aplikasi WebView. Aktifkan permission CAMERA di aplikasi lalu coba lagi.' :
                'Izin kamera ditolak oleh browser. Izinkan akses kamera lalu coba lagi.';
        }

        if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
            return 'Kamera depan tidak ditemukan pada perangkat ini.';
        }

        if (errorName === 'NotReadableError' || errorName === 'TrackStartError') {
            return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi kamera lain lalu coba lagi.';
        }

        if (errorName === 'OverconstrainedError' || errorName === 'ConstraintNotSatisfiedError') {
            return 'Konfigurasi kamera tidak cocok di perangkat ini. Coba ulangi dengan fallback kamera manual.';
        }

        return isWebView ?
            'WebView belum mengizinkan kamera live. Biasanya perlu izin native CAMERA dan grant `RESOURCE_VIDEO_CAPTURE` di aplikasi Android.' :
            'Akses kamera ditolak atau tidak tersedia. Gunakan upload manual bila perlu.';
    }

    function initMap(latOffice, longOffice, radius) {
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 17,
            center: {
                lat: latOffice,
                lng: longOffice
            },
            mapTypeId: "hybrid",
        });

        markerOffice = new google.maps.Marker({
            position: {
                lat: latOffice,
                lng: longOffice
            },
            map: map,
            title: "Lokasi presensi",
            icon: "https://maps.google.com/mapfiles/ms/icons/red-dot.png"
        });

        circleOffice = new google.maps.Circle({
            strokeColor: "#fd0d0d",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#fd0d0d",
            fillOpacity: 0.2,
            map,
            center: {
                lat: latOffice,
                lng: longOffice
            },
            radius: radius
        });
    }

    function getDistance(lat1, lon1, lat2, lon2) {
        let R = 6371000;
        let dLat = (lat2 - lat1) * Math.PI / 180;
        let dLon = (lon2 - lon1) * Math.PI / 180;
        let a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) *
            Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        let c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const toRad = (value) => value * Math.PI / 180;

        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);

        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(lat1)) *
            Math.cos(toRad(lat2)) *
            Math.sin(dLon / 2) *
            Math.sin(dLon / 2);

        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return R * c;
    }

    function updateFaceStatus(message, type = 'secondary') {
        const badge = document.getElementById('faceStatusBadge');
        const alert = document.getElementById('faceVerificationAlert');
        const statusKey = type + ':' + message;

        if (badge) {
            if (badge.dataset.statusKey === statusKey) {
                return;
            }

            badge.dataset.statusKey = statusKey;
            badge.className = 'face-status-chip bg-' + type + (type === 'warning' ? ' text-dark' : '') + (type === 'light' ? ' text-muted' : ' text-white');
            badge.textContent = message;
        }

        if (alert) {
            alert.dataset.statusKey = statusKey;
            alert.className = 'alert alert-' + type + ' mt-3 mb-3';
            alert.textContent = message;
        }
    }

    function resetFaceVerificationState() {
        faceVerificationPassed = false;
        cameraValidationStartedAt = null;
        cameraVerificationLocked = false;

        resetFaceMatchedReady();
        livenessCaptureInProgress = false;

        blinkLivenessPassed = false;
        blinkLivenessScore = null;
        blinkLivenessMessage = '';

        document.getElementById('face_verified').value = '0';
        document.getElementById('face_distance').value = '';
        document.getElementById('face_detection_count').value = '0';
        document.getElementById('face_verification_meta').value = '';

        const captureDataInput = document.getElementById('selfie_capture_data');
        const selfieInput = document.getElementById('selfie_capture');

        const blinkPassedInput = document.getElementById('face_liveness_passed');
        const blinkScoreInput = document.getElementById('face_liveness_score');
        const blinkMessageInput = document.getElementById('face_liveness_message');

        if (captureDataInput) {
            captureDataInput.value = '';
        }

        if (selfieInput) {
            selfieInput.value = '';
            delete selfieInput.dataset.skipNextChange;
        }

        if (blinkPassedInput) {
            blinkPassedInput.value = '0';
        }

        if (blinkScoreInput) {
            blinkScoreInput.value = '';
        }

        if (blinkMessageInput) {
            blinkMessageInput.value = '';
        }

        updateAttendanceButtonState();
    }

    function updateAttendanceButtonState() {
        document.querySelectorAll(".btn-absen").forEach(button => {
            button.disabled = !(gpsReady && gpsEvidenceReady && faceVerificationPassed && blinkLivenessPassed);
        });
    }

    async function loadFaceModels() {
        if (!faceReferencePath) {
            updateFaceStatus('Foto referensi wajah belum tersedia.', 'warning');
            resetFaceVerificationState();
            return;
        }

        try {
            updateFaceStatus('Memuat model verifikasi wajah...', 'secondary');

            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(faceModelPath),
                faceapi.nets.faceLandmark68TinyNet.loadFromUri(faceModelPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(faceModelPath)
            ]);

            faceApiReady = true;
            updateFaceStatus('Model siap. Ambil selfie untuk memulai matching.', 'info');
        } catch (error) {
            faceApiReady = false;
            updateFaceStatus('Gagal memuat model verifikasi wajah.', 'danger');
        }

        updateAttendanceButtonState();
    }

    function createImageFromFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = function(event) {
                const image = new Image();
                image.onload = function() {
                    resolve(image);
                };
                image.onerror = reject;
                image.src = event.target.result;
            };

            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    async function detectFaceDescriptors(image) {
        const options = new faceapi.TinyFaceDetectorOptions({
            inputSize: 320,
            scoreThreshold: 0.5
        });

        return faceapi
            .detectAllFaces(image, options)
            .withFaceLandmarks(true)
            .withFaceDescriptors();
    }

    async function ensureReferenceFaceDescriptor() {
        if (referenceFaceDescriptor) {
            return referenceFaceDescriptor;
        }

        const referenceImage = document.getElementById('referenceFaceImage');

        if (!referenceImage) {
            throw new Error('Foto referensi tidak ditemukan.');
        }

        if (!referenceImage.complete) {
            await new Promise((resolve, reject) => {
                referenceImage.onload = resolve;
                referenceImage.onerror = reject;
            });
        }

        const detections = await detectFaceDescriptors(referenceImage);

        if (detections.length !== 1) {
            throw new Error('Foto referensi harus memuat tepat satu wajah.');
        }

        referenceFaceDescriptor = detections[0].descriptor;
        return referenceFaceDescriptor;
    }

    function setSelfieSurfaceMode(mode) {
        const video = document.getElementById('selfieCamera');
        const overlay = document.getElementById('selfieOverlay');
        const preview = document.getElementById('selfiePreview');
        const placeholder = document.getElementById('selfiePlaceholder');

        if (mode !== 'live' && blinkInterval) {
            clearInterval(blinkInterval);
            blinkInterval = null;
        }

        if (video) {
            video.classList.toggle('d-none', mode !== 'live');
        }

        if (overlay) {
            overlay.classList.toggle('d-none', mode !== 'live');
        }

        if (preview) {
            preview.classList.toggle('d-none', mode !== 'preview');
        }

        if (placeholder) {
            placeholder.classList.toggle('d-none', mode !== 'placeholder');
        }
    }

    function clearSelfiePreview() {
        const selfiePreview = document.getElementById('selfiePreview');

        if (cameraPreviewUrl) {
            URL.revokeObjectURL(cameraPreviewUrl);
            cameraPreviewUrl = null;
        }

        if (selfiePreview) {
            selfiePreview.removeAttribute('src');
        }
    }

    function syncLiveCameraOverlaySize() {
        const video = document.getElementById('selfieCamera');
        const overlay = document.getElementById('selfieOverlay');

        if (!video || !overlay) {
            return null;
        }

        const width = Math.max(Math.round(video.clientWidth || 0), 1);
        const height = Math.max(Math.round(video.clientHeight || 0), 1);

        if (overlay.width !== width || overlay.height !== height) {
            overlay.width = width;
            overlay.height = height;
        }

        return {
            width,
            height
        };
    }

    function getLiveGuideRect(width, height) {
        return {
            x: width * 0.18,
            y: height * 0.10,
            width: width * 0.64,
            height: height * 0.80
        };
    }

    function getFaceRollAngle(landmarks) {
        if (!landmarks) {
            return 0;
        }

        const averagePoint = function(points) {
            const total = points.reduce(function(accumulator, point) {
                return {
                    x: accumulator.x + point.x,
                    y: accumulator.y + point.y
                };
            }, {
                x: 0,
                y: 0
            });

            return {
                x: total.x / points.length,
                y: total.y / points.length
            };
        };

        const leftEye = averagePoint(landmarks.getLeftEye());
        const rightEye = averagePoint(landmarks.getRightEye());

        return Math.abs(Math.atan2(rightEye.y - leftEye.y, rightEye.x - leftEye.x) * (180 / Math.PI));
    }

    function drawLiveCameraOverlay(options = {}) {
        const overlay = document.getElementById('selfieOverlay');

        if (!overlay) {
            return;
        }

        const context = overlay.getContext('2d');

        if (!context) {
            return;
        }

        const displaySize = syncLiveCameraOverlaySize();

        if (!displaySize) {
            return;
        }

        const palette = {
            neutral: '#94a3b8',
            validating: '#0d6efd',
            red: '#dc3545',
            yellow: '#ffc107',
            green: '#198754'
        };
        const state = options.state || 'neutral';
        const guideRect = getLiveGuideRect(displaySize.width, displaySize.height);
        const boxes = Array.isArray(options.boxes) ? options.boxes : [];

        const strokeRoundedRect = function(x, y, width, height, radius) {
            context.beginPath();
            context.moveTo(x + radius, y);
            context.lineTo(x + width - radius, y);
            context.quadraticCurveTo(x + width, y, x + width, y + radius);
            context.lineTo(x + width, y + height - radius);
            context.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
            context.lineTo(x + radius, y + height);
            context.quadraticCurveTo(x, y + height, x, y + height - radius);
            context.lineTo(x, y + radius);
            context.quadraticCurveTo(x, y, x + radius, y);
            context.closePath();
            context.stroke();
        };

        context.clearRect(0, 0, overlay.width, overlay.height);
        context.setLineDash([10, 8]);
        context.lineWidth = 3;
        context.strokeStyle = palette[state] || palette.neutral;
        strokeRoundedRect(guideRect.x, guideRect.y, guideRect.width, guideRect.height, 42);
        context.setLineDash([]);

        boxes.forEach(function(box) {
            context.lineWidth = 3;
            context.strokeStyle = palette[state] || palette.neutral;
            strokeRoundedRect(box.x, box.y, box.width, box.height, 14);
        });
    }

    function updateLiveFrameFeedback(state, message, progress = 0) {
        const stage = document.getElementById('selfieCameraStage');
        const badge = document.getElementById('cameraFrameBadge');
        const label = document.getElementById('cameraGuideLabel');
        const messageElement = document.getElementById('cameraFrameMessage');
        const holdBar = document.getElementById('cameraHoldBar');
        const progressText = document.getElementById('cameraProgressText');
        const assistText = document.getElementById('cameraAssistText');
        const states = {
            neutral: {
                badge: 'Siaga',
                alert: 'secondary',
                guide: 'Posisikan wajah di area tengah',
                assist: 'Sistem sedang menyiapkan pembacaan wajah dan akan menangkap selfie otomatis setelah validasi singkat.'
            },
            validating: {
                badge: 'Memastikan',
                alert: 'info',
                guide: 'Pastikan wajah tetap stabil',
                assist: 'Wajah sudah cocok. Sistem sedang memastikan posisi dan kejernihan tetap konsisten sebelum selfie disimpan.'
            },
            red: {
                badge: 'Atur Ulang',
                alert: 'danger',
                guide: 'Sesuaikan posisi wajah',
                assist: 'Periksa posisi wajah, jarak kamera, dan pastikan hanya satu orang yang terlihat.'
            },
            yellow: {
                badge: 'Perjelas',
                alert: 'warning',
                guide: 'Perjelas tampilan wajah',
                assist: 'Coba hadap lurus ke kamera, kurangi blur, dan pastikan pencahayaan cukup merata.'
            },
            green: {
                badge: 'Cocok',
                alert: 'success',
                guide: 'Verifikasi berhasil',
                assist: 'Bagus. Wajah sudah cocok dan selfie akan langsung digunakan untuk presensi.'
            }
        };
        const config = states[state] || states.neutral;
        const safeProgress = Math.max(0, Math.min(progress, 1));

        if (stage) {
            stage.dataset.frameState = state;
        }

        if (badge) {
            badge.className = 'camera-frame-chip is-' + state;
            badge.textContent = config.badge;
        }

        if (label) {
            label.textContent = config.guide;
        }

        if (messageElement) {
            messageElement.textContent = message;
        }

        if (holdBar) {
            holdBar.style.width = (safeProgress * 100) + '%';
        }

        if (progressText) {
            if (state === 'validating') {
                progressText.textContent = 'Memastikan kestabilan wajah...';
            } else if (state === 'green') {
                progressText.textContent = 'Selfie siap digunakan';
            } else if (state === 'yellow') {
                progressText.textContent = 'Verifikasi hampir siap';
            } else if (state === 'red') {
                progressText.textContent = 'Belum memenuhi syarat';
            } else {
                progressText.textContent = 'Menunggu pembacaan wajah';
            }
        }

        if (assistText) {
            assistText.textContent = config.assist;
        }

        updateFaceStatus(config.badge + ': ' + message, config.alert);
    }

    function fillFaceVerificationInputs(payload) {
        const matched = Boolean(payload.matched);
        const detectionCount = Number(payload.detectionCount || 0);
        const distanceValue = typeof payload.distance === 'number' ? payload.distance.toFixed(6) : '';

        document.getElementById('face_verified').value = matched ? '1' : '0';
        document.getElementById('face_distance').value = distanceValue;
        document.getElementById('face_detection_count').value = String(detectionCount);
        document.getElementById('face_verification_meta').value = JSON.stringify({
            threshold: faceDistanceThreshold,
            distance: distanceValue ? Number(distanceValue) : null,
            detection_count: detectionCount,
            reference: 'employee_face_reference',
            source: payload.source,
            challenge_id: attendanceChallenge ? attendanceChallenge.id : null,
            challenge_elapsed_ms: attendanceChallengeElapsedMs(),
            detection_score: payload.detectionScore ?? null,
            roll_angle: payload.rollAngle ?? null,
            frame_state: payload.frameState ?? null,
            client_liveness: {
                type: 'blink',
                passed: blinkLivenessPassed,
                score: blinkLivenessScore,
                message: blinkLivenessMessage,
                challenge_id: attendanceChallenge ? attendanceChallenge.id : null,
                challenge_action: attendanceChallenge ? attendanceChallenge.action : null,
                checked_at_client: new Date().toISOString()
            },
            verified_at_client: new Date().toISOString()
        });
    }

    function stopLiveCamera(options = {}) {
        const preservePreview = options.preservePreview === true;
        const video = document.getElementById('selfieCamera');

        if (cameraDetectionIntervalId) {
            window.clearInterval(cameraDetectionIntervalId);
            cameraDetectionIntervalId = null;
        }

        cameraValidationStartedAt = null;
        cameraIsProcessing = false;

        if (cameraStream) {
            cameraStream.getTracks().forEach(function(track) {
                track.stop();
            });
            cameraStream = null;
        }

        if (video) {
            video.pause();
            video.srcObject = null;
        }

        if (!preservePreview) {
            setSelfieSurfaceMode('placeholder');
            drawLiveCameraOverlay();
        }
    }

    async function captureLiveSelfie(metadata) {
        const video = document.getElementById('selfieCamera');
        const selfieInput = document.getElementById('selfie_capture');
        const captureDataInput = document.getElementById('selfie_capture_data');
        const selfiePreview = document.getElementById('selfiePreview');

        if (!video || !video.videoWidth || !video.videoHeight) {
            throw new Error('Kamera belum siap untuk menyimpan selfie.');
        }

        const captureScale = Math.min(1, selfieMaxCaptureWidth / video.videoWidth);
        const captureCanvas = document.createElement('canvas');
        captureCanvas.width = Math.round(video.videoWidth * captureScale);
        captureCanvas.height = Math.round(video.videoHeight * captureScale);
        captureCanvas.getContext('2d').drawImage(video, 0, 0, captureCanvas.width, captureCanvas.height);

        const captureBlob = await new Promise(function(resolve) {
            captureCanvas.toBlob(resolve, 'image/jpeg', selfieJpegQuality);
        });

        if (!captureBlob) {
            throw new Error('Selfie tidak berhasil disimpan dari kamera.');
        }

        let fileAttached = false;

        if (selfieInput && window.DataTransfer) {
            try {
                const file = new File([captureBlob], 'selfie-live-' + Date.now() + '.jpg', {
                    type: 'image/jpeg'
                });
                const transfer = new DataTransfer();
                transfer.items.add(file);
                selfieInput.dataset.skipNextChange = '1';
                selfieInput.files = transfer.files;
                fileAttached = true;

                if (captureDataInput) {
                    captureDataInput.value = '';
                }
            } catch (error) {
                // Hidden input base64 tetap menjadi fallback utama bila browser menolak assignment file.
            }
        }

        if (!fileAttached && captureDataInput) {
            captureDataInput.value = captureCanvas.toDataURL('image/jpeg', selfieJpegQuality);
        }

        clearSelfiePreview();
        cameraPreviewUrl = URL.createObjectURL(captureBlob);

        if (selfiePreview) {
            selfiePreview.src = cameraPreviewUrl;
        }

        fillFaceVerificationInputs({
            matched: true,
            distance: metadata.distance,
            detectionCount: 1,
            source: 'live-camera',
            detectionScore: metadata.detectionScore,
            rollAngle: metadata.rollAngle,
            frameState: 'green'
        });

        faceVerificationPassed = true;
        cameraVerificationLocked = true;
        setSelfieSurfaceMode('preview');
        stopLiveCamera({
            preservePreview: true
        });
        updateAttendanceButtonState();
        updateLiveFrameFeedback('green', 'Wajah cocok. Selfie tersimpan, lanjutkan ke tahap presensi.', 1);

        if (typeof window.setAttendanceWizardStep === 'function') {
            window.setAttendanceWizardStep(2, {
                scroll: true
            });
        }
    }

    async function runFaceVerificationFromFile(file) {
        const selfiePreview = document.getElementById('selfiePreview');
        const captureDataInput = document.getElementById('selfie_capture_data');

        stopLiveCamera();
        clearSelfiePreview();

        if (captureDataInput) {
            captureDataInput.value = '';
        }

        try {
            updateLiveFrameFeedback('neutral', 'Menganalisis selfie manual...', 0);

            cameraPreviewUrl = URL.createObjectURL(file);

            if (selfiePreview) {
                selfiePreview.src = cameraPreviewUrl;
            }

            setSelfieSurfaceMode('preview');

            const selfieImage = await createImageFromFile(file);
            const referenceDescriptor = await ensureReferenceFaceDescriptor();
            const selfieDetections = await detectFaceDescriptors(selfieImage);

            document.getElementById('face_detection_count').value = String(selfieDetections.length);

            if (selfieDetections.length !== 1) {
                faceVerificationPassed = false;
                updateAttendanceButtonState();
                updateLiveFrameFeedback('red', 'Selfie manual harus memuat tepat satu wajah.', 0);
                return;
            }

            const rollAngle = getFaceRollAngle(selfieDetections[0].landmarks);
            const distance = faceapi.euclideanDistance(referenceDescriptor, selfieDetections[0].descriptor);
            const matched = distance <= faceDistanceThreshold;

            fillFaceVerificationInputs({
                matched,
                distance,
                detectionCount: selfieDetections.length,
                source: 'manual-upload',
                detectionScore: Number(selfieDetections[0].detection.score.toFixed(4)),
                rollAngle: Number(rollAngle.toFixed(2)),
                frameState: matched ? 'green' : 'red'
            });

            faceVerificationPassed = matched;
            updateAttendanceButtonState();

            if (matched) {
                updateLiveFrameFeedback('green', 'Verifikasi wajah berhasil dari upload manual.', 1);

                if (typeof window.setAttendanceWizardStep === 'function') {
                    window.setAttendanceWizardStep(2, {
                        scroll: true
                    });
                }
            } else {
                updateLiveFrameFeedback('red', 'Wajah pada upload manual belum cocok dengan referensi.', 0);
            }
        } catch (error) {
            faceVerificationPassed = false;
            updateAttendanceButtonState();
            updateLiveFrameFeedback('red', error.message || 'Verifikasi selfie manual gagal dijalankan.', 0);
        }
    }

    async function evaluateLiveCameraFrame() {
        const video = document.getElementById('selfieCamera');

        if (!video || video.readyState < 2 || cameraVerificationLocked) {
            return;
        }

        const displaySize = syncLiveCameraOverlaySize();

        if (!displaySize) {
            return;
        }

        if (!faceApiReady) {
            drawLiveCameraOverlay({
                state: 'neutral'
            });
            updateLiveFrameFeedback('neutral', 'Kamera aktif. Model verifikasi sedang disiapkan...', 0);
            return;
        }

        const referenceDescriptor = await ensureReferenceFaceDescriptor();
        const detections = await detectFaceDescriptors(video);
        const resizedDetections = faceapi.resizeResults(detections, displaySize);

        document.getElementById('face_detection_count').value = String(detections.length);
        document.getElementById('face_verified').value = '0';
        faceVerificationPassed = false;
        updateAttendanceButtonState();

        if (detections.length === 0) {
            cameraValidationStartedAt = null;
            document.getElementById('face_distance').value = '';
            drawLiveCameraOverlay({
                state: 'red'
            });
            updateLiveFrameFeedback('red', 'Wajah belum terdeteksi. Dekatkan wajah ke tengah frame.', 0);
            return;
        }

        if (detections.length > 1) {
            cameraValidationStartedAt = null;
            document.getElementById('face_distance').value = '';
            drawLiveCameraOverlay({
                state: 'red',
                boxes: resizedDetections.map(function(detection) {
                    return detection.detection.box;
                })
            });
            updateLiveFrameFeedback('red', 'Terdeteksi lebih dari satu wajah. Pastikan hanya satu orang di kamera.', 0);
            return;
        }

        const detection = detections[0];
        const resizedDetection = resizedDetections[0];
        const faceBox = resizedDetection.detection.box;
        const guideRect = getLiveGuideRect(displaySize.width, displaySize.height);
        const centerX = faceBox.x + (faceBox.width / 2);
        const centerY = faceBox.y + (faceBox.height / 2);
        const insideGuide =
            centerX >= guideRect.x + (guideRect.width * 0.16) &&
            centerX <= guideRect.x + (guideRect.width * 0.84) &&
            centerY >= guideRect.y + (guideRect.height * 0.18) &&
            centerY <= guideRect.y + (guideRect.height * 0.82);
        const faceHeightRatio = faceBox.height / displaySize.height;
        const detectionScore = Number(detection.detection.score.toFixed(4));
        const rollAngle = Number(getFaceRollAngle(detection.landmarks).toFixed(2));
        const distance = faceapi.euclideanDistance(referenceDescriptor, detection.descriptor);

        fillFaceVerificationInputs({
            matched: false,
            distance,
            detectionCount: 1,
            source: 'live-camera',
            detectionScore,
            rollAngle,
            frameState: 'neutral'
        });

        if (!insideGuide) {
            cameraValidationStartedAt = null;
            drawLiveCameraOverlay({
                state: 'red',
                boxes: [faceBox]
            });
            updateLiveFrameFeedback('red', 'Posisikan wajah tepat di tengah frame panduan.', 0);
            return;
        }

        if (detectionScore < 0.78 || faceHeightRatio < 0.32 || faceHeightRatio > 0.76 || rollAngle > 12) {
            cameraValidationStartedAt = null;
            drawLiveCameraOverlay({
                state: 'yellow',
                boxes: [faceBox]
            });
            updateLiveFrameFeedback('yellow', 'Wajah belum jelas. Hadap lurus, dekatkan secukupnya, dan jaga tetap stabil.', 0);
            return;
        }

        if (distance > faceDistanceThreshold) {
            cameraValidationStartedAt = null;
            drawLiveCameraOverlay({
                state: 'red',
                boxes: [faceBox]
            });
            updateLiveFrameFeedback('red', 'Wajah belum sesuai dengan foto referensi.', 0);
            return;
        }

        faceMatchedReady = true;
        latestFaceMatchCapturePayload = {
            distance,
            detectionScore,
            rollAngle
        };

        cameraValidationStartedAt = null;

        drawLiveCameraOverlay({
            state: 'green',
            boxes: [faceBox]
        });

        updateLiveFrameFeedback(
            'green',
            'Wajah sudah cocok. Kedipkan mata sekali untuk mengambil selfie.',
            0
        );

return;

        if (!cameraValidationStartedAt) {
            cameraValidationStartedAt = Date.now();
        }

        const cameraValidationProgress = Math.min((Date.now() - cameraValidationStartedAt) / cameraValidationDelayMs, 1);

        if (cameraValidationProgress < 1) {
            drawLiveCameraOverlay({
                state: 'validating',
                boxes: [faceBox]
            });
            updateLiveFrameFeedback('validating', 'Wajah cocok. Memastikan frame tetap stabil...', cameraValidationProgress);
            return;
        }

        drawLiveCameraOverlay({
            state: 'green',
            boxes: [faceBox]
        });
        cameraVerificationLocked = true;
        cameraValidationStartedAt = null;
        updateLiveFrameFeedback('green', 'Wajah cocok. Selfie sedang disimpan.', 1);

        try {
            await captureLiveSelfie({
                distance,
                detectionScore,
                rollAngle
            });
        } catch (error) {
            cameraVerificationLocked = false;
            throw error;
        }
    }

    function beginLiveCameraVerification() {
        if (cameraDetectionIntervalId) {
            window.clearInterval(cameraDetectionIntervalId);
        }

        cameraDetectionIntervalId = window.setInterval(async function() {
            if (cameraIsProcessing || cameraVerificationLocked) {
                return;
            }

            cameraIsProcessing = true;

            try {
                await evaluateLiveCameraFrame();
            } catch (error) {
                updateLiveFrameFeedback('red', error.message || 'Analisis kamera gagal dijalankan.', 0);
            } finally {
                cameraIsProcessing = false;
            }
        }, liveDetectionIntervalMs);
    }

    async function startLiveCamera() {
        const video = document.getElementById('selfieCamera');
        const isWebView = isLikelyAndroidWebView();

        if (!video || !faceReferencePath) {
            return;
        }

        if (!isAttendanceChallengeReady()) {
            updateLiveFrameFeedback('red', 'Sesi keamanan presensi belum siap atau sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.', 0);
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            emphasizeManualCameraFallback(true);
            updateLiveFrameFeedback('red', isWebView ?
                'WebView ini belum membuka dukungan kamera live. Presensi aman membutuhkan kamera live dari aplikasi.' :
                'Browser ini belum mendukung kamera live. Presensi aman membutuhkan kamera live.', 0);
            return;
        }

        if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
            emphasizeManualCameraFallback(true);
            updateLiveFrameFeedback('red', isWebView ?
                'Halaman WebView belum berjalan di HTTPS, jadi kamera live diblokir. Presensi aman membutuhkan HTTPS.' :
                'Kamera live membutuhkan HTTPS agar bisa aktif otomatis.', 0);
            return;
        }

        stopLiveCamera();
        cameraVerificationLocked = false;
        cameraValidationStartedAt = null;
        emphasizeManualCameraFallback(false);
        updateLiveFrameFeedback('neutral', 'Meminta akses kamera depan...', 0);

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: {
                        ideal: 'user'
                    },
                    width: {
                        ideal: 720
                    },
                    height: {
                        ideal: 960
                    }
                },
                audio: false
            });

            video.srcObject = cameraStream;
            await video.play();

            setSelfieSurfaceMode('live');
            startBlinkLiveness(video);

            syncLiveCameraOverlaySize();
            drawLiveCameraOverlay({
                state: 'neutral'
            });
            updateLiveFrameFeedback(
                'neutral',
                'Kamera aktif. Hadap lurus ke kamera. Setelah wajah cocok, kedipkan mata untuk mengambil selfie.',
                0
            );
            beginLiveCameraVerification();
        } catch (error) {
            setSelfieSurfaceMode('placeholder');
            emphasizeManualCameraFallback(true);
            updateLiveFrameFeedback('red', describeCameraAccessError(error), 0);
        }
    }

    function validateNaturalMovement() {
        if (positionHistory.length < 2) {
            return {
                status: false,
                reason: "Mengumpulkan data GPS..."
            };
        }

        let totalVariation = 0;
        let zeroMoveCount = 0;

        for (let i = 1; i < positionHistory.length; i++) {
            let distance = getDistance(
                positionHistory[i - 1].lat,
                positionHistory[i - 1].long,
                positionHistory[i].lat,
                positionHistory[i].long
            );

            totalVariation += distance;

            if (distance < 0.1) {
                zeroMoveCount++;
            }
        }

        if (zeroMoveCount >= positionHistory.length - 1) {
            return {
                status: true
            };
        }

        if (totalVariation < 0.5) {
            return {
                status: true
            };
        }

        if (totalVariation <= 25) {
            return {
                status: true
            };
        }

        return {
            status: false,
            reason: "Pergerakan tidak wajar"
        };
    }

    document.addEventListener("DOMContentLoaded", function() {
        let latOffice = {{ $lokasi->lat }};
        let longOffice = {{ $lokasi->long }};
        let radius = {{ $lokasi->radius }};

        let lastLat = null;
        let lastLong = null;
        let lastTime = null;
        let speed = null;
        const selfieInput = document.getElementById('selfie_capture');
        const retryCameraButton = document.getElementById('retryCameraButton');
        const manualSelfieButton = document.getElementById('manualSelfieButton');
        const wizardStepFace = document.getElementById('wizardStepFace');
        const wizardStepAttendance = document.getElementById('wizardStepAttendance');
        const wizardIndicatorFace = document.getElementById('wizardIndicatorFace');
        const wizardIndicatorAttendance = document.getElementById('wizardIndicatorAttendance');
        const attendanceStepHint = document.getElementById('attendanceStepHint');
        const wizardAttendanceContent = document.getElementById('wizardAttendanceContent');

        if (manualSelfieButton && !manualSelfieEnabled) {
            manualSelfieButton.classList.add('d-none');
            manualSelfieButton.disabled = true;
        }

        const refreshMapViewport = function() {
            if (!map || !window.google || !window.google.maps) {
                return;
            }

            google.maps.event.trigger(map, 'resize');

            if (markerUser) {
                map.panTo(markerUser.getPosition());
                return;
            }

            map.setCenter({
                lat: latOffice,
                lng: longOffice
            });
        };

        const setWizardStep = function(step, options = {}) {
            const scroll = options.scroll === true;
            const hasFaceStep = Boolean(wizardStepFace);
            const attendanceUnlocked = !hasFaceStep || step >= 2;

            if (wizardStepFace) {
                wizardStepFace.classList.toggle('is-active', step === 1);
                wizardStepFace.classList.toggle('is-done', step >= 2);
            }

            if (wizardIndicatorFace) {
                wizardIndicatorFace.classList.remove('is-active', 'is-done', 'is-locked');
                wizardIndicatorFace.classList.add(step >= 2 ? 'is-done' : 'is-active');
                wizardIndicatorFace.textContent = '1';
            }

            if (wizardStepAttendance) {
                wizardStepAttendance.classList.toggle('is-active', attendanceUnlocked);
                wizardStepAttendance.classList.toggle('is-locked', !attendanceUnlocked);
                wizardStepAttendance.classList.toggle('is-done', false);
            }

            if (wizardIndicatorAttendance) {
                wizardIndicatorAttendance.classList.remove('is-active', 'is-done', 'is-locked');
                wizardIndicatorAttendance.classList.add(attendanceUnlocked ? 'is-active' : 'is-locked');
            }

            if (attendanceStepHint) {
                attendanceStepHint.classList.toggle('is-success', attendanceUnlocked);
                attendanceStepHint.innerHTML = attendanceUnlocked ?
                    "<i class='fas fa-check-circle'></i> Tahap presensi sudah terbuka. Pastikan GPS stabil lalu lanjutkan presensi." :
                    "<i class='fas fa-lock'></i> Selesaikan selfie dan tunggu matching berhasil untuk membuka tahap presensi.";
            }

            if (wizardAttendanceContent) {
                wizardAttendanceContent.classList.toggle('d-none', hasFaceStep && !attendanceUnlocked);
            }

            if (attendanceUnlocked && scroll && wizardStepAttendance) {
                window.setTimeout(function() {
                    wizardStepAttendance.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    window.setTimeout(refreshMapViewport, 260);
                }, 120);
            }
        };

        window.setAttendanceWizardStep = setWizardStep;

        initMap(latOffice, longOffice, radius);
        updateAttendanceButtonState();
        setSelfieSurfaceMode(wizardStepFace ? 'placeholder' : 'preview');
        setWizardStep(wizardStepFace ? 1 : 2);
        if (isLikelyAndroidWebView()) {
            emphasizeManualCameraFallback(true);
            updateLiveFrameFeedback('neutral', 'Mode WebView terdeteksi. Kamera live akan dicoba dulu, tapi fallback kamera manual sudah disiapkan.', 0);
        }
        loadFaceModels();
        startLiveCamera();

        if (retryCameraButton) {
            retryCameraButton.addEventListener('click', function() {
                clearSelfiePreview();
                resetFaceVerificationState();
                setWizardStep(1);
                setSelfieSurfaceMode('placeholder');
                updateLiveFrameFeedback('neutral', 'Menyiapkan ulang kamera depan...', 0);
                startLiveCamera();
            });
        }

        if (manualSelfieButton && selfieInput && manualSelfieEnabled) {
            manualSelfieButton.addEventListener('click', function() {
                selfieInput.click();
            });
        }

        if (selfieInput) {
            selfieInput.addEventListener('change', async function(event) {
                if (selfieInput.dataset.skipNextChange === '1') {
                    delete selfieInput.dataset.skipNextChange;
                    return;
                }

                const file = event.target.files && event.target.files[0];

                clearSelfiePreview();
                resetFaceVerificationState();
                setWizardStep(1);

                if (!file) {
                    updateLiveFrameFeedback('neutral', 'Pilih selfie manual atau aktifkan kembali kamera.', 0);
                    startLiveCamera();
                    return;
                }

                await runFaceVerificationFromFile(file);
            });
        }

        window.addEventListener('resize', function() {
            syncLiveCameraOverlaySize();
        });

        window.addEventListener('beforeunload', function() {
            stopLiveCamera();
        });

        if (!navigator.geolocation) {
            document.getElementById("distanceInfo").innerHTML =
                "<span class='text-danger'>Browser tidak mendukung GPS</span>";
            return;
        }

        navigator.geolocation.watchPosition(function(position) {
            let latUser = position.coords.latitude;
            let longUser = position.coords.longitude;
            let accuracy = position.coords.accuracy;
            let now = Date.now();

            if (accuracy > 60) {
                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>GPS tidak valid (" + Math.round(accuracy) + "m)</span>";
                return;
            }

            if (lastLat !== null && lastTime !== null) {
                let distanceMove = getDistance(lastLat, lastLong, latUser, longUser);
                let timeDiff = (now - lastTime) / 1000;

                if (timeDiff > 0) {
                    speed = distanceMove / timeDiff;
                }

                if (speed > 50) {
                    gpsReady = false;
                    gpsEvidenceReady = false;
                    stableStartTime = null;
                    updateAttendanceButtonState();

                    document.getElementById("distanceInfo").innerHTML =
                        "<span class='text-danger'>Pergerakan tidak wajar terdeteksi</span>";
                    return;
                }
            }

            positionHistory.push({
                lat: latUser,
                long: longUser
            });

            if (positionHistory.length > 3) {
                positionHistory.shift();
            }

            lastLat = latUser;
            lastLong = longUser;
            lastTime = now;

            if (markerUser) {
                markerUser.setPosition({
                    lat: latUser,
                    lng: longUser
                });
            } else {
                markerUser = new google.maps.Marker({
                    position: {
                        lat: latUser,
                        lng: longUser
                    },
                    map: map,
                    title: "Posisi kamu",
                    icon: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
                });
            }

            currentDistance = getDistance(latUser, longUser, latOffice, longOffice);

            if (currentDistance > radius) {
                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>" +
                    currentDistance.toFixed(1) +
                    " meter (Di luar radius)</span>";

                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();
                return;
            }

            let naturalCheck = validateNaturalMovement();

            if (!naturalCheck.status) {
                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>" + naturalCheck.reason + "</span>";

                return;
            }

            if (position.coords.mocked === true) {
                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>Mock location terdeteksi</span>";

                return;
            }

            if (!stableStartTime) {
                stableStartTime = now;
            }

            if (now - stableStartTime < gpsValidationDelayMs) {
                gpsReady = false;
                gpsEvidenceReady = false;
                updateAttendanceButtonState();

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-warning'>" +
                    currentDistance.toFixed(1) +
                    " meter (Memvalidasi lokasi...)</span>";
                return;
            }

            gpsReady = true;
            updateAttendanceButtonState();

            document.getElementById("distanceInfo").innerHTML =
                "<span class='text-success'>" +
                currentDistance.toFixed(1) +
                " meter (Dalam Radius)</span>";

            document.getElementById("lat_user").value = latUser;
            document.getElementById("long_user").value = longUser;
            document.getElementById("accuracy_user").value = accuracy;
            document.getElementById("speed_user").value = speed ?? 0;

            let deviceInfo = {
                platform: navigator.platform,
                language: navigator.language,
                userAgent: navigator.userAgent,
                screen: window.screen.width + "x" + window.screen.height,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                memory: navigator.deviceMemory || "unknown",
                cores: navigator.hardwareConcurrency || "unknown"
            };

            document.getElementById("device_info").value = JSON.stringify(deviceInfo);

            if (gpsReady) {
                const isFirstGpsEvidence = !window.lastLogTime;
                const previousLogTime = window.lastLogTime || 0;
                const previousLat = window.lastLat;
                const previousLong = window.lastLong;
                const shouldSendGpsEvidence = isFirstGpsEvidence || (Date.now() - previousLogTime >= 5000);

                if (shouldSendGpsEvidence && !gpsLogInFlight) {
                    const distance = isFirstGpsEvidence ? null : calculateDistance(previousLat, previousLong, latUser, longUser);

                    if (!isFirstGpsEvidence && distance < 3 && Date.now() - previousLogTime < 60000) {
                        return;
                    }

                    if (accuracy > 60) {
                        return;
                    }

                    gpsLogInFlight = true;

                    fetch(gpsLogUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name=\"csrf-token\"]').content
                        },
                        body: JSON.stringify({
                            lat: latUser,
                            long: longUser,
                            accuracy: accuracy,
                            speed: speed ?? 0,
                        })
                    }).then(function(response) {
                        gpsEvidenceReady = response.ok;
                        updateAttendanceButtonState();
                    }).catch(function(err) {
                        gpsEvidenceReady = false;
                        updateAttendanceButtonState();
                        console.log("GPS Log Error:", err);
                    }).finally(function() {
                        gpsLogInFlight = false;
                    });

                    window.lastLat = latUser;
                    window.lastLong = longUser;
                    window.lastLogTime = Date.now();
                }
            }
        }, function() {
            document.getElementById("distanceInfo").innerHTML =
                "<span class='text-danger'>Gagal mengambil lokasi</span>";
        }, {
            enableHighAccuracy: true,
            timeout: 20000,
            maximumAge: 0
        });

        document.querySelectorAll(".btn-absen").forEach(button => {
            button.addEventListener("click", function() {
                if (!gpsReady) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'GPS belum tervalidasi',
                        text: 'Pastikan lokasi stabil sebelum absen.'
                    });
                    return;
                }

                if (!faceReferencePath) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Foto referensi belum tersedia',
                        text: 'Minta admin mengunggah foto referensi wajah terlebih dahulu.'
                    });
                    return;
                }

                if (!faceVerificationPassed) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Matching wajah belum selesai',
                        text: 'Arahkan wajah ke frame sampai indikator hijau muncul.'
                    });
                    return;
                }

                if (!isAttendanceChallengeReady()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sesi presensi kedaluwarsa',
                        text: 'Muat ulang halaman untuk mengambil sesi keamanan baru sebelum presensi.'
                    });
                    return;
                }

                if (this.dataset.submitting === '1') {
                    return;
                }

                let type = this.dataset.type;
                let form = document.getElementById("formAbsen");

                this.dataset.submitting = '1';
                document.querySelectorAll(".btn-absen").forEach(button => {
                    button.disabled = true;
                });

                Swal.fire({
                    title: 'Mengirim presensi...',
                    text: 'Mohon tunggu, selfie dan lokasi sedang dikirim.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                form.action = `${attendanceSubmitBaseUrl}/${type}`;
                window.setTimeout(() => form.submit(), 50);
            });
        });
    });
</script>

<script>
    let blinkInterval = null;
    let blinkDetected = false;
    let eyeWasOpen = false;
    let closedFrameCount = 0;
    let openFrameCount = 0;
    let blinkIsProcessing = false;

    const BLINK_SAMPLE_INTERVAL_MS = 70;
    const BLINK_BASELINE_FRAMES = 3;
    const BLINK_DROP_RATIO = 0.92;
    const BLINK_REOPEN_RATIO = 0.72;
    const BLINK_MIN_CLOSED_FRAMES = 1;
    const BLINK_MIN_DROP_ABSOLUTE = 0.010;

    let blinkOpenSamples = [];
    let blinkOpenBaseline = null;
    let blinkClosedDetected = false;
    let blinkReopenedDetected = false;

    function distance(pointA, pointB) {
        const dx = pointA.x - pointB.x;
        const dy = pointA.y - pointB.y;
        return Math.sqrt((dx * dx) + (dy * dy));
    }
    
    function calculateEAR(eye) {
        const vertical1 = distance(eye[1], eye[5]);
        const vertical2 = distance(eye[2], eye[4]);
        const horizontal = distance(eye[0], eye[3]);

        if (horizontal === 0) {
            return 0;
        }

        return (vertical1 + vertical2) / (2.0 * horizontal);
    }

    function setBlinkResult(passed, score, message) {
        blinkLivenessPassed = passed;
        blinkLivenessScore = score;
        blinkLivenessMessage = message;

        const passedInput = document.getElementById('face_liveness_passed');
        const scoreInput = document.getElementById('face_liveness_score');
        const messageInput = document.getElementById('face_liveness_message');
        const status = document.getElementById('blinkStatus');

        if (passedInput) {
            passedInput.value = passed ? '1' : '0';
        }

        if (scoreInput) {
            scoreInput.value = score !== null ? Number(score).toFixed(4) : '';
        }

        if (messageInput) {
            messageInput.value = message;
        }

        if (status) {
            status.className = passed ? 'small text-success mb-2' : 'small text-warning mb-2';
            status.innerText = message;
        }

        updateAttendanceButtonState();
    }

    async function captureSelfieFromBlink(score, message) {
        if (livenessCaptureInProgress || cameraVerificationLocked) {
            return;
        }

        if (!faceMatchedReady || !latestFaceMatchCapturePayload) {
            setBlinkResult(false, score, 'Wajah harus cocok terlebih dahulu sebelum selfie diambil.');
            return;
        }

        livenessCaptureInProgress = true;
        blinkLivenessPassed = true;
        blinkLivenessScore = score;
        blinkLivenessMessage = message || 'Kedipan mata terdeteksi.';

        setBlinkResult(true, score, blinkLivenessMessage);

        if (blinkInterval) {
            clearInterval(blinkInterval);
            blinkInterval = null;
        }

        cameraVerificationLocked = true;
        cameraValidationStartedAt = null;

        updateLiveFrameFeedback(
            'green',
            'Kedipan terdeteksi. Selfie sedang diambil otomatis.',
            1
        );

        try {
            await captureLiveSelfie(latestFaceMatchCapturePayload);
        } catch (error) {
            cameraVerificationLocked = false;
            livenessCaptureInProgress = false;
            updateLiveFrameFeedback(
                'red',
                error.message || 'Gagal mengambil selfie dari liveness.',
                0
            );
        }
    }

    async function startBlinkLiveness(videoElement) {
        if (blinkInterval) {
            clearInterval(blinkInterval);
        }

        blinkDetected = false;
        blinkClosedDetected = false;
        blinkReopenedDetected = false;
        eyeWasOpen = false;
        closedFrameCount = 0;
        openFrameCount = 0;
        blinkOpenSamples = [];
        blinkOpenBaseline = null;

        setBlinkResult(false, null, 'Hadap lurus ke kamera, lalu kedipkan mata.');

        blinkInterval = setInterval(async () => {
            
            if (blinkIsProcessing) {
                return;
            }

            blinkIsProcessing = true;
            
            try {
                if (!videoElement || videoElement.readyState < 2) {
                    setBlinkResult(false, null, 'Kamera belum siap.');
                    return;
                }

                const detection = await faceapi
                    .detectSingleFace(
                        videoElement,
                        new faceapi.TinyFaceDetectorOptions({
                            inputSize: 160,
                            scoreThreshold: 0.35
                        })
                    )
                    .withFaceLandmarks(true);

                if (!detection) {
                    setBlinkResult(false, null, 'Wajah belum terdeteksi.');
                    return;
                }

                const landmarks = detection.landmarks;
                const leftEye = landmarks.getLeftEye();
                const rightEye = landmarks.getRightEye();

                const leftEAR = calculateEAR(leftEye);
                const rightEAR = calculateEAR(rightEye);
                const avgEAR = (leftEAR + rightEAR) / 2;

                if (!faceMatchedReady || !latestFaceMatchCapturePayload) {
                    setBlinkResult(
                        false,
                        avgEAR,
                        'Tunggu wajah cocok terlebih dahulu. Setelah cocok, kedipkan mata untuk mengambil selfie.'
                    );
                    return;
                }

                if (!blinkOpenBaseline) {
                    blinkOpenSamples.push(avgEAR);

                    if (blinkOpenSamples.length < BLINK_BASELINE_FRAMES) {
                        setBlinkResult(
                            false,
                            avgEAR,
                            'Hadap lurus. Membaca mata terbuka... EAR: ' + avgEAR.toFixed(3)
                        );
                        return;
                    }

                    blinkOpenBaseline = blinkOpenSamples.reduce((sum, value) => sum + value, 0) / blinkOpenSamples.length;
                    eyeWasOpen = true;

                    setBlinkResult(
                        false,
                        avgEAR,
                        'Baseline mata terbuka tersimpan. Sekarang kedipkan mata sekali.'
                    );
                    return;
                }

                const closedThreshold = Math.max(
                    0.08,
                    Math.min(
                        blinkOpenBaseline * BLINK_DROP_RATIO,
                        blinkOpenBaseline - BLINK_MIN_DROP_ABSOLUTE
                    )
                );

                const reopenThreshold = Math.max(
                    closedThreshold + 0.008,
                    blinkOpenBaseline * BLINK_REOPEN_RATIO
                );

                /*
                * Tahap 2:
                * Deteksi mata menutup.
                */
                if (!blinkClosedDetected && avgEAR <= closedThreshold) {
                    closedFrameCount++;

                    if (closedFrameCount >= BLINK_MIN_CLOSED_FRAMES) {
                        blinkClosedDetected = true;
                        setBlinkResult(
                            false,
                            avgEAR,
                            'Kedipan terbaca. Buka mata kembali... EAR: ' + avgEAR.toFixed(3)
                        );
                    }

                    return;
                }

                if (blinkClosedDetected && avgEAR >= reopenThreshold) {
                    blinkReopenedDetected = true;
                    blinkDetected = true;

                    await captureSelfieFromBlink(
                        avgEAR,
                        'Liveness berhasil. Kedipan mata terdeteksi.'
                    );

                    return;
                }

                if (!blinkClosedDetected) {
                    setBlinkResult(
                        false,
                        avgEAR,
                        'Kedipkan mata sekali.'
                    );
                } else {
                    setBlinkResult(
                        false,
                        avgEAR,
                        'Buka mata kembali. EAR: ' + avgEAR.toFixed(3) +
                        ' | target buka ≥ ' + reopenThreshold.toFixed(3)
                    );
                }
            } catch (error) {
                setBlinkResult(false, null, 'Gagal membaca kedipan mata.');
            } finally {
                blinkIsProcessing = false;
            }
        }, BLINK_SAMPLE_INTERVAL_MS);
    }
</script>
@endif

<script>
    $(document).ready(function() {
        $("#table-presensi").DataTable({
            responsive: true,
            order: [
                [0, 'desc']
            ]
        });
    });
</script>
@endpush