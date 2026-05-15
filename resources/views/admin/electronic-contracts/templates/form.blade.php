@extends('layouts.app')

@section('title', $template->exists ? 'Edit Template Kontrak' : 'Tambah Template Kontrak')

@push('styles')
<style>
    .variable-chip {
        border: 1px solid #d9e2ef;
        background: #f8fafc;
        border-radius: 999px;
        color: #334155;
        font-size: 12px;
        padding: 6px 10px;
    }

    .editor-help {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">{{ $template->exists ? 'Edit Template Kontrak' : 'Tambah Template Kontrak' }}</h4>
                <small class="text-muted">Gunakan placeholder agar data karyawan dan kontrak otomatis terisi saat generate.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('electronic-contracts.templates.index') }}" class="btn btn-light">Kembali</a>
            </div>
        </div>

        <form action="{{ $action }}" method="POST">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Tipe Kontrak</label>
                                    <select name="contract_type" class="form-select @error('contract_type') is-invalid @enderror">
                                        <option value="">-- Pilih Tipe --</option>
                                        @foreach($typeOptions as $value => $label)
                                            <option value="{{ $value }}" {{ old('contract_type', $template->contract_type) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('contract_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Nama Template</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $template->name) }}" placeholder="Contoh: PKWT 1 Bahasa Indonesia">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label d-block">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="isActive">Aktif</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">KOP / Header Kontrak</label>
                                    <textarea name="letterhead_html" rows="6" class="form-control js-contract-editor @error('letterhead_html') is-invalid @enderror">{{ old('letterhead_html', $template->letterhead_html) }}</textarea>
                                    @error('letterhead_html')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Isi Kontrak</label>
                                    <textarea name="body_html" rows="20" class="form-control js-contract-editor @error('body_html') is-invalid @enderror">{{ old('body_html', $template->body_html) }}</textarea>
                                    @error('body_html')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save me-1"></i> Simpan Template
                        </button>
                        <a href="{{ route('electronic-contracts.templates.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm editor-help">
                        <div class="card-body">
                            <h5 class="mb-2">Placeholder</h5>
                            <p class="text-muted small mb-3">Klik chip untuk memasukkan placeholder ke editor yang sedang aktif.</p>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($variables as $key => $label)
                                    @php($placeholder = '{' . '{' . $key . '}' . '}')
                                    <button type="button" class="variable-chip js-insert-variable" data-variable="{{ $placeholder }}" title="{{ $label }}">
                                        {{ $placeholder }}
                                    </button>
                                @endforeach
                            </div>
                            <hr>
                            <div class="small text-muted">
                                Untuk template adendum, letakkan <code>{{ '{' . '{' . 'klausul' . '}' . '}' }}</code> pada posisi klausul. Jika tidak ada, sistem menambahkan klausul di akhir isi kontrak.
                                Letakkan <code>{{ '{' . '{' . 'tanda_tangan_pihak_kedua' . '}' . '}' }}</code> tepat di atas nama penanda tangan agar gambar tanda tangan masuk ke posisi tersebut setelah kontrak disetujui.
                                Untuk tanda tangan perusahaan, letakkan <code>{{ '{' . '{' . 'tanda_tangan_pihak_pertama' . '}' . '}' }}</code> tepat di atas nama Pihak Pertama.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
    (function () {
        let activeEditor = null;

        tinymce.init({
            selector: '.js-contract-editor',
            height: 420,
            menubar: false,
            branding: false,
            promotion: false,
            convert_urls: false,
            paste_data_images: false,
            plugins: 'lists link image table code',
            toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | table image link | code',
            images_upload_handler: function (blobInfo, progress) {
                return new Promise(function (resolve, reject) {
                    const xhr = new XMLHttpRequest();
                    const formData = new FormData();

                    xhr.open('POST', '{{ route('electronic-contracts.assets.store') }}');
                    xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
                    xhr.upload.onprogress = function (event) {
                        if (event.lengthComputable) {
                            progress(event.loaded / event.total * 100);
                        }
                    };
                    xhr.onload = function () {
                        if (xhr.status < 200 || xhr.status >= 300) {
                            reject('Upload gambar gagal. Periksa format dan ukuran file.');
                            return;
                        }

                        const json = JSON.parse(xhr.responseText);
                        resolve(json.location);
                    };
                    xhr.onerror = function () {
                        reject('Upload gambar gagal karena koneksi terputus.');
                    };

                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    @if($template->exists)
                        formData.append('contract_template_id', '{{ $template->id }}');
                    @endif
                    xhr.send(formData);
                });
            },
            setup: function (editor) {
                editor.on('focus', function () {
                    activeEditor = editor;
                });
            }
        });

        document.querySelectorAll('.js-insert-variable').forEach(function (button) {
            button.addEventListener('click', function () {
                const variable = button.dataset.variable;

                if (activeEditor) {
                    activeEditor.insertContent(variable);
                    activeEditor.focus();
                }
            });
        });
    })();
</script>
@endpush
