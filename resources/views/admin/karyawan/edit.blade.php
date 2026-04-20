@extends('layouts.app')

@push('styles')
<style>
    .employee-document-card {
        border: 1px solid rgba(15, 23, 42, 0.1);
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .employee-document-card__preview {
        min-height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            linear-gradient(135deg, rgba(14, 116, 144, 0.08), rgba(16, 185, 129, 0.08)),
            #f8fafc;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    }

    .employee-document-card__preview img {
        width: 100%;
        max-height: 220px;
        object-fit: contain;
        background-color: #fff;
    }

    .employee-document-card__placeholder {
        text-align: center;
        color: #64748b;
        padding: 2rem 1rem;
    }

    .employee-document-card__placeholder i {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }

    .employee-document-card__body {
        padding: 1rem;
    }

    .employee-document-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .employee-document-card__meta {
        color: #64748b;
        font-size: 0.85rem;
        word-break: break-word;
    }

    .employee-document-modal__surface {
        min-height: 65vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #0f172a;
    }

    .employee-document-modal__surface img {
        max-width: 100%;
        max-height: 75vh;
        object-fit: contain;
    }

    .employee-document-modal__surface iframe {
        width: 100%;
        height: 75vh;
        border: 0;
        background: #fff;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">Edit Karyawan</h3>
        </div>

        <div class="card">
            <div class="card-body">
                @php
                    $documentFields = [
                        [
                            'label' => 'Foto Karyawan',
                            'input' => 'photo_file',
                            'path' => $employee->photo_path,
                            'accept' => 'image/png,image/jpeg,image/webp',
                            'help' => 'Upload foto profil atau pas foto karyawan.',
                        ],
                        [
                            'label' => 'KTP',
                            'input' => 'ktp_file',
                            'path' => $employee->ktp_path,
                            'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
                            'help' => 'Bisa berupa gambar atau PDF.',
                        ],
                        [
                            'label' => 'KK',
                            'input' => 'kk_file',
                            'path' => $employee->kk_path,
                            'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
                            'help' => 'Bisa berupa gambar atau PDF.',
                        ],
                        [
                            'label' => 'SIM',
                            'input' => 'sim_file',
                            'path' => $employee->sim_path,
                            'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
                            'help' => 'Bisa berupa gambar atau PDF.',
                        ],
                        [
                            'label' => 'SIO',
                            'input' => 'sio_file',
                            'path' => $employee->sio_path,
                            'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
                            'help' => 'Bisa berupa gambar atau PDF.',
                        ],
                    ];
                @endphp
                <form action="{{ route('karyawan.update', $employee->nik) }}" method="POST" enctype="multipart/form-data" data-auto-compress-images="true">
                    @csrf
                    @method('PUT')

                    {{-- DATA UTAMA --}}
                    <h5 class="fw-bold mb-3">Data Utama</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-control" value="{{ $employee->nik }}" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nama Karyawan</label>
                            <input type="text" class="form-control" name="nama_karyawan" value="{{ $employee->nama_karyawan }}" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-select form-control">
                                <option value="">-- Pilih --</option>
                                <option value="L" {{ $employee->jenis_kelamin == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="P" {{ $employee->jenis_kelamin == 'P' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Foto Referensi Wajah</label>
                            <input type="file" class="form-control @error('face_reference') is-invalid @enderror" name="face_reference" accept="image/png,image/jpeg,image/webp" data-compress-images="true">
                            @error('face_reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2">
                                Upload foto wajah lurus ke kamera. Gambar akan dikompres otomatis ke kisaran 1-2MB bila terlalu besar.
                            </small>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label d-block">Preview Referensi</label>
                            @if($employee->face_reference_path)
                            <div class="employee-document-card">
                                <div class="employee-document-card__preview">
                                    <img
                                        src="{{ asset($employee->face_reference_path) }}"
                                        alt="Foto referensi wajah"
                                        class="img-fluid"
                                        loading="lazy">
                                </div>
                                <div class="employee-document-card__body">
                                    <div class="employee-document-card__meta mb-3">
                                        {{ basename($employee->face_reference_path) }}
                                    </div>
                                    <div class="employee-document-card__actions">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-document-preview
                                            data-file-url="{{ asset($employee->face_reference_path) }}"
                                            data-file-type="image"
                                            data-file-label="Foto Referensi Wajah"
                                            data-file-name="{{ basename($employee->face_reference_path) }}">
                                            Perbesar
                                        </button>
                                        <a
                                            href="{{ asset($employee->face_reference_path) }}"
                                            download
                                            class="btn btn-sm btn-primary">
                                            Unduh
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="border rounded p-3 text-muted bg-light small">
                                Belum ada foto referensi wajah.
                            </div>
                            @endif
                        </div>
                    </div>

                    <h5 class="fw-bold mt-4 mb-3">Dokumen Karyawan</h5>
                    <div class="row">
                        @foreach($documentFields as $document)
                            @php
                                $currentPath = $document['path'] ?? null;
                                $currentUrl = $currentPath ? asset($currentPath) : null;
                                $currentFileName = $currentPath ? basename($currentPath) : null;
                                $extension = $currentPath ? strtolower(pathinfo($currentPath, PATHINFO_EXTENSION)) : null;
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
                                $isPdf = $extension === 'pdf';
                            @endphp
                            <div class="col-md-6 mb-4">
                                <label class="form-label">{{ $document['label'] }}</label>
                                <input
                                    type="file"
                                    class="form-control @error($document['input']) is-invalid @enderror"
                                    name="{{ $document['input'] }}"
                                    accept="{{ $document['accept'] }}"
                                    data-compress-images="true">
                                @error($document['input'])
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">{{ $document['help'] }} Gambar akan dikompres otomatis ke kisaran 1-2MB</small>

                                <div class="employee-document-card mt-3">
                                    @if($currentPath)
                                        <div class="employee-document-card__preview">
                                            @if($isImage)
                                                <img
                                                    src="{{ $currentUrl }}"
                                                    alt="{{ $document['label'] }}"
                                                    class="img-fluid"
                                                    loading="lazy">
                                            @elseif($isPdf)
                                                <div class="employee-document-card__placeholder">
                                                    <i class="fas fa-file-pdf text-danger"></i>
                                                    <div class="fw-semibold">PDF {{ $document['label'] }}</div>
                                                    <div class="small mt-1">Buka preview untuk melihat isi file lebih besar.</div>
                                                </div>
                                            @else
                                                <div class="employee-document-card__placeholder">
                                                    <i class="fas fa-file-alt text-primary"></i>
                                                    <div class="fw-semibold">File {{ $document['label'] }}</div>
                                                    <div class="small mt-1">Dokumen siap dibuka atau diunduh.</div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="employee-document-card__body">
                                            <div class="employee-document-card__meta mb-3">
                                                {{ $currentFileName }}
                                            </div>
                                            <div class="employee-document-card__actions">
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-document-preview
                                                    data-file-url="{{ $currentUrl }}"
                                                    data-file-type="{{ $isPdf ? 'pdf' : 'image' }}"
                                                    data-file-label="{{ $document['label'] }}"
                                                    data-file-name="{{ $currentFileName }}">
                                                    {{ $isPdf ? 'Lihat PDF' : 'Perbesar' }}
                                                </button>
                                                <a
                                                    href="{{ $currentUrl }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    Tab Baru
                                                </a>
                                                <a
                                                    href="{{ $currentUrl }}"
                                                    download
                                                    class="btn btn-sm btn-primary">
                                                    Unduh
                                                </a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="employee-document-card__placeholder">
                                            <i class="fas fa-file-upload"></i>
                                            <div class="fw-semibold">Belum ada file</div>
                                            <div class="small mt-1">Belum ada file {{ strtolower($document['label']) }}.</div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- PEKERJAAN --}}
                    <h5 class="fw-bold mt-4 mb-3">Pekerjaan</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Posisi</label>
                            <input type="text" class="form-control" name="posisi" value="{{ $employee->posisi }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jabatan</label>
                            <input type="text" class="form-control" name="jabatan" value="{{ $employee->jabatan }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Kontrak</label>
                            <select name="status_karyawan" class="form-select form-control">
                                <option value="">-- Pilih --</option>
                                <option value="PKWT 合同工" {{ $employee->status_karyawan == 'PKWT 合同工' ? 'selected' : '' }}>PKWT 合同工</option>
                                <option value="PKWTT 固定工" {{ $employee->status_karyawan == 'PKWTT 固定工' ? 'selected' : '' }}>PKWTT 固定工</option>
                            </select>
                        </div>

                        {{-- PERUSAHAAN --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Perusahaan</label>
                            <select name="area_kerja" id="perusahaan_id" class="form-select form-control">
                                <option value="">-- Pilih Perusahaan --</option>
                                @foreach ($areas as $area)
                                <option value="{{ $area->kode_perusahaan }}"
                                    {{ old('perusahaan_id', $employee->area_kerja) == $area->kode_perusahaan ? 'selected' : '' }}>
                                    {{ $area->kode_perusahaan }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- DEPARTEMEN --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Departemen</label>
                            <select name="departemen_id" id="departemen_id" class="form-select form-control">
                                <option value="">-- Pilih Departemen --</option>
                                @foreach ($departemens as $departemen)
                                <option value="{{ $departemen->id }}"
                                    {{ old('departemen_id', $employee->departemen_id) == $departemen->id ? 'selected' : '' }}>
                                    {{ $departemen->nama_departemen }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- DIVISI --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Divisi</label>
                            <select name="divisi_id" id="divisi_id" class="form-select form-control">
                                <option value="">-- Pilih Divisi --</option>
                                @foreach ($divisis as $divisi)
                                <option value="{{ $divisi->id }}"
                                    {{ old('divisi_id', $employee->divisi_id) == $divisi->id ? 'selected' : '' }}>
                                    {{ $divisi->nama_divisi }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- STATUS KARYAWAN --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Karyawan</label>
                            <select name="status_resign" id="status_resign" class="form-select form-control">
                                <option value="">-- Pilih Status --</option>
                                <option value="">Semua Status</option>
                                <option value="AKTIF" {{ old('status_resign', $employee->status_resign) == 'AKTIF' ? 'selected' : '' }}>Aktif</option>
                                <option value="RESIGN SESUAI PROSEDUR" {{ old('status_resign', $employee->status_resign) == 'RESIGN SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Sesuai Prosedur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR" {{ old('status_resign', $employee->status_resign) == 'RESIGN TIDAK SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN" {{ old('status_resign', $employee->status_resign) == 'RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedu - Pengajuan</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-KABUR" {{ old('status_resign', $employee->status_resign) == 'RESIGN TIDAK SESUAI PROSEDUR-KABUR' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedur - Kabur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR-PAYROLL" {{ old('status_resign', $employee->status_resign) == 'RESIGN TIDAK SESUAI PROSEDUR-PAYROLL' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedur - Payroll</option>
                                <option value="PB RESIGN" {{ old('status_resign', $employee->status_resign) == 'PB RESIGN' ? 'selected' : '' }}>PB Resign</option>
                                <option value="PUTUS KONTRAK" {{ old('status_resign', $employee->status_resign) == 'PUTUS KONTRAK' ? 'selected' : '' }}>Putus Kontrak</option>
                                <option value="PHK" {{ old('status_resign', $employee->status_resign) == 'PHK' ? 'selected' : '' }}>PHK</option>
                                <option value="PHK PENSIUN" {{ old('status_resign', $employee->status_resign) == 'PHK PENSIUN' ? 'selected' : '' }}>PHK Pensiun</option>
                                <option value="PHK PENSIUN DINI" {{ old('status_resign', $employee->status_resign) == 'PHK PENSIUN DINI' ? 'selected' : '' }}>PHK Pensiun Dini</option>
                                <option value="PHK PIDANA" {{ old('status_resign', $employee->status_resign) == 'PHK PIDANA' ? 'selected' : '' }}>PHK Pidana</option>
                                <option value="PHK MENINGGAL DUNIA" {{ old('status_resign', $employee->status_resign) == 'PHK MENINGGAL DUNIA' ? 'selected' : '' }}>PHK Meninggal Dunia</option>
                            </select>
                        </div>

                    </div>

                    {{-- DATA PRIBADI --}}
                    <h5 class="fw-bold mt-4 mb-3">Data Pribadi</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" name="tgl_lahir" value="{{ $employee->tgl_lahir->format('Y-m-d') }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Agama</label>
                            <select name="agama" class="form-select form-control">
                                <option value="">-- Pilih Agama --</option>
                                <option value="ISLAM 伊斯兰教" {{ $employee->agama == 'ISLAM 伊斯兰教' ? 'selected' : '' }}>ISLAM 伊斯兰教</option>
                                <option value="KRISTEN PROTESTAN 基督教新教" {{ $employee->agama == 'KRISTEN PROTESTAN 基督教新教' ? 'selected' : '' }}>KRISTEN PROTESTAN 基督教新教</option>
                                <option value="KRISTEN KATHOLIK 天主教徒" {{ $employee->agama == 'KRISTEN KATHOLIK 天主教徒' ? 'selected' : '' }}>KRISTEN KATHOLIK 天主教徒</option>
                                <option value="HINDU 印度教" {{ $employee->agama == 'HINDU 印度教' ? 'selected' : '' }}>HINDU 印度教</option>
                                <option value="BUDHA 佛教" {{ $employee->agama == 'BUDHA 佛教' ? 'selected' : '' }}>BUDHA 佛教</option>
                                <option value="KHONGHUCU 儒教" {{ $employee->agama == 'KHONGHUCU 儒教' ? 'selected' : '' }}>KHONGHUCU 儒教</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Perkawinan</label>
                            <select name="status_perkawinan" id="status_perkawinan" class="form-select form-control">
                                <option value="">Pilih Status</option>
                                <option value="Belum Kawin" {{ $employee->status_perkawinan == 'Belum Kawin' ? 'selected' : '' }}>Belum Kawin</option>
                                <option value="Kawin" {{ $employee->status_perkawinan == 'Kawin' ? 'selected' : '' }}>Kawin</option>
                                <option value="Cerai" {{ $employee->status_perkawinan == 'Cerai' ? 'selected' : '' }}>Cerai</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">No. Telp</label>
                            <input type="text" class="form-control" name="no_telp" value="{{ $employee->no_telp }}">
                        </div>
                    </div>

                    {{-- ALAMAT --}}
                    <h5 class="fw-bold mt-4 mb-3">Alamat</h5>
                    <div class="row">

                        {{-- PROVINSI --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Provinsi</label>
                            <select name="provinsi_id" id="provinsi_id" class="form-select form-control">
                                <option value="">-- Pilih Provinsi --</option>
                            </select>
                        </div>

                        {{-- KABUPATEN --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kabupaten</label>
                            <select name="kabupaten_id" id="kabupaten_id" class="form-select form-control">
                                <option value="">-- Pilih Kabupaten --</option>
                            </select>
                        </div>

                        {{-- KECAMATAN --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kecamatan</label>
                            <select name="kecamatan_id" id="kecamatan_id" class="form-select form-control">
                                <option value="">-- Pilih Kecamatan --</option>
                            </select>
                        </div>

                        {{-- KELURAHAN --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kelurahan</label>
                            <select name="kelurahan_id" id="kelurahan_id" class="form-select form-control">
                                <option value="">-- Pilih Kelurahan --</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat KTP</label>
                            <textarea class="form-control" name="alamat_ktp" rows="3">{{ $employee->alamat_ktp }}</textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea class="form-control" name="alamat_domisili" rows="3">{{ $employee->alamat_domisili }}</textarea>
                        </div>
                    </div>

                    {{-- ADMINISTRASI --}}
                    <h5 class="fw-bold mt-4 mb-3">Administrasi</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">NPWP</label>
                            <input type="text" class="form-control" name="npwp" value="{{ $employee->npwp }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">BPJS Kesehatan</label>
                            <input type="text" class="form-control" name="bpjs_kesehatan"  value="{{ $employee->bpjs_kesehatan }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">BPJS Ketenagakerjaan</label>
                            <input type="text" class="form-control" name="bpjs_tk" value="{{ $employee->bpjs_tk }}">
                        </div>
                    </div>

                    {{-- BUTTON --}}
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="{{ route('karyawan.index') }}" class="btn btn-secondary">
                            Kembali
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="employeeDocumentPreviewModal" tabindex="-1" aria-labelledby="employeeDocumentPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="employeeDocumentPreviewModalLabel">Preview Dokumen</h5>
                    <div class="small text-muted" id="employeeDocumentPreviewFileName"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="employee-document-modal__surface" id="employeeDocumentPreviewImageWrap">
                    <img id="employeeDocumentPreviewImage" src="" alt="Preview dokumen">
                </div>
                <div class="employee-document-modal__surface d-none" id="employeeDocumentPreviewPdfWrap">
                    <iframe id="employeeDocumentPreviewPdf" src="" title="Preview PDF"></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-outline-secondary" id="employeeDocumentPreviewOpen" target="_blank" rel="noopener noreferrer">
                    Buka di Tab Baru
                </a>
                <a href="#" class="btn btn-primary" id="employeeDocumentPreviewDownload" download>
                    Unduh
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const oldPerusahaan = "{{ old('perusahaan_id', $employee->area_kerja) }}";
    const oldDepartemen = "{{ old('departemen_id', $employee->departemen_id) }}";
    const oldDivisi = "{{ old('divisi_id', $employee->divisi_id) }}";

    $(document).ready(function() {

        // ===============================
        // PERUSAHAAN → DEPARTEMEN
        // ===============================
        $('#perusahaan_id').on('change', function() {
            let perusahaan = $(this).val();

            // kalau user pilih ulang → reset
            $('#departemen_id').html('<option value="">Loading...</option>');
            $('#divisi_id').html('<option value="">-- Pilih Divisi --</option>');

            if (!perusahaan) {
                $('#departemen_id').html('<option value="">-- Pilih Departemen --</option>');
                return;
            }

            $.get('/admin/ajax/departemen-by-area', {
                area: perusahaan
            }, function(data) {
                let options = '<option value="">-- Pilih Departemen --</option>';

                data.forEach(item => {
                    options += `<option value="${item.id}">${item.departemen}</option>`;
                });

                $('#departemen_id').html(options);

                /**
                 * AUTO SELECT hanya jika:
                 * - ini pertama kali load (edit mode)
                 * - perusahaan tidak berubah
                 */
                if (oldDepartemen && perusahaan === oldPerusahaan) {
                    $('#departemen_id').val(oldDepartemen).trigger('change');
                }
            });
        });

        // ===============================
        // DEPARTEMEN → DIVISI
        // ===============================
        $('#departemen_id').on('change', function() {
            let departemen = $(this).val();

            $('#divisi_id').html('<option value="">Loading...</option>');

            if (!departemen) {
                $('#divisi_id').html('<option value="">-- Pilih Divisi --</option>');
                return;
            }

            $.get("/admin/ajax/divisi-by-departemen", {
                departemen: departemen
            }, function(data) {
                let options = '<option value="">-- Pilih Divisi --</option>';

                data.forEach(item => {
                    options += `<option value="${item.id}">${item.nama_divisi}</option>`;
                });

                $('#divisi_id').html(options);

                /**
                 * AUTO SELECT hanya jika:
                 * - edit mode
                 * - departemen tidak berubah
                 */
                if (oldDivisi && departemen == oldDepartemen) {
                    $('#divisi_id').val(oldDivisi);
                }
            });
        });

        // ===============================
        // AUTO LOAD SAAT EDIT
        // ===============================
        if (oldPerusahaan) {
            $('#perusahaan_id').trigger('change');
        }

    });
</script>

<script>
    (function() {
        const modalElement = document.getElementById('employeeDocumentPreviewModal');

        if (!modalElement || typeof bootstrap === 'undefined') {
            return;
        }

        const previewModal = new bootstrap.Modal(modalElement);
        const titleElement = document.getElementById('employeeDocumentPreviewModalLabel');
        const fileNameElement = document.getElementById('employeeDocumentPreviewFileName');
        const imageWrap = document.getElementById('employeeDocumentPreviewImageWrap');
        const imageElement = document.getElementById('employeeDocumentPreviewImage');
        const pdfWrap = document.getElementById('employeeDocumentPreviewPdfWrap');
        const pdfElement = document.getElementById('employeeDocumentPreviewPdf');
        const openButton = document.getElementById('employeeDocumentPreviewOpen');
        const downloadButton = document.getElementById('employeeDocumentPreviewDownload');

        function resetPreview() {
            imageWrap.classList.remove('d-none');
            pdfWrap.classList.add('d-none');
            imageElement.removeAttribute('src');
            pdfElement.removeAttribute('src');
        }

        document.querySelectorAll('[data-document-preview]').forEach((button) => {
            button.addEventListener('click', function() {
                const fileUrl = this.dataset.fileUrl;
                const fileType = this.dataset.fileType || 'image';
                const fileLabel = this.dataset.fileLabel || 'Preview Dokumen';
                const fileName = this.dataset.fileName || '';

                titleElement.textContent = fileLabel;
                fileNameElement.textContent = fileName;
                openButton.href = fileUrl;
                downloadButton.href = fileUrl;

                resetPreview();

                if (fileType === 'pdf') {
                    imageWrap.classList.add('d-none');
                    pdfWrap.classList.remove('d-none');
                    pdfElement.src = fileUrl + '#toolbar=1&navpanes=0&view=FitH';
                } else {
                    imageElement.src = fileUrl;
                }

                previewModal.show();
            });
        });

        modalElement.addEventListener('hidden.bs.modal', resetPreview);
    })();
</script>

<script>
    const oldProvinsi = "{{ old('provinsi_id', $employee->provinsi_id) }}";
    const oldKabupaten = "{{ old('kabupaten_id', $employee->kabupaten_id) }}";
    const oldKecamatan = "{{ old('kecamatan_id', $employee->kecamatan_id) }}";
    const oldKelurahan = "{{ old('kelurahan_id', $employee->kelurahan_id) }}";

    $(document).ready(function() {

        // ===============================
        // LOAD PROVINSI
        // ===============================
        $.get("{{ route('wilayah.provinces') }}", function(data) {
            let opt = '<option value="">-- Pilih Provinsi --</option>';
            data.forEach(item => {
                opt += `<option value="${item.id}">${item.provinsi}</option>`;
            });
            $('#provinsi_id').html(opt);

            if (oldProvinsi) {
                $('#provinsi_id').val(oldProvinsi).trigger('change');
            }
        });

        // ===============================
        // PROVINSI → KABUPATEN
        // ===============================
        $('#provinsi_id').on('change', function() {
            let provinsi = $(this).val();

            $('#kabupaten_id').html('<option>Loading...</option>');
            $('#kecamatan_id').html('<option value="">-- Pilih Kecamatan --</option>');
            $('#kelurahan_id').html('<option value="">-- Pilih Kelurahan --</option>');

            if (!provinsi) return;

            $.get(`/wilayah/kabupatens/${provinsi}`, function(data) {
                let opt = '<option value="">-- Pilih Kabupaten --</option>';
                data.forEach(item => {
                    opt += `<option value="${item.id}">${item.kabupaten}</option>`;
                });
                $('#kabupaten_id').html(opt);

                if (oldKabupaten && provinsi == oldProvinsi) {
                    $('#kabupaten_id').val(oldKabupaten).trigger('change');
                }
            });
        });

        // ===============================
        // KABUPATEN → KECAMATAN
        // ===============================
        $('#kabupaten_id').on('change', function() {
            let kabupaten = $(this).val();

            $('#kecamatan_id').html('<option>Loading...</option>');
            $('#kelurahan_id').html('<option value="">-- Pilih Kelurahan --</option>');

            if (!kabupaten) return;

            $.get(`/wilayah/kecamatans/${kabupaten}`, function(data) {
                let opt = '<option value="">-- Pilih Kecamatan --</option>';
                data.forEach(item => {
                    opt += `<option value="${item.id}">${item.kecamatan}</option>`;
                });
                $('#kecamatan_id').html(opt);

                if (oldKecamatan && kabupaten == oldKabupaten) {
                    $('#kecamatan_id').val(oldKecamatan).trigger('change');
                }
            });
        });

        // ===============================
        // KECAMATAN → KELURAHAN
        // ===============================
        $('#kecamatan_id').on('change', function() {
            let kecamatan = $(this).val();

            $('#kelurahan_id').html('<option>Loading...</option>');

            if (!kecamatan) return;

            $.get(`/wilayah/kelurahans/${kecamatan}`, function(data) {
                let opt = '<option value="">-- Pilih Kelurahan --</option>';
                data.forEach(item => {
                    opt += `<option value="${item.id}">${item.kelurahan}</option>`;
                });
                $('#kelurahan_id').html(opt);

                if (oldKelurahan && kecamatan == oldKecamatan) {
                    $('#kelurahan_id').val(oldKelurahan);
                }
            });
        });

    });
</script>
@endpush

@endsection
