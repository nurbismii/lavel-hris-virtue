@extends('layouts.app')

@section('title', 'Tanda Tangan Kontrak')

@push('styles')
<style>
    .secure-contract-viewer {
        background: #f3f4f6;
        border-radius: 10px;
        padding: 14px;
    }

    .secure-contract-page {
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        margin: 0 auto;
        max-width: 850px;
        min-height: 900px;
        padding: 38px;
        position: relative;
    }

    .secure-contract-page::before {
        color: rgba(15, 23, 42, 0.06);
        content: "{{ $contract->nik }} - {{ now()->format('Y-m-d H:i') }}";
        font-size: 38px;
        font-weight: 700;
        left: 50%;
        pointer-events: none;
        position: absolute;
        top: 50%;
        transform: translate(-50%, -50%) rotate(-28deg);
        white-space: nowrap;
        z-index: 0;
    }

    .secure-contract-content {
        position: relative;
        z-index: 1;
    }

    .secure-contract-content table {
        border-collapse: collapse;
        width: 100%;
    }

    .secure-contract-content td,
    .secure-contract-content th {
        border: 1px solid #d1d5db;
        padding: 6px;
    }

    .secure-contract-content .contract-signature-slot {
        display: block;
        height: 86px;
        line-height: normal;
        margin: 4px 0;
        text-align: center;
    }

    .secure-contract-content .contract-signature-box {
        border: 0 !important;
        border-collapse: collapse;
        height: 86px;
        margin: 0;
        width: 100%;
    }

    .secure-contract-content .contract-signature-box td {
        border: 0 !important;
        height: 86px;
        padding: 0 !important;
        text-align: center;
        vertical-align: middle;
    }

    .secure-contract-content .contract-signature-image {
        height: 76px;
        max-width: 220px;
        vertical-align: middle;
    }

    .signature-pad {
        background: #fff;
        border: 1px dashed #94a3b8;
        border-radius: 8px;
        height: 220px;
        width: 100%;
    }

    .contract-consent-check {
        align-items: flex-start;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        min-width: 0;
        padding-left: 0;
    }

    .contract-consent-check .form-check-input {
        flex: 0 0 auto;
        float: none;
        margin-left: 0;
        margin-top: 3px;
    }

    .contract-consent-check .form-check-label {
        display: block;
        flex: 1 1 auto;
        line-height: 1.45;
        min-width: 0;
        overflow-wrap: anywhere;
        white-space: normal;
        word-break: normal;
    }

    .contract-consent-check .invalid-feedback {
        flex-basis: 100%;
        margin-left: 28px;
    }

    @media (max-width: 576px) {
        .secure-contract-viewer {
            margin-left: -6px;
            margin-right: -6px;
            overflow-x: auto;
            padding: 8px;
        }

        .secure-contract-page {
            min-width: 0;
            padding: 16px;
            width: 100%;
        }

        .secure-contract-page::before {
            font-size: 22px;
        }

        .contract-signature-card .card-body {
            padding: 16px;
        }

        .signature-pad {
            height: 180px;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Kontrak Elektronik</h4>
                <small class="text-muted">{{ $contract->display_number }}</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('user-electronic-contracts.index') }}" class="btn btn-light">Kembali</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="secure-contract-viewer">
                    <div class="secure-contract-page">
                        <div class="secure-contract-content">
                            {!! $html !!}

                            @if($contract->signature)
                            <hr>
                            <div class="alert alert-success mb-0">
                                Kontrak ini telah ditandatangani pada {{ optional($contract->signature->signed_at)->format('d M Y H:i') }}.
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h5 class="mb-3">Ringkasan</h5>
                        <dl class="row mb-0">
                            <dt class="col-5">Status</dt>
                            <dd class="col-7">{{ $contract->status_label }}</dd>
                            <dt class="col-5">Tipe</dt>
                            <dd class="col-7">{{ $contract->type_label }}</dd>
                            <dt class="col-5">NIK</dt>
                            <dd class="col-7">{{ $contract->nik }}</dd>
                            <dt class="col-5">No PKWT</dt>
                            <dd class="col-7">{{ $contract->pkwt_number }}</dd>
                            <dt class="col-5">No Adendum</dt>
                            <dd class="col-7">{{ $contract->addendum_number ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>

                @if($contract->isReadyForSignature())
                <div class="card border-0 shadow-sm contract-signature-card">
                    <div class="card-body">
                        <h5 class="mb-2">Tanda Tangan</h5>
                        <p class="small text-muted">Gunakan jari atau mouse di area tanda tangan. Tanda tangan ini hanya berlaku untuk kontrak ini.</p>

                        <form action="{{ route('user-electronic-contracts.sign', $contract) }}" method="POST" id="signatureForm">
                            @csrf
                            <canvas id="signaturePad" class="signature-pad"></canvas>
                            <input type="hidden" name="signature_data" id="signatureData">
                            @error('signature_data')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearSignature">
                                    Bersihkan
                                </button>
                            </div>

                            <div class="form-check mt-3 contract-consent-check">
                                <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" value="1" id="consent">
                                <label class="form-check-label small" for="consent">
                                    {{ $consentText }}
                                </label>
                                @error('consent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-3">
                                Tandatangani Kontrak
                            </button>
                        </form>
                    </div>
                </div>
                @elseif($contract->signature)
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-2">Bukti Tanda Tangan</h5>
                        <dl class="row mb-0">
                            <dt class="col-5">Waktu</dt>
                            <dd class="col-7">{{ optional($contract->signature->signed_at)->format('d M Y H:i') }}</dd>
                            <dt class="col-5">IP</dt>
                            <dd class="col-7">{{ $contract->signature->ip_address ?: '-' }}</dd>
                            <dt class="col-5">Hash</dt>
                            <dd class="col-7 small text-break">{{ $contract->pdf_hash ?: '-' }}</dd>
                        </dl>
                    </div>
                </div>
                @else
                <div class="alert alert-secondary">
                    Kontrak ini tidak tersedia untuk tanda tangan.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function() {
        const canvas = document.getElementById('signaturePad');
        const form = document.getElementById('signatureForm');

        if (!canvas || !form) {
            return;
        }

        const context = canvas.getContext('2d');
        let drawing = false;
        let hasStroke = false;

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();

            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            context.setTransform(1, 0, 0, 1, 0, 0);
            context.scale(ratio, ratio);
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#111827';
        }

        function point(event) {
            const rect = canvas.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;

            return {
                x: source.clientX - rect.left,
                y: source.clientY - rect.top
            };
        }

        function start(event) {
            drawing = true;
            const p = point(event);
            context.beginPath();
            context.moveTo(p.x, p.y);
            event.preventDefault();
        }

        function move(event) {
            if (!drawing) {
                return;
            }

            const p = point(event);
            context.lineTo(p.x, p.y);
            context.stroke();
            hasStroke = true;
            event.preventDefault();
        }

        function end(event) {
            drawing = false;
            event.preventDefault();
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', start, {
            passive: false
        });
        canvas.addEventListener('touchmove', move, {
            passive: false
        });
        canvas.addEventListener('touchend', end, {
            passive: false
        });

        document.getElementById('clearSignature').addEventListener('click', function() {
            context.clearRect(0, 0, canvas.width, canvas.height);
            hasStroke = false;
        });

        form.addEventListener('submit', function(event) {
            if (!hasStroke) {
                event.preventDefault();
                window.AppDialog.alert(
                    'Tanda tangan belum diisi',
                    'Tanda tangan wajib diisi sebelum kontrak dikirim.',
                    'warning'
                );
                return;
            }

            document.getElementById('signatureData').value = canvas.toDataURL('image/png');
        });
    })();
</script>
@endpush
