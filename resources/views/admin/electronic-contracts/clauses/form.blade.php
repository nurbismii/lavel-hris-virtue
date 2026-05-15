@extends('layouts.app')

@section('title', $clause->exists ? 'Edit Klausul Adendum' : 'Tambah Klausul Adendum')

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
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">{{ $clause->exists ? 'Edit Klausul Adendum' : 'Tambah Klausul Adendum' }}</h4>
                <small class="text-muted">Klausul bisa memakai placeholder nomor PKWT, nomor adendum, dan tanggal kontrak.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('electronic-contracts.clauses.index') }}" class="btn btn-light">Kembali</a>
            </div>
        </div>

        <form action="{{ $action }}" method="POST">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Jenis Klausul</label>
                                    <select name="clause_key" class="form-select @error('clause_key') is-invalid @enderror">
                                        <option value="">-- Pilih Klausul --</option>
                                        @foreach($keyOptions as $value => $label)
                                            <option value="{{ $value }}" {{ old('clause_key', $clause->clause_key) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('clause_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Nama Klausul</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $clause->name) }}" placeholder="Contoh: Klausul perpanjangan pertama">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label d-block">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', $clause->is_active ?? true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="isActive">Aktif</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Isi Klausul</label>
                                    <textarea name="body_html" rows="16" class="form-control js-contract-editor @error('body_html') is-invalid @enderror">{{ old('body_html', $clause->body_html) }}</textarea>
                                    @error('body_html')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-save me-1"></i> Simpan Klausul
                        </button>
                        <a href="{{ route('electronic-contracts.clauses.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="mb-2">Placeholder</h5>
                            <p class="text-muted small mb-3">Klik chip untuk memasukkan placeholder.</p>
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
                                Jika ingin mengikuti formula Excel, gunakan placeholder <code>{{ '{' . '{' . 'klausul_formula' . '}' . '}' }}</code>.
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
            plugins: 'lists link table code',
            toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | table link | code',
            setup: function (editor) {
                editor.on('focus', function () {
                    activeEditor = editor;
                });
            }
        });

        document.querySelectorAll('.js-insert-variable').forEach(function (button) {
            button.addEventListener('click', function () {
                if (activeEditor) {
                    activeEditor.insertContent(button.dataset.variable);
                    activeEditor.focus();
                }
            });
        });
    })();
</script>
@endpush
