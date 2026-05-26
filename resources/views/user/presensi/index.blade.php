@extends('layouts.app')

@push('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin="">
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

$attendanceVerificationStatus = function ($record, string $type, bool $hasTime = true) {
if (!$record || !$hasTime) {
return null;
}

$verification = null;

if (isset($record->verifications) && $record->verifications instanceof \Illuminate\Support\Collection) {
$verification = $record->verifications->firstWhere('attendance_type', $type);
}

return $verification->status ?? ($record->status_absen ?? null);
};

$lokasi = $lokasi ?? null;
$isLocationReady = $isLocationReady ?? (
    $lokasi
    && is_numeric($lokasi->lat ?? null)
    && is_numeric($lokasi->long ?? null)
    && is_numeric($lokasi->radius ?? null)
    && (float) $lokasi->lat >= -90
    && (float) $lokasi->lat <= 90
    && (float) $lokasi->long >= -180
    && (float) $lokasi->long <= 180
    && (float) $lokasi->radius >= 1
);
$locationIssueMessage = $locationIssueMessage ?? (
    $lokasi
        ? 'Konfigurasi lokasi presensi divisi Anda belum lengkap. Hubungi HR/Admin untuk melengkapi titik koordinat dan radius.'
        : 'Lokasi presensi untuk divisi Anda belum diatur.'
);
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

        @if (!$isLocationReady)
        <div class="alert alert-danger">
            {{ $locationIssueMessage }}
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
        $nextType = $nextAttendanceType ?? null;
        $label = '';
        $btnClass = 'btn-primary';
        $btnIcon = 'fas fa-arrow-right';
        $actionTitle = 'Siap untuk langkah berikutnya';
        $actionText = 'Ambil selfie terlebih dahulu, tunggu matching berhasil, lalu lanjutkan presensi saat GPS valid.';
        $inactiveButtonLabel = 'Presensi Hari Ini Selesai';
        $inactiveButtonIcon = 'fas fa-check-circle';
        $inactiveButtonClass = 'btn-success';

        if ($statusPresensiHariIni) {
        $actionTitle = 'Status presensi tanggal aktif';
        $actionText = 'Tanggal presensi ini tercatat sebagai ' . $statusPresensiHariIni . '. Presensi jam tidak diperlukan.';
        } elseif ($nextType === 'masuk') {
        $label = 'Absen Masuk';
        $btnClass = 'btn-primary';
        $btnIcon = 'fas fa-sign-in-alt';
        $actionTitle = 'Absen masuk tersedia';
        $actionText = 'Ambil selfie dulu, pastikan matching berhasil, lalu sistem akan mengizinkan presensi masuk.';
        } elseif ($nextType === 'istirahat') {
        $label = 'Mulai Istirahat';
        $btnClass = 'btn-warning';
        $btnIcon = 'fas fa-mug-hot';
        $actionTitle = 'Mulai waktu istirahat';
        $actionText = 'Lanjutkan ke presensi istirahat setelah selfie cocok dan lokasi kamu tetap valid.';
        } elseif ($nextType === 'kembali') {
        $label = 'Kembali Istirahat';
        $btnClass = 'btn-info';
        $btnIcon = 'fas fa-undo-alt';
        $actionTitle = 'Kembali dari istirahat';
        $actionText = 'Sistem akan membuka tombol kembali setelah selfie terverifikasi dan GPS tetap sesuai.';
        } elseif ($nextType === 'pulang') {
        $label = 'Absen Pulang';
        $btnClass = 'btn-danger';
        $btnIcon = 'fas fa-sign-out-alt';
        $actionTitle = 'Tutup presensi tanggal aktif';
        $actionText = 'Ambil selfie terakhir, tunggu matching, lalu lakukan presensi pulang.';
        }

        $requiresFaceStep = (bool) ($nextType && $faceReferencePath && $attendanceChallenge);
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
                            <span class="attendance-step__value">Lokasi & Wajah</span>
                        </div>
                        <div class="attendance-step">
                            <span class="attendance-step__label">Langkah 2</span>
                            <span class="attendance-step__value">Simpan Presensi</span>
                        </div>
                    </div>
                </div>

                <div class="attendance-wizard">
                    @if ($requiresFaceStep)
                    <section id="wizardStepFace" class="attendance-stage attendance-stage--single-flow is-active">
                        <div class="attendance-stage__rail">
                            <span id="wizardIndicatorFace" class="attendance-stage__number is-active">1</span>
                        </div>

                        <div class="attendance-stage__main">
                            <div class="face-section__header">
                                <div>
                                    <span class="section-caption">Verifikasi Wajah</span>
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
                                        <div class="camera-stage__media-topbar" aria-hidden="true">
                                            <span id="cameraFrameBadge" class="camera-frame-chip is-neutral">Siaga</span>
                                            <span id="cameraProgressText" class="camera-stage__pill camera-stage__pill--secondary">
                                                Menunggu pembacaan wajah
                                            </span>
                                        </div>

                                        <div class="camera-live-location-panel" aria-live="polite">
                                            <div id="faceLiveMap" class="camera-live-location-panel__map"></div>
                                            <div class="camera-live-location-panel__content">
                                                <span class="camera-live-location-panel__eyebrow">
                                                    <i class="fas fa-location-arrow"></i>
                                                    Lokasi Live
                                                </span>
                                                <strong id="faceLiveLocationStatus" class="camera-live-location-panel__status">
                                                    Menunggu GPS...
                                                </strong>
                                                <span id="faceLiveLocationMeta" class="camera-live-location-panel__meta">
                                                    Izinkan lokasi agar titik live muncul.
                                                </span>
                                            </div>
                                        </div>

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

                    <section id="wizardStepAttendance" class="attendance-stage attendance-stage--merged is-active">
                        <div class="attendance-stage__main">
                            @if ($requiresFaceStep)
                            <div id="attendanceStepHint" class="attendance-stage__hint">
                                <i class="fas fa-sync-alt"></i>
                                GPS live dan liveness wajah diproses bersamaan. Tombol presensi aktif saat keduanya valid.
                            </div>
                            @endif

                            <div id="wizardAttendanceContent" class="attendance-unified-panel">
                                <div class="presensi-hidden-map" aria-hidden="true">
                                    <div id="map" class="map-surface"></div>
                                </div>

                                @if ($statusPresensiHariIni)
                                <div class="alert alert-info mb-2 mt-4">
                                    <strong>Status hari ini:</strong> {{ $statusPresensiHariIni }}
                                </div>
                                @endif

                                <div class="attendance-summary row g-3">
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            @php($masukVerification = $attendanceVerificationStatus($absensiHariIni ?? null, 'masuk', filled($absensiHariIni->jam_masuk ?? null)))
                                            <span class="attendance-metric__label">Masuk</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_masuk ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                            <span class="attendance-metric__status badge {{ \App\Models\Presensi::statusAbsenBadgeClass($masukVerification) }}">
                                                {{ $masukVerification ? \App\Models\Presensi::statusAbsenLabel($masukVerification) : 'Belum' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            @php($istirahatVerification = $attendanceVerificationStatus($absensiHariIni ?? null, 'istirahat', filled($absensiHariIni->jam_istirahat ?? null)))
                                            <span class="attendance-metric__label">Istirahat</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_istirahat ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                            <span class="attendance-metric__status badge {{ \App\Models\Presensi::statusAbsenBadgeClass($istirahatVerification) }}">
                                                {{ $istirahatVerification ? \App\Models\Presensi::statusAbsenLabel($istirahatVerification) : 'Belum' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            @php($kembaliVerification = $attendanceVerificationStatus($absensiHariIni ?? null, 'kembali', filled($absensiHariIni->jam_kembali_istirahat ?? null)))
                                            <span class="attendance-metric__label">Kembali</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_kembali_istirahat ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                            <span class="attendance-metric__status badge {{ \App\Models\Presensi::statusAbsenBadgeClass($kembaliVerification) }}">
                                                {{ $kembaliVerification ? \App\Models\Presensi::statusAbsenLabel($kembaliVerification) : 'Belum' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-6 col-lg-3">
                                        <div class="attendance-metric">
                                            @php($pulangVerification = $attendanceVerificationStatus($absensiHariIni ?? null, 'pulang', filled($absensiHariIni->jam_pulang ?? null)))
                                            <span class="attendance-metric__label">Pulang</span>
                                            <div class="attendance-metric__time">{{ $formatPresensiClock($absensiHariIni->jam_pulang ?? null, optional($absensiHariIni)->tanggal) }}</div>
                                            <span class="attendance-metric__status badge {{ \App\Models\Presensi::statusAbsenBadgeClass($pulangVerification) }}">
                                                {{ $pulangVerification ? \App\Models\Presensi::statusAbsenLabel($pulangVerification) : 'Belum' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div id="attendanceActionPanel" class="attendance-action-card">
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
                                        <button class="btn {{ $inactiveButtonClass }} shadow" disabled>
                                            <i class="{{ $inactiveButtonIcon }} me-2"></i>
                                            {{ $inactiveButtonLabel }}
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

                    <input type="hidden" name="presensi_challenge_id" id="presensi_challenge_id" value="{{ $attendanceChallenge['id'] ?? '' }}">
                    <input type="hidden" name="presensi_challenge_action" id="presensi_challenge_action" value="{{ $attendanceChallenge['liveness_action'] ?? 'turn_left_right' }}">

                    <input type="hidden" name="face_liveness_passed" id="face_liveness_passed" value="0">
                    <input type="hidden" name="face_liveness_type" id="face_liveness_type" value="{{ $attendanceChallenge['liveness_action'] ?? 'turn_left_right' }}">
                    <input type="hidden" name="face_liveness_score" id="face_liveness_score">
                    <input type="hidden" name="face_liveness_message" id="face_liveness_message">
                    <input type="hidden" name="face_liveness_evidence" id="face_liveness_evidence">

                    <input type="hidden" name="screen_spoof_score" id="screen_spoof_score" value="">
                    <input type="hidden" name="screen_spoof_reason" id="screen_spoof_reason" value="">
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
                            <th>{{ __('tables.date') }}</th>
                            <th>{{ __('tables.last_verification') }}</th>
                            <th>{{ __('tables.status') }}</th>
                            <th>{{ __('tables.shift') }}</th>
                            <th>{{ __('tables.clock_in') }}</th>
                            <th>{{ __('tables.break') }}</th>
                            <th>{{ __('tables.return_from_break') }}</th>
                            <th>{{ __('tables.clock_out') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($presensi as $item)
                        @php($verificationStatus = $item->status_absen ?? null)
                        @php($fulfillment = $item->attendance_fulfillment ?? null)
                        @php($masukVerification = $attendanceVerificationStatus($item, 'masuk', filled($item->jam_masuk)))
                        @php($istirahatVerification = $attendanceVerificationStatus($item, 'istirahat', filled($item->jam_istirahat)))
                        @php($kembaliVerification = $attendanceVerificationStatus($item, 'kembali', filled($item->jam_kembali_istirahat)))
                        @php($pulangVerification = $attendanceVerificationStatus($item, 'pulang', filled($item->jam_pulang)))
                        <tr>
                            <td>{{ formatDateIndonesia($item->tanggal) }}</td>
                            <td>
                                <span class="badge {{ \App\Models\Presensi::statusAbsenBadgeClass($verificationStatus) }}">
                                    {{ \App\Models\Presensi::statusAbsenLabel($verificationStatus) }}
                                </span>
                            </td>
                            <td>{{ \App\Models\Presensi::shortStatus($item->status_presensi) ?? '-' }}</td>
                            <td>{{ optional($item->resolved_shift)->code ?? 'AUTO' }}</td>
                            <td>
                                <div class="attendance-history-time">
                                    <span>{{ $formatPresensiClock($item->jam_masuk, $item->tanggal, '-') }}</span>
                                    @if($masukVerification)
                                    <span class="attendance-history-verification badge {{ \App\Models\Presensi::statusAbsenBadgeClass($masukVerification) }}">
                                        {{ \App\Models\Presensi::statusAbsenLabel($masukVerification) }}
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="attendance-history-time">
                                    <span>{{ $formatPresensiClock($item->jam_istirahat, $item->tanggal, '-') }}</span>
                                    @if($istirahatVerification)
                                    <span class="attendance-history-verification badge {{ \App\Models\Presensi::statusAbsenBadgeClass($istirahatVerification) }}">
                                        {{ \App\Models\Presensi::statusAbsenLabel($istirahatVerification) }}
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="attendance-history-time">
                                    <span>{{ $formatPresensiClock($item->jam_kembali_istirahat, $item->tanggal, '-') }}</span>
                                    @if($kembaliVerification)
                                    <span class="attendance-history-verification badge {{ \App\Models\Presensi::statusAbsenBadgeClass($kembaliVerification) }}">
                                        {{ \App\Models\Presensi::statusAbsenLabel($kembaliVerification) }}
                                    </span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="attendance-history-time">
                                    <span>{{ $formatPresensiClock($item->jam_pulang, $item->tanggal, '-') }}</span>
                                    @if($pulangVerification)
                                    <span class="attendance-history-verification badge {{ \App\Models\Presensi::statusAbsenBadgeClass($pulangVerification) }}">
                                        {{ \App\Models\Presensi::statusAbsenLabel($pulangVerification) }}
                                    </span>
                                    @endif
                                </div>
                            </td>
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
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="{{ versioned_asset('vendor/face-api/face-api.min.js') }}"></script>

@if ($isLocationReady)
<script>
    let map;
    let faceMap;
    let markerUser;
    let faceMarkerUser;
    let markerOffice;
    let faceMarkerOffice;
    let circleOffice;
    let faceCircleOffice;
    let currentDistance = 0;
    let stableStartTime = null;
    let gpsReady = false;
    let faceApiReady = false;
    let faceVerificationPassed = false;
    let referenceFaceDescriptor = null;
    let positionHistory = [];
    let gpsEvidenceReady = false;
    let gpsLogInFlight = false;
    let gpsEvidenceLastAttemptAt = 0;
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
    let livenessStartedAtMs = 0;
    let lastLivenessCapturedAtMs = 0;
    let livenessEvidence = {
        frames: []
    };

    const gpsValidationDelayMs = 2500;
    const gpsEvidenceRetryDelayMs = 5000;
    const gpsEvidenceReuseMs = 60000;
    const cameraValidationDelayMs = 900;
    const liveDetectionIntervalMs = 320;
    const faceDistanceThreshold = 0.5;
    const selfieMaxCaptureWidth = 720;
    const selfieJpegQuality = 0.82;
    const livenessEvidenceMaxWidth = 480;
    const livenessEvidenceJpegQuality = 0.76;
    const faceModelPath = @json(asset('vendor/face-api/weights'));
    const faceReferencePath = @json($faceReferenceUrl);
    const attendanceChallenge = @json($attendanceChallenge);
    const livenessAction = attendanceChallenge && attendanceChallenge.liveness_action ? attendanceChallenge.liveness_action : 'turn_left_right';
    const manualSelfieEnabled = false;
    const attendanceSubmitBaseUrl = @json(url('/absen'));
    const gpsLogUrl = @json(url('/api/gps-log'));
    const freeMapTileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    const freeMapAttribution = 'Lokasi presensi';

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

    function resetLivenessEvidence() {
        livenessStartedAtMs = (window.performance && performance.now) ? performance.now() : Date.now();
        lastLivenessCapturedAtMs = 0;
        livenessEvidence = {
            frames: [],
            started_at_client: new Date().toISOString()
        };

        const evidenceInput = document.getElementById('face_liveness_evidence');

        if (evidenceInput) {
            evidenceInput.value = '';
        }
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

    function createMapPinIcon(color) {
        const safeColor = color === 'red' ? 'red' : 'blue';

        return L.divIcon({
            className: 'attendance-map-pin attendance-map-pin--' + safeColor,
            html: '<span></span>',
            iconSize: [34, 44],
            iconAnchor: [17, 40],
            popupAnchor: [0, -38]
        });
    }

    function initMap(latOffice, longOffice, radius) {
        const officePosition = [latOffice, longOffice];

        map = L.map("map", {
            zoomControl: true,
            attributionControl: true
        }).setView(officePosition, 18);

        L.tileLayer(freeMapTileUrl, {
            maxZoom: 19,
            attribution: freeMapAttribution
        }).addTo(map);

        markerOffice = L.marker(officePosition, {
            title: "Lokasi presensi",
            icon: createMapPinIcon('red')
        }).addTo(map);

        circleOffice = L.circle(officePosition, {
            color: "#fd0d0d",
            opacity: 0.8,
            weight: 2,
            fillColor: "#fd0d0d",
            fillOpacity: 0.2,
            radius: radius
        }).addTo(map);
    }

    function initFaceLiveMap(latOffice, longOffice, radius) {
        const mapElement = document.getElementById('faceLiveMap');

        if (!mapElement || !window.L) {
            return;
        }

        const officePosition = [latOffice, longOffice];

        faceMap = L.map(mapElement, {
            zoomControl: false,
            attributionControl: false,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            tap: false,
            touchZoom: false,
        }).setView(officePosition, 18);

        L.tileLayer(freeMapTileUrl, {
            maxZoom: 19,
            attribution: freeMapAttribution
        }).addTo(faceMap);

        faceMarkerOffice = L.marker(officePosition, {
            title: 'Lokasi presensi',
            icon: createMapPinIcon('red')
        }).addTo(faceMap);

        faceCircleOffice = L.circle(officePosition, {
            color: '#fd0d0d',
            opacity: 0.8,
            weight: 1,
            fillColor: '#fd0d0d',
            fillOpacity: 0.10,
            radius: radius
        }).addTo(faceMap);

        window.setTimeout(function() {
            faceMap.invalidateSize();
        }, 250);
    }

    function syncUserMapMarker(latUser, longUser) {
        const userPosition = [latUser, longUser];

        if (map) {
            if (markerUser) {
                markerUser.setLatLng(userPosition);
            } else {
                markerUser = L.marker(userPosition, {
                    title: 'Posisi kamu',
                    icon: createMapPinIcon('blue')
                }).addTo(map);
            }
        }

        if (faceMap) {
            if (faceMarkerUser) {
                faceMarkerUser.setLatLng(userPosition);
            } else {
                faceMarkerUser = L.marker(userPosition, {
                    title: 'Posisi kamu',
                    icon: createMapPinIcon('blue')
                }).addTo(faceMap);
            }

            faceMap.setView(userPosition, Math.max(faceMap.getZoom() || 18, 18), {
                animate: true,
                duration: 0.35,
            });
            faceMap.invalidateSize();
        }
    }

    function updateFaceLiveLocation(status, title, meta) {
        const panel = document.querySelector('.camera-live-location-panel');
        const statusElement = document.getElementById('faceLiveLocationStatus');
        const metaElement = document.getElementById('faceLiveLocationMeta');

        if (panel) {
            panel.dataset.locationState = status || 'neutral';
        }

        if (statusElement) {
            statusElement.textContent = title || 'Menunggu GPS...';
        }

        if (metaElement) {
            metaElement.textContent = meta || 'Izinkan lokasi agar titik live muncul.';
        }
    }

    function faceLiveLocationMeta(distance, accuracy, suffix) {
        const parts = [];

        if (Number.isFinite(distance)) {
            parts.push('Jarak ' + Number(distance).toFixed(1) + 'm');
        }

        if (Number.isFinite(accuracy)) {
            parts.push('Akurasi ' + Math.round(Number(accuracy)) + 'm');
        }

        if (suffix) {
            parts.push(suffix);
        }

        return parts.join(' | ');
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
        resetLivenessEvidence();

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

    function updateDistanceInfo(statusClass, distance, message) {
        const distanceInfo = document.getElementById("distanceInfo");

        if (!distanceInfo) {
            return;
        }

        const status = document.createElement('span');
        status.className = statusClass;
        status.textContent = Number(distance).toFixed(1) + " meter (" + message + ")";

        distanceInfo.innerHTML = '';
        distanceInfo.appendChild(status);
    }

    function getAttendanceButtonBlockReason() {
        const livenessPassedInput = document.getElementById('face_liveness_passed');
        const livenessEvidenceInput = document.getElementById('face_liveness_evidence');
        const selfieDataInput = document.getElementById('selfie_capture_data');
        const selfieInput = document.getElementById('selfie_capture');
        const faceMetaInput = document.getElementById('face_verification_meta');
        const livenessReady = blinkLivenessPassed || (livenessPassedInput && livenessPassedInput.value === '1');
        const hasLivenessEvidence = livenessEvidenceInput && livenessEvidenceInput.value.trim() !== '';
        const hasSelfieCapture = (selfieDataInput && selfieDataInput.value.trim() !== '') ||
            (selfieInput && selfieInput.files && selfieInput.files.length > 0);
        const hasFaceMeta = faceMetaInput && faceMetaInput.value.trim() !== '';

        if (!gpsReady) {
            return 'GPS belum valid';
        }

        if (!gpsEvidenceReady) {
            return 'bukti GPS live belum tersimpan';
        }

        if (!livenessReady || !hasLivenessEvidence || !hasSelfieCapture || !hasFaceMeta) {
            return 'liveness wajah belum selesai';
        }

        if (!isAttendanceChallengeReady()) {
            return 'sesi presensi kedaluwarsa';
        }

        return '';
    }

    function updateAttendanceButtonState() {
        const blockReason = getAttendanceButtonBlockReason();

        document.querySelectorAll(".btn-absen").forEach(button => {
            button.disabled = Boolean(blockReason);
            button.title = blockReason ? 'Belum bisa presensi: ' + blockReason : '';
            button.setAttribute('aria-disabled', blockReason ? 'true' : 'false');
        });

        return !blockReason;
    }

    function updateGpsReadyInfo(distance) {
        const blockReason = getAttendanceButtonBlockReason();

        if (!blockReason) {
            updateDistanceInfo('text-success', distance, 'Siap presensi');
            return;
        }

        updateDistanceInfo('text-warning', distance, 'Lokasi siap - ' + blockReason);
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
                type: livenessAction,
                passed: blinkLivenessPassed,
                score: blinkLivenessScore,
                message: blinkLivenessMessage,
                challenge_id: attendanceChallenge ? attendanceChallenge.id : null,
                challenge_action: livenessAction,
                evidence_frames: (livenessEvidence.frames || []).map(function(frame) {
                    return {
                        label: frame.label,
                        captured_at_ms: frame.captured_at_ms,
                        ear: frame.ear ?? null,
                        yaw: frame.yaw ?? null
                    };
                }),
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

        if (!hasRequiredLivenessEvidence()) {
            throw new Error('Bukti liveness belum lengkap. Ulangi verifikasi dan ikuti instruksi hadap wajah.');
        }

        const captureScale = Math.min(1, selfieMaxCaptureWidth / video.videoWidth);
        const captureCanvas = document.createElement('canvas');
        captureCanvas.width = Math.round(video.videoWidth * captureScale);
        captureCanvas.height = Math.round(video.videoHeight * captureScale);
        captureCanvas.getContext('2d').drawImage(video, 0, 0, captureCanvas.width, captureCanvas.height);
        const spoofCheck = analyzeScreenSpoofFromCanvas(captureCanvas);

        document.getElementById('screen_spoof_score').value = spoofCheck.score;
        document.getElementById('screen_spoof_reason').value = JSON.stringify(spoofCheck);

        if (spoofCheck.score >= 45) {
            throw new Error('Selfie terindikasi berasal dari layar/foto. Gunakan wajah langsung di depan kamera.');
        }

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

        setBlinkResult(true, blinkLivenessScore ?? 1, blinkLivenessMessage || 'Liveness berhasil. Gerakan wajah terdeteksi.');
        faceVerificationPassed = true;
        cameraVerificationLocked = true;
        setSelfieSurfaceMode('preview');
        stopLiveCamera({
            preservePreview: true
        });
        updateAttendanceButtonState();
        updateLiveFrameFeedback('green', 'Wajah cocok. Selfie tersimpan, lanjutkan presensi saat GPS valid.', 1);

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
            'Wajah sudah cocok. Ikuti instruksi hadap tengah, kiri, lalu kanan.',
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
            startTurnLiveness(video);

            syncLiveCameraOverlaySize();
            drawLiveCameraOverlay({
                state: 'neutral'
            });
            updateLiveFrameFeedback(
                'neutral',
                'Kamera aktif. Hadap lurus ke kamera. Setelah wajah cocok, ikuti instruksi hadap wajah.',
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
        let latOffice = @json((float) $lokasi->lat);
        let longOffice = @json((float) $lokasi->long);
        let radius = @json((float) $lokasi->radius);
        let maxGpsAccuracy = Math.min(200, Math.max(60, Number(radius) * 0.25));

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
        const attendanceActionPanel = document.getElementById('attendanceActionPanel');

        if (manualSelfieButton && !manualSelfieEnabled) {
            manualSelfieButton.classList.add('d-none');
            manualSelfieButton.disabled = true;
        }

        const refreshMapViewport = function() {
            if (!map || !window.L) {
                return;
            }

            map.invalidateSize();

            if (faceMap) {
                faceMap.invalidateSize();

                if (faceMarkerUser) {
                    faceMap.panTo(faceMarkerUser.getLatLng());
                }
            }

            if (markerUser) {
                map.panTo(markerUser.getLatLng());
                return;
            }

            map.setView([latOffice, longOffice], map.getZoom() || 17);
        };

        const setWizardStep = function(step, options = {}) {
            const scroll = options.scroll === true;
            const hasFaceStep = Boolean(wizardStepFace && document.getElementById('selfieCameraStage'));
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
                wizardStepAttendance.classList.add('is-active');
                wizardStepAttendance.classList.toggle('is-locked', false);
                wizardStepAttendance.classList.toggle('is-done', attendanceUnlocked);
            }

            if (wizardIndicatorAttendance) {
                wizardIndicatorAttendance.classList.remove('is-active', 'is-done', 'is-locked');
                wizardIndicatorAttendance.classList.add(attendanceUnlocked ? 'is-active' : 'is-locked');
            }

            if (attendanceStepHint) {
                attendanceStepHint.classList.toggle('is-success', attendanceUnlocked);
                attendanceStepHint.innerHTML = attendanceUnlocked ?
                    "<i class='fas fa-check-circle'></i> Liveness wajah sudah valid. Sistem tetap memastikan GPS live stabil sebelum tombol presensi aktif." :
                    "<i class='fas fa-sync-alt'></i> GPS live dan liveness wajah diproses bersamaan. Tombol presensi aktif saat keduanya valid.";
            }

            if (wizardAttendanceContent) {
                wizardAttendanceContent.classList.toggle('is-unlocked', attendanceUnlocked);
            }

            if (attendanceUnlocked && scroll && (attendanceActionPanel || wizardStepAttendance)) {
                window.setTimeout(function() {
                    (attendanceActionPanel || wizardStepAttendance).scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    window.setTimeout(refreshMapViewport, 260);
                }, 120);
            }
        };

        window.setAttendanceWizardStep = setWizardStep;

        initMap(latOffice, longOffice, radius);
        initFaceLiveMap(latOffice, longOffice, radius);
        updateFaceLiveLocation('neutral', 'Menunggu GPS...', 'Sistem akan menyimpan bukti lokasi saat titik sudah stabil.');
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
            updateFaceLiveLocation('danger', 'Browser tidak mendukung GPS', 'Gunakan browser modern dengan izin lokasi aktif.');
            return;
        }

        navigator.geolocation.watchPosition(function(position) {
            let latUser = position.coords.latitude;
            let longUser = position.coords.longitude;
            let accuracy = position.coords.accuracy;
            let now = Date.now();
            let liveDistance = getDistance(latUser, longUser, latOffice, longOffice);

            syncUserMapMarker(latUser, longUser);
            updateFaceLiveLocation(
                'warning',
                'Membaca lokasi live...',
                faceLiveLocationMeta(liveDistance, accuracy, 'Menunggu titik stabil')
            );

            if (accuracy > maxGpsAccuracy) {
                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();
                updateFaceLiveLocation(
                    'danger',
                    'Akurasi GPS belum valid',
                    faceLiveLocationMeta(liveDistance, accuracy, 'Batas ' + Math.round(maxGpsAccuracy) + 'm')
                );

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>Akurasi GPS belum valid (" + Math.round(accuracy) + "m, batas " + Math.round(maxGpsAccuracy) + "m)</span>";
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
                    updateFaceLiveLocation(
                        'danger',
                        'Pergerakan tidak wajar',
                        faceLiveLocationMeta(liveDistance, accuracy, 'Kecepatan GPS terlalu tinggi')
                    );

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

            currentDistance = liveDistance;

            if (currentDistance > radius) {
                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>" +
                    currentDistance.toFixed(1) +
                    " meter (Di luar radius)</span>";

                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();
                updateFaceLiveLocation(
                    'danger',
                    'Di luar radius presensi',
                    faceLiveLocationMeta(currentDistance, accuracy, 'Radius ' + Math.round(radius) + 'm')
                );
                return;
            }

            let naturalCheck = validateNaturalMovement();

            if (!naturalCheck.status) {
                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();
                updateFaceLiveLocation(
                    'danger',
                    naturalCheck.reason,
                    faceLiveLocationMeta(currentDistance, accuracy, 'Validasi gerak GPS gagal')
                );

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-danger'>" + naturalCheck.reason + "</span>";

                return;
            }

            if (position.coords.mocked === true) {
                gpsReady = false;
                gpsEvidenceReady = false;
                stableStartTime = null;
                updateAttendanceButtonState();
                updateFaceLiveLocation(
                    'danger',
                    'Mock location terdeteksi',
                    faceLiveLocationMeta(currentDistance, accuracy, 'Presensi diblokir')
                );

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
                updateFaceLiveLocation(
                    'warning',
                    'Memvalidasi lokasi...',
                    faceLiveLocationMeta(currentDistance, accuracy, 'Tahan posisi sebentar')
                );

                document.getElementById("distanceInfo").innerHTML =
                    "<span class='text-warning'>" +
                    currentDistance.toFixed(1) +
                    " meter (Memvalidasi lokasi...)</span>";
                return;
            }

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

            gpsReady = true;

            if (gpsReady) {
                const currentTime = Date.now();
                const previousLogTime = Number(window.lastLogTime || 0);
                const previousLat = window.lastLat;
                const previousLong = window.lastLong;
                const hasReusableGpsEvidence = gpsEvidenceReady &&
                    previousLogTime > 0 &&
                    Number.isFinite(previousLat) &&
                    Number.isFinite(previousLong) &&
                    calculateDistance(previousLat, previousLong, latUser, longUser) < 3 &&
                    (currentTime - previousLogTime) < gpsEvidenceReuseMs;
                const canRetryGpsEvidence = (currentTime - gpsEvidenceLastAttemptAt) >= gpsEvidenceRetryDelayMs;
                const shouldSendGpsEvidence = !hasReusableGpsEvidence && canRetryGpsEvidence;

                if (hasReusableGpsEvidence) {
                    updateFaceLiveLocation(
                        'success',
                        'GPS live tersimpan',
                        faceLiveLocationMeta(currentDistance, accuracy, 'Siap bersama liveness')
                    );
                    updateGpsReadyInfo(currentDistance);
                } else if (gpsLogInFlight || shouldSendGpsEvidence) {
                    updateFaceLiveLocation(
                        'warning',
                        'Menyimpan bukti GPS live...',
                        faceLiveLocationMeta(currentDistance, accuracy, 'Jangan berpindah lokasi')
                    );
                    updateDistanceInfo('text-warning', currentDistance, 'Dalam Radius - menyimpan bukti GPS live...');
                } else if (!gpsEvidenceReady) {
                    updateFaceLiveLocation(
                        'warning',
                        'Menunggu kirim ulang GPS',
                        faceLiveLocationMeta(currentDistance, accuracy, 'Dalam radius')
                    );
                    updateDistanceInfo('text-warning', currentDistance, 'Dalam Radius - menunggu kirim ulang bukti GPS');
                } else {
                    updateFaceLiveLocation(
                        'success',
                        'Lokasi dalam radius',
                        faceLiveLocationMeta(currentDistance, accuracy, 'Siap menyimpan bukti')
                    );
                    updateDistanceInfo('text-success', currentDistance, 'Dalam Radius');
                }

                updateAttendanceButtonState();

                if (shouldSendGpsEvidence && !gpsLogInFlight) {
                    if (accuracy > maxGpsAccuracy) {
                        return;
                    }

                    gpsLogInFlight = true;
                    gpsEvidenceLastAttemptAt = currentTime;

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
                    }).then(async function(response) {
                        gpsEvidenceReady = response.ok;

                        if (response.ok) {
                            window.lastLat = latUser;
                            window.lastLong = longUser;
                            window.lastLogTime = Date.now();
                            updateAttendanceButtonState();
                            updateFaceLiveLocation(
                                'success',
                                'GPS live tersimpan',
                                faceLiveLocationMeta(currentDistance, accuracy, 'Lanjutkan liveness wajah')
                            );
                            updateGpsReadyInfo(currentDistance);
                        } else {
                            let message = 'Bukti GPS live belum tersimpan';

                            try {
                                const payload = await response.json();

                                if (payload && payload.message) {
                                    message = payload.message;
                                }
                            } catch (error) {
                                message = 'Bukti GPS live gagal tersimpan';
                            }

                            updateDistanceInfo('text-danger', currentDistance, message);
                            updateFaceLiveLocation(
                                'danger',
                                message,
                                faceLiveLocationMeta(currentDistance, accuracy, 'Coba ulang otomatis')
                            );
                        }

                        updateAttendanceButtonState();
                    }).catch(function(err) {
                        gpsEvidenceReady = false;
                        updateDistanceInfo('text-danger', currentDistance, 'Bukti GPS live gagal tersimpan');
                        updateFaceLiveLocation(
                            'danger',
                            'Bukti GPS live gagal tersimpan',
                            faceLiveLocationMeta(currentDistance, accuracy, 'Periksa koneksi lalu tunggu ulang')
                        );
                        updateAttendanceButtonState();
                        console.log("GPS Log Error:", err);
                    }).finally(function() {
                        gpsLogInFlight = false;
                    });
                }
            }
        }, function() {
            document.getElementById("distanceInfo").innerHTML =
                "<span class='text-danger'>Gagal mengambil lokasi</span>";
            updateFaceLiveLocation('danger', 'Gagal mengambil lokasi', 'Periksa izin lokasi di browser.');
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

                if (!gpsEvidenceReady) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Bukti GPS belum tersimpan',
                        text: 'Tunggu beberapa detik sampai validasi GPS live selesai.'
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

                const blockReason = getAttendanceButtonBlockReason();

                if (blockReason) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Presensi belum siap',
                        text: blockReason
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
                    title: 'Menyimpan presensi...',
                    text: 'Mohon tunggu, data presensi sedang dicatat.',
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
    let blinkIsProcessing = false;

    const TURN_SAMPLE_INTERVAL_MS = 140;
    const TURN_CENTER_FRAMES = 3;
    const TURN_CENTER_MAX_YAW = 0.035;
    const TURN_MIN_YAW = 0.07;

    let turnStage = 'center';
    let turnCenterSamples = [];
    let turnLeftYaw = null;

    function distance(pointA, pointB) {
        const dx = pointA.x - pointB.x;
        const dy = pointA.y - pointB.y;
        return Math.sqrt((dx * dx) + (dy * dy));
    }

    function averagePoint(points) {
        if (!Array.isArray(points) || points.length === 0) {
            return null;
        }

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
    }

    function calculateHeadTurnMetric(landmarks) {
        const nose = landmarks.getNose();
        const leftEye = landmarks.getLeftEye();
        const rightEye = landmarks.getRightEye();
        const jaw = landmarks.getJawOutline();

        const noseTip = nose[3] || nose[Math.floor(nose.length / 2)];
        const leftEyeCenter = averagePoint(leftEye);
        const rightEyeCenter = averagePoint(rightEye);

        if (!noseTip || !leftEyeCenter || !rightEyeCenter) {
            return null;
        }

        const eyeCenter = {
            x: (leftEyeCenter.x + rightEyeCenter.x) / 2,
            y: (leftEyeCenter.y + rightEyeCenter.y) / 2
        };
        const eyeDistance = distance(leftEyeCenter, rightEyeCenter);
        const faceWidth = jaw && jaw.length >= 17 ?
            Math.max(distance(jaw[0], jaw[16]), eyeDistance * 2, 1) :
            Math.max(eyeDistance * 2, 1);
        const yaw = (noseTip.x - eyeCenter.x) / faceWidth;

        return {
            yaw: Number(yaw.toFixed(4)),
            absYaw: Number(Math.abs(yaw).toFixed(4))
        };
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

    function syncLivenessEvidenceInput() {
        const evidenceInput = document.getElementById('face_liveness_evidence');

        if (evidenceInput) {
            evidenceInput.value = JSON.stringify(livenessEvidence);
        }
    }

    function captureLivenessEvidenceFrame(label, videoElement, extra = {}) {
        if (!videoElement || !videoElement.videoWidth || !videoElement.videoHeight) {
            return;
        }

        if (!livenessStartedAtMs) {
            livenessStartedAtMs = (window.performance && performance.now) ? performance.now() : Date.now();
        }

        const nowMs = (window.performance && performance.now) ? performance.now() : Date.now();
        let capturedAtMs = Math.max(1, Math.round(nowMs - livenessStartedAtMs));

        if (capturedAtMs <= lastLivenessCapturedAtMs) {
            capturedAtMs = lastLivenessCapturedAtMs + 1;
        }

        lastLivenessCapturedAtMs = capturedAtMs;

        const scale = Math.min(1, livenessEvidenceMaxWidth / videoElement.videoWidth);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(videoElement.videoWidth * scale);
        canvas.height = Math.round(videoElement.videoHeight * scale);
        canvas.getContext('2d').drawImage(videoElement, 0, 0, canvas.width, canvas.height);

        const frame = {
            label,
            captured_at_ms: capturedAtMs,
            captured_at_client: new Date().toISOString(),
            image: canvas.toDataURL('image/jpeg', livenessEvidenceJpegQuality),
            ear: extra.ear ?? null,
            yaw: extra.yaw ?? null
        };

        livenessEvidence.frames = (livenessEvidence.frames || []).filter(function(item) {
            return item.label !== label;
        });
        livenessEvidence.frames.push(frame);
        syncLivenessEvidenceInput();
    }

    function hasRequiredLivenessEvidence() {
        const labels = (livenessEvidence.frames || []).map(function(frame) {
            return frame.label;
        });

        return ['center', 'turn_left', 'turn_right'].every(function(label) {
            return labels.includes(label);
        });
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

    async function captureSelfieFromTurn(score, message) {
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
        blinkLivenessMessage = message || 'Gerakan wajah terdeteksi.';

        setBlinkResult(true, score, blinkLivenessMessage);

        if (blinkInterval) {
            clearInterval(blinkInterval);
            blinkInterval = null;
        }

        cameraVerificationLocked = true;
        cameraValidationStartedAt = null;

        updateLiveFrameFeedback(
            'green',
            'Gerakan wajah terdeteksi. Selfie sedang diambil otomatis.',
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

    async function startTurnLiveness(videoElement) {
        if (blinkInterval) {
            clearInterval(blinkInterval);
        }

        turnStage = 'center';
        turnCenterSamples = [];
        turnLeftYaw = null;
        resetLivenessEvidence();

        setBlinkResult(false, null, 'Hadap lurus ke kamera sampai frame tengah tersimpan.');

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
                const turnMetric = calculateHeadTurnMetric(landmarks);

                if (!turnMetric) {
                    setBlinkResult(false, null, 'Gagal membaca posisi wajah. Hadap kamera dengan pencahayaan cukup.');
                    return;
                }

                if (!faceMatchedReady || !latestFaceMatchCapturePayload) {
                    setBlinkResult(
                        false,
                        turnMetric.absYaw,
                        'Tunggu wajah cocok terlebih dahulu. Setelah cocok, ikuti instruks hadap wajah.'
                    );
                    return;
                }

                if (turnStage === 'center') {
                    if (turnMetric.absYaw > TURN_CENTER_MAX_YAW) {
                        turnCenterSamples = [];
                        setBlinkResult(
                            false,
                            turnMetric.absYaw,
                            'Hadap lurus ke kamera untuk menyimpan frame tengah.'
                        );
                        return;
                    }

                    turnCenterSamples.push(turnMetric.yaw);

                    if (turnCenterSamples.length < TURN_CENTER_FRAMES) {
                        setBlinkResult(
                            false,
                            turnMetric.absYaw,
                            'Tahan wajah di tengah...'
                        );
                        return;
                    }

                    captureLivenessEvidenceFrame('center', videoElement, {
                        yaw: turnMetric.yaw
                    });
                    turnStage = 'turn_left';

                    setBlinkResult(
                        false,
                        turnMetric.absYaw,
                        'Frame tengah tersimpan. Hadap wajah ke kiri.'
                    );
                    return;
                }

                if (turnStage === 'turn_left') {
                    if (turnMetric.absYaw < TURN_MIN_YAW) {
                        setBlinkResult(
                            false,
                            turnMetric.absYaw,
                            'Hadap wajah ke kiri sampai gerakan terbaca.'
                        );
                        return;
                    }

                    turnLeftYaw = turnMetric.yaw;
                    captureLivenessEvidenceFrame('turn_left', videoElement, {
                        yaw: turnMetric.yaw
                    });
                    turnStage = 'turn_right';

                    setBlinkResult(
                        false,
                        turnMetric.absYaw,
                        'Frame kiri tersimpan. Sekarang hadap wajah ke kanan.'
                    );
                    return;
                }

                if (turnStage === 'turn_right') {
                    const oppositeDirection = turnLeftYaw === null
                        ? turnMetric.absYaw >= TURN_MIN_YAW
                        : (turnLeftYaw < 0 ? turnMetric.yaw >= TURN_MIN_YAW : turnMetric.yaw <= -TURN_MIN_YAW);

                    if (!oppositeDirection) {
                        setBlinkResult(
                            false,
                            turnMetric.absYaw,
                            'Hadap wajah ke arah berlawanan sampai gerakan terbaca.'
                        );
                        return;
                    }

                    captureLivenessEvidenceFrame('turn_right', videoElement, {
                        yaw: turnMetric.yaw
                    });

                    const score = Math.min(1, Math.max(
                        Math.abs(turnLeftYaw || 0),
                        turnMetric.absYaw
                    ) / TURN_MIN_YAW);

                    await captureSelfieFromTurn(
                        score,
                        'Liveness berhasil. Gerakan wajah kiri dan kanan terdeteksi.'
                    );
                    return;
                }
            } catch (error) {
                setBlinkResult(false, null, 'Gagal membaca gerakan wajah.');
            } finally {
                blinkIsProcessing = false;
            }
        }, TURN_SAMPLE_INTERVAL_MS);
    }

    function analyzeScreenSpoofFromCanvas(canvas) {
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;

        const imageData = ctx.getImageData(0, 0, width, height).data;

        let sampleCount = 0;
        let brightnessSum = 0;
        let brightnessSquareSum = 0;
        let overBrightCount = 0;

        for (let y = 0; y < height; y += 12) {
            for (let x = 0; x < width; x += 12) {
                const index = (y * width + x) * 4;
                const r = imageData[index];
                const g = imageData[index + 1];
                const b = imageData[index + 2];

                const brightness = (0.2126 * r) + (0.7152 * g) + (0.0722 * b);

                brightnessSum += brightness;
                brightnessSquareSum += brightness * brightness;

                if (brightness > 235) {
                    overBrightCount++;
                }

                sampleCount++;
            }
        }

        const mean = brightnessSum / sampleCount;
        const variance = (brightnessSquareSum / sampleCount) - (mean * mean);
        const overBrightRatio = overBrightCount / sampleCount;

        let score = 0;
        let reasons = [];

        if (overBrightRatio > 0.08) {
            score += 35;
            reasons.push('high_screen_glare');
        }

        if (variance < 250) {
            score += 30;
            reasons.push('low_texture_variance');
        }

        if (mean > 190) {
            score += 20;
            reasons.push('too_bright');
        }

        return {
            score,
            reasons,
            mean: Number(mean.toFixed(2)),
            variance: Number(variance.toFixed(2)),
            overBrightRatio: Number(overBrightRatio.toFixed(4))
        };
    }
</script>
@endif

<script>
    $(document).ready(function() {
        $("#table-presensi").DataTable({
            responsive: true,
            ordering: false
        });
    });
</script>
@endpush
