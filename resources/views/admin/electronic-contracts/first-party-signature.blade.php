@extends('layouts.app')

@section('title', 'Tanda Tangan Pihak Pertama')

@push('styles')
<style>
    .first-party-signature-pad {
        background: #fff;
        border: 1px dashed #94a3b8;
        border-radius: 8px;
        height: 220px;
        width: 100%;
    }

    .first-party-signature-preview {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
    }

    .first-party-signature-preview img {
        max-height: 110px;
        max-width: 100%;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Tanda Tangan Pihak Pertama</h4>
                <small class="text-muted">Input sekali untuk dipakai otomatis pada semua kontrak PKWT, Translator, dan Adendum.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('electronic-contracts.index') }}" class="btn btn-light">Kembali</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">Tanda Tangan Aktif</h5>

                        @if($signaturePreview)
                            <div class="first-party-signature-preview mb-3">
                                <img src="{{ $signaturePreview }}" alt="Tanda tangan Pihak Pertama">
                            </div>
                            <dl class="row mb-0">
                                <dt class="col-5">Nama</dt>
                                <dd class="col-7">{{ $signature->signer_name }}</dd>
                                <dt class="col-5">Jabatan</dt>
                                <dd class="col-7">{{ $signature->signer_position ?: '-' }}</dd>
                                <dt class="col-5">Sumber</dt>
                                <dd class="col-7">{{ $signature->signature_source === 'uploaded' ? 'Upload gambar' : 'Buat langsung' }}</dd>
                                <dt class="col-5">Diperbarui</dt>
                                <dd class="col-7">{{ optional($signature->signed_at)->format('d M Y H:i') ?: '-' }}</dd>
                            </dl>
                        @else
                            <div class="alert alert-warning mb-0">
                                Belum ada tanda tangan Pihak Pertama. Karyawan tetap bisa melihat kontrak, tetapi slot Pihak Pertama akan kosong sampai tanda tangan disimpan.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-2">Input Tanda Tangan</h5>
                        <p class="small text-muted mb-3">
                            Tanda tangan ini akan otomatis muncul di slot <code>{{ '{' . '{' . 'tanda_tangan_pihak_pertama' . '}' . '}' }}</code> untuk semua kontrak yang memakai template default.
                        </p>

                        @php($signatureMode = old('signature_mode', 'draw'))
                        <form action="{{ route('electronic-contracts.first-party-signature.store') }}" method="POST" enctype="multipart/form-data" id="firstPartySignatureForm">
                            @csrf

                            <div class="d-flex flex-wrap gap-3 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input js-first-party-mode" type="radio" name="signature_mode" id="firstPartyModeDraw" value="draw" {{ $signatureMode === 'draw' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="firstPartyModeDraw">Buat langsung</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input js-first-party-mode" type="radio" name="signature_mode" id="firstPartyModeUpload" value="upload" {{ $signatureMode === 'upload' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="firstPartyModeUpload">Upload foto</label>
                                </div>
                            </div>
                            @error('signature_mode')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                            <div id="firstPartyDrawPanel">
                                <canvas id="firstPartySignaturePad" class="first-party-signature-pad"></canvas>
                                <input type="hidden" name="signature_data" id="firstPartySignatureData">
                                @error('signature_data')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="clearFirstPartySignature">
                                    Bersihkan
                                </button>
                            </div>

                            <div id="firstPartyUploadPanel">
                                <input type="file" name="signature_file" class="form-control @error('signature_file') is-invalid @enderror" accept="image/png,image/jpeg">
                                @error('signature_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Gunakan JPG/PNG maksimal 2 MB. Background putih atau transparan lebih rapi untuk PDF.</div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3">
                                Simpan Tanda Tangan Master
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('firstPartySignatureForm');
        const canvas = document.getElementById('firstPartySignaturePad');

        if (!form || !canvas) {
            return;
        }

        const context = canvas.getContext('2d');
        const drawPanel = document.getElementById('firstPartyDrawPanel');
        const uploadPanel = document.getElementById('firstPartyUploadPanel');
        const signatureData = document.getElementById('firstPartySignatureData');
        let drawing = false;
        let hasStroke = false;

        function selectedMode() {
            const checked = document.querySelector('.js-first-party-mode:checked');
            return checked ? checked.value : 'draw';
        }

        function toggleMode() {
            const mode = selectedMode();
            drawPanel.style.display = mode === 'draw' ? 'block' : 'none';
            uploadPanel.style.display = mode === 'upload' ? 'block' : 'none';

            if (mode === 'draw') {
                resizeCanvas();
            }
        }

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();

            if (!rect.width || !rect.height) {
                return;
            }

            canvas.width = rect.width * ratio;
            canvas.height = rect.height * ratio;
            context.setTransform(1, 0, 0, 1, 0, 0);
            context.scale(ratio, ratio);
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#111827';
            hasStroke = false;
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
            if (selectedMode() !== 'draw') {
                return;
            }

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

        document.querySelectorAll('.js-first-party-mode').forEach(function (radio) {
            radio.addEventListener('change', toggleMode);
        });

        window.addEventListener('resize', resizeCanvas);
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end, { passive: false });

        document.getElementById('clearFirstPartySignature').addEventListener('click', function () {
            context.clearRect(0, 0, canvas.width, canvas.height);
            hasStroke = false;
            signatureData.value = '';
        });

        form.addEventListener('submit', function (event) {
            if (selectedMode() !== 'draw') {
                signatureData.value = '';
                return;
            }

            if (!hasStroke) {
                event.preventDefault();
                window.AppDialog.alert(
                    'Tanda tangan belum diisi',
                    'Tanda tangan Pihak Pertama wajib diisi sebelum disimpan.',
                    'warning'
                );
                return;
            }

            signatureData.value = canvas.toDataURL('image/png');
        });

        toggleMode();
    })();
</script>
@endpush
