@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-karyawan-edit.css') }}">
@endpush

@section('content')
@php
$documentFields = collect([
[
'label' => 'Foto Karyawan',
'type' => 'photo',
'input' => 'photo_file',
'path' => $employee->photo_path,
'accept' => 'image/png,image/jpeg,image/webp',
'help' => 'Upload foto profil atau pas foto karyawan.',
],
[
'label' => 'KTP',
'type' => 'ktp',
'input' => 'ktp_file',
'path' => $employee->ktp_path,
'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
'help' => 'Menerima gambar atau PDF.',
],
[
'label' => 'KK',
'type' => 'kk',
'input' => 'kk_file',
'path' => $employee->kk_path,
'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
'help' => 'Menerima gambar atau PDF.',
],
[
'label' => 'SIM',
'type' => 'sim',
'input' => 'sim_file',
'path' => $employee->sim_path,
'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
'help' => 'Menerima gambar atau PDF.',
],
[
'label' => 'SIO',
'type' => 'sio',
'input' => 'sio_file',
'path' => $employee->sio_path,
'accept' => 'image/png,image/jpeg,image/webp,application/pdf',
'help' => 'Menerima gambar atau PDF.',
],
])->map(function ($document) use ($employee) {
$path = $document['path'] ?? null;
$extension = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : null;
$isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
$isPdf = $extension === 'pdf';

return array_merge($document, [
'url' => $path ? route('karyawan.documents.preview', ['nik' => $employee->nik, 'type' => $document['type']]) : null,
'download_url' => $path ? route('karyawan.documents.download', ['nik' => $employee->nik, 'type' => $document['type']]) : null,
'file_name' => $path ? basename($path) : null,
'is_image' => $isImage,
'is_pdf' => $isPdf,
'badge' => $path ? ($isPdf ? 'PDF' : 'Gambar') : 'Kosong',
]);
});

$uploadedDocumentCount = $documentFields->filter(fn($document) => filled($document['path']))->count() + (filled($employee->face_reference_path) ? 1 : 0);
$statusBadgeClass = $employee->status_resign === 'AKTIF' ? 'success' : 'secondary';
@endphp

<div class="container-fluid employee-edit-shell"
    data-old-perusahaan="{{ old('area_kerja', $employee->area_kerja) }}"
    data-old-departemen="{{ old('departemen_id', $employee->departemen_id) }}"
    data-old-divisi="{{ old('divisi_id', $employee->divisi_id) }}"
    data-old-provinsi="{{ old('provinsi_id', $employee->provinsi_id) }}"
    data-old-kabupaten="{{ old('kabupaten_id', $employee->kabupaten_id) }}"
    data-old-kecamatan="{{ old('kecamatan_id', $employee->kecamatan_id) }}"
    data-old-kelurahan="{{ old('kelurahan_id', $employee->kelurahan_id) }}"
    data-departemen-url="{{ url('/admin/ajax/departemen-by-area') }}"
    data-divisi-url="{{ url('/admin/ajax/divisi-by-departemen') }}"
    data-provinces-url="{{ route('wilayah.provinces') }}"
    data-kabupatens-base-url="{{ url('/wilayah/kabupatens') }}"
    data-kecamatans-base-url="{{ url('/wilayah/kecamatans') }}"
    data-kelurahans-base-url="{{ url('/wilayah/kelurahans') }}">
    <div class="page-inner">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 pt-2 pb-4">
            <div>
                <h3 class="fw-bold mb-1">Edit Karyawan</h3>
                <small class="text-muted">Kelola data inti, alamat dan dokumen karyawan.</small>
            </div>
            <a href="{{ route('karyawan.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left me-1"></i>Kembali
            </a>
        </div>

        <form action="{{ route('karyawan.update', $employee->nik) }}" method="POST" enctype="multipart/form-data" data-auto-compress-images="true">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card border-0">
                        <div class="card-body p-4">
                            <ul class="nav nav-pills employee-edit-tabs" id="employeeEditTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="profil-tab" data-bs-toggle="pill" data-bs-target="#profil-pane" type="button" role="tab" aria-controls="profil-pane" aria-selected="true">
                                        Profil
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="alamat-tab" data-bs-toggle="pill" data-bs-target="#alamat-pane" type="button" role="tab" aria-controls="alamat-pane" aria-selected="false">
                                        Alamat
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="dokumen-tab" data-bs-toggle="pill" data-bs-target="#dokumen-pane" type="button" role="tab" aria-controls="dokumen-pane" aria-selected="false">
                                        Dokumen
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="profil-pane" role="tabpanel" aria-labelledby="profil-tab">
                                    <div class="employee-edit-section">
                                        <div class="employee-edit-section__card">
                                            <div class="employee-edit-section__title">Identitas Karyawan</div>
                                            <div class="employee-edit-section__caption">Informasi personal utama yang dipakai pada profil dan akses akun.</div>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">NIK</label>
                                                    <input type="text" class="form-control" value="{{ $employee->nik }}" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Nama Karyawan</label>
                                                    <input type="text" class="form-control" name="nama_karyawan" value="{{ $employee->nama_karyawan }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Jenis Kelamin</label>
                                                    <select name="jenis_kelamin" class="form-select form-control">
                                                        <option value="">-- Pilih --</option>
                                                        <option value="L" {{ $employee->jenis_kelamin == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                                        <option value="P" {{ $employee->jenis_kelamin == 'P' ? 'selected' : '' }}>Perempuan</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Tanggal Lahir</label>
                                                    <input type="date" class="form-control" name="tgl_lahir" value="{{ optional($employee->tgl_lahir)->format('Y-m-d') }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Agama</label>
                                                    <select name="agama" class="form-select form-control">
                                                        <option value="">-- Pilih Agama --</option>
                                                        <option value="ISLAM 伊斯兰教" {{ $employee->agama == 'ISLAM 伊斯兰教' ? 'selected' : '' }}>ISLAM 伊斯兰教</option>
                                                        <option value="KRISTEN PROTESTAN 基督教新教" {{ $employee->agama == 'KRISTEN PROTESTAN 基督教新教' ? 'selected' : '' }}>KRISTEN PROTESTAN 基督教新教</option>
                                                        <option value="KRISTEN KATHOLIK 天主教徒" {{ $employee->agama == 'KRISTEN KATHOLIK 天主教徒' ? 'selected' : '' }}>KRISTEN KATHOLIK 天主教徒</option>
                                                        <option value="HINDU 印度教" {{ $employee->agama == 'HINDU 印度教' ? 'selected' : '' }}>HINDU 印度教</option>
                                                        <option value="BUDHA 佛教" {{ $employee->agama == 'BUDHA 佛教' ? 'selected' : '' }}>BUDHA 佛教</option>
                                                        <option value="KHONGHUCU 儒家" {{ $employee->agama == 'KHONGHUCU 儒家' ? 'selected' : '' }}>KHONGHUCU 儒家</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Status Perkawinan</label>
                                                    <select name="status_perkawinan" id="status_perkawinan" class="form-select form-control">
                                                        <option value="">Pilih Status</option>
                                                        <option value="Belum Kawin" {{ $employee->status_perkawinan == 'Belum Kawin' ? 'selected' : '' }}>Belum Kawin</option>
                                                        <option value="Kawin" {{ $employee->status_perkawinan == 'Kawin' ? 'selected' : '' }}>Kawin</option>
                                                        <option value="Cerai" {{ $employee->status_perkawinan == 'Cerai' ? 'selected' : '' }}>Cerai</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">No. Telp</label>
                                                    <input type="text" class="form-control" name="no_telp" value="{{ $employee->no_telp }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="employee-edit-section">
                                        <div class="employee-edit-section__card">
                                            <div class="employee-edit-section__title">Pekerjaan dan Organisasi</div>
                                            <div class="employee-edit-section__caption">Data penempatan kerja, struktur organisasi, dan status aktif karyawan.</div>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Posisi</label>
                                                    <input type="text" class="form-control" name="posisi" value="{{ $employee->posisi }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Jabatan</label>
                                                    <input type="text" class="form-control" name="jabatan" value="{{ $employee->jabatan }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Status Kontrak</label>
                                                    <select name="status_karyawan" class="form-select form-control">
                                                        <option value="">-- Pilih --</option>
                                                        <option value="PKWTT 固定工" {{ $employee->status_karyawan == 'PKWTT 固定工' ? 'selected' : '' }}>PKWTT 固定工</option>
                                                        <option value="PKWT 合同工" {{ $employee->status_karyawan == 'PKWT 合同工' ? 'selected' : '' }}>PKWT 合同工</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Perusahaan</label>
                                                    <select name="area_kerja" id="perusahaan_id" class="form-select form-control">
                                                        <option value="">-- Pilih Perusahaan --</option>
                                                        @foreach ($areas as $area)
                                                        <option value="{{ $area->kode_perusahaan }}" {{ old('area_kerja', $employee->area_kerja) == $area->kode_perusahaan ? 'selected' : '' }}>
                                                            {{ $area->kode_perusahaan }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Departemen</label>
                                                    <select name="departemen_id" id="departemen_id" class="form-select form-control">
                                                        <option value="">-- Pilih Departemen --</option>
                                                        @foreach ($departemens as $departemen)
                                                        <option value="{{ $departemen->id }}" {{ old('departemen_id', $employee->departemen_id) == $departemen->id ? 'selected' : '' }}>
                                                            {{ $departemen->nama_departemen }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Divisi</label>
                                                    <select name="divisi_id" id="divisi_id" class="form-select form-control">
                                                        <option value="">-- Pilih Divisi --</option>
                                                        @foreach ($divisis as $divisi)
                                                        <option value="{{ $divisi->id }}" {{ old('divisi_id', $employee->divisi_id) == $divisi->id ? 'selected' : '' }}>
                                                            {{ $divisi->nama_divisi }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Status Karyawan</label>
                                                    <select name="status_resign" id="status_resign" class="form-select form-control">
                                                        <option value="">-- Pilih Status --</option>
                                                        <option value="">Semua Status</option>
                                                        <option value="AKTIF" {{ old('status_resign', $employee->status_resign) == 'AKTIF' ? 'selected' : '' }}>Aktif</option>
                                                        <option value="RESIGN SESUAI PROSEDUR" {{ old('status_resign', $employee->status_resign) == 'RESIGN SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Sesuai Prosedur</option>
                                                        <option value="RESIGN TIDAK SESUAI PROSEDUR" {{ old('status_resign', $employee->status_resign) == 'RESIGN TIDAK SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedur</option>
                                                        <option value="RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN" {{ old('status_resign', $employee->status_resign) == 'RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedur - Pengajuan</option>
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
                                                <div class="col-md-6">
                                                    <label class="form-label">Master Pola Kerja</label>
                                                    <select name="work_pattern_id" class="form-select form-control">
                                                        <option value="">-- Pilih Pola Kerja --</option>
                                                        @foreach ($workPatterns as $workPattern)
                                                            <option value="{{ $workPattern->id }}" {{ (string) old('work_pattern_id', $employee->work_pattern_id) === (string) $workPattern->id ? 'selected' : '' }}>
                                                                {{ $workPattern->code }} - {{ $workPattern->name }} ({{ $workPattern->cycle_summary }} | {{ $workPattern->work_time_range_text }} | Istirahat: {{ $workPattern->break_time_range_text }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('work_pattern_id')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Mulai Berlaku Pola Kerja</label>
                                                    <input type="date" class="form-control" name="work_pattern_start_date" value="{{ old('work_pattern_start_date', optional($employee->work_pattern_start_date)->format('Y-m-d')) }}">
                                                    @error('work_pattern_start_date')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="employee-edit-section">
                                        <div class="employee-edit-section__card">
                                            <div class="employee-edit-section__title">Administrasi</div>
                                            <div class="employee-edit-section__caption">Nomor identitas administrasi dan kepesertaan jaminan sosial.</div>
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">NPWP</label>
                                                    <input type="text" class="form-control" name="npwp" value="{{ $employee->npwp }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">BPJS Kesehatan</label>
                                                    <input type="text" class="form-control" name="bpjs_kesehatan" value="{{ $employee->bpjs_kesehatan }}">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">BPJS Ketenagakerjaan</label>
                                                    <input type="text" class="form-control" name="bpjs_tk" value="{{ $employee->bpjs_tk }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="alamat-pane" role="tabpanel" aria-labelledby="alamat-tab">
                                    <div class="employee-edit-section">
                                        <div class="employee-edit-section__card">
                                            <div class="employee-edit-section__title">Wilayah Administratif</div>
                                            <div class="employee-edit-section__caption">Pilih wilayah bertingkat untuk data alamat KTP dan domisili.</div>
                                            <div class="row g-3">
                                                <div class="col-md-3">
                                                    <label class="form-label">Provinsi</label>
                                                    <select name="provinsi_id" id="provinsi_id" class="form-select form-control">
                                                        <option value="">-- Pilih Provinsi --</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Kabupaten</label>
                                                    <select name="kabupaten_id" id="kabupaten_id" class="form-select form-control">
                                                        <option value="">-- Pilih Kabupaten --</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Kecamatan</label>
                                                    <select name="kecamatan_id" id="kecamatan_id" class="form-select form-control">
                                                        <option value="">-- Pilih Kecamatan --</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Kelurahan</label>
                                                    <select name="kelurahan_id" id="kelurahan_id" class="form-select form-control">
                                                        <option value="">-- Pilih Kelurahan --</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="employee-edit-section">
                                        <div class="employee-edit-section__card">
                                            <div class="employee-edit-section__title">Alamat Lengkap</div>
                                            <div class="employee-edit-section__caption">Simpan alamat KTP dan domisili dengan format yang jelas dan mudah diverifikasi.</div>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Alamat KTP</label>
                                                    <textarea class="form-control" name="alamat_ktp" rows="5">{{ $employee->alamat_ktp }}</textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Alamat Domisili</label>
                                                    <textarea class="form-control" name="alamat_domisili" rows="5">{{ $employee->alamat_domisili }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="dokumen-pane" role="tabpanel" aria-labelledby="dokumen-tab">
                                    <div class="employee-edit-section">
                                        <div class="employee-edit-section__card">
                                            <div class="employee-edit-section__title">Foto Referensi Wajah</div>
                                            <div class="employee-edit-section__caption">Dipakai untuk validasi presensi berbasis foto. Gunakan foto wajah lurus ke kamera.</div>
                                            <div class="employee-document-list">
                                                <div class="employee-document-row">
                                                    <div class="employee-document-row__grid">
                                                        <div class="employee-document-row__preview">
                                                            @if($employee->face_reference_path)
                                                            <img src="{{ route('karyawan.documents.preview', ['nik' => $employee->nik, 'type' => 'face_reference']) }}" alt="Foto referensi wajah" loading="lazy">
                                                            @else
                                                            <div class="employee-document-row__placeholder">
                                                                <i class="fas fa-camera"></i>
                                                                <div>Belum ada file</div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <div class="employee-document-row__header">
                                                                <div>
                                                                    <div class="employee-document-row__title">Foto Referensi Wajah</div>
                                                                    <div class="employee-document-row__meta">
                                                                        {{ $employee->face_reference_path ? basename($employee->face_reference_path) : 'Belum ada foto referensi tersimpan.' }}
                                                                    </div>
                                                                </div>
                                                                <span class="employee-document-card__badge">{{ $employee->face_reference_path ? 'Gambar' : 'Kosong' }}</span>
                                                            </div>
                                                            @if($employee->face_reference_path)
                                                            <div class="employee-document-row__actions">
                                                                <button type="button" class="btn btn-sm btn-outline-primary" data-document-preview data-file-url="{{ route('karyawan.documents.preview', ['nik' => $employee->nik, 'type' => 'face_reference']) }}" data-file-type="image" data-file-label="Foto Referensi Wajah" data-file-name="{{ basename($employee->face_reference_path) }}">
                                                                    Lihat
                                                                </button>
                                                                <a href="{{ route('karyawan.documents.download', ['nik' => $employee->nik, 'type' => 'face_reference']) }}" download class="btn btn-sm btn-primary">
                                                                    Unduh
                                                                </a>
                                                            </div>
                                                            @endif
                                                            <div class="employee-document-row__upload">
                                                                <label class="form-label">Ganti Foto Referensi</label>
                                                                <input type="file" class="form-control @error('face_reference') is-invalid @enderror" name="face_reference" accept="image/png,image/jpeg,image/webp" data-compress-images="true">
                                                                @error('face_reference')
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                                <div class="employee-document-input__help">
                                                                    Upload baru akan menimpa file lama dan memperbarui data karyawan.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="employee-edit-section">
                                        <div class="employee-edit-section__card">
                                            <div class="employee-edit-section__title">Dokumen Legal Karyawan</div>
                                            <div class="employee-edit-section__caption">Setiap upload baru akan menimpa file lama untuk jenis dokumen yang sama, lalu memperbarui path file di database.</div>
                                            <div class="employee-document-list">
                                                @foreach($documentFields as $document)
                                                <div class="employee-document-row">
                                                    <div class="employee-document-row__grid">
                                                        <div class="employee-document-row__preview">
                                                            @if($document['path'] && $document['is_image'])
                                                            <img src="{{ $document['url'] }}" alt="{{ $document['label'] }}" loading="lazy">
                                                            @elseif($document['path'] && $document['is_pdf'])
                                                            <div class="employee-document-row__placeholder">
                                                                <i class="fas fa-file-pdf text-danger"></i>
                                                                <div>PDF</div>
                                                            </div>
                                                            @elseif($document['path'])
                                                            <div class="employee-document-row__placeholder">
                                                                <i class="fas fa-file-alt text-primary"></i>
                                                                <div>File</div>
                                                            </div>
                                                            @else
                                                            <div class="employee-document-row__placeholder">
                                                                <i class="fas fa-file-upload"></i>
                                                                <div>Belum ada file</div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <div class="employee-document-row__header">
                                                                <div>
                                                                    <div class="employee-document-row__title">{{ $document['label'] }}</div>
                                                                    <div class="employee-document-row__meta">
                                                                        {{ $document['path'] ? $document['file_name'] : 'Belum ada file tersimpan.' }}
                                                                    </div>
                                                                </div>
                                                                <span class="employee-document-card__badge">{{ $document['badge'] }}</span>
                                                            </div>
                                                            @if($document['path'])
                                                            <div class="employee-document-row__actions">
                                                                <button type="button" class="btn btn-sm btn-outline-primary" data-document-preview data-file-url="{{ $document['url'] }}" data-file-type="{{ $document['is_pdf'] ? 'pdf' : 'image' }}" data-file-label="{{ $document['label'] }}" data-file-name="{{ $document['file_name'] }}">
                                                                    Lihat
                                                                </button>
                                                                <a href="{{ $document['download_url'] }}" download class="btn btn-sm btn-primary">
                                                                    Unduh
                                                                </a>
                                                            </div>
                                                            @endif
                                                            <div class="employee-document-row__upload">
                                                                <label class="form-label">Upload Ulang {{ $document['label'] }}</label>
                                                                <input
                                                                    type="file"
                                                                    class="form-control @error($document['input']) is-invalid @enderror"
                                                                    name="{{ $document['input'] }}"
                                                                    accept="{{ $document['accept'] }}"
                                                                    data-compress-images="true">
                                                                @error($document['input'])
                                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                                                @enderror
                                                                <div class="employee-document-input__help">
                                                                    {{ $document['help'] }} Upload baru akan menimpa file lama.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="employee-action-card d-flex flex-column gap-4">
                        <div class="card employee-summary-card border-0">
                            <div class="card-body p-4">
                                <div class="employee-summary-card__name">{{ $employee->nama_karyawan }}</div>
                                <div class="employee-summary-card__nik mb-3">{{ $employee->nik }}</div>
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <span class="badge bg-{{ $statusBadgeClass }}">{{ $employee->status_resign ?: 'Belum diatur' }}</span>
                                    @if($employee->area_kerja)
                                    <span class="badge bg-light text-dark border">{{ $employee->area_kerja }}</span>
                                    @endif
                                </div>

                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <div class="employee-summary-card__stat">
                                            <div class="employee-summary-card__stat-label">Departemen</div>
                                            <div class="employee-summary-card__stat-value">{{ optional($employee->departemen)->departemen ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="employee-summary-card__stat">
                                            <div class="employee-summary-card__stat-label">Divisi</div>
                                            <div class="employee-summary-card__stat-value">{{ optional($employee->divisi)->nama_divisi ?? '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="employee-summary-card__stat">
                                            <div class="employee-summary-card__stat-label">Posisi</div>
                                            <div class="employee-summary-card__stat-value">{{ $employee->posisi ?: '-' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="employee-summary-card__stat">
                                            <div class="employee-summary-card__stat-label">Dokumen</div>
                                            <div class="employee-summary-card__stat-value">{{ $uploadedDocumentCount }}/6 tersedia</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="employee-summary-card__stat">
                                            <div class="employee-summary-card__stat-label">Pola Kerja</div>
                                            <div class="employee-summary-card__stat-value">{{ optional($employee->workPattern)->code ?? '-' }}</div>
                                            <div class="employee-summary-card__stat-label mt-1">{{ optional($employee->workPattern)->work_time_range_text ?? 'Belum diatur' }}</div>
                                            <div class="employee-summary-card__stat-label">Istirahat {{ optional($employee->workPattern)->break_time_range_text ?? 'Tidak diatur' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0">
                            <div class="card-body p-4">
                                <div class="employee-edit-section__title">Aksi</div>
                                <div class="employee-edit-section__caption">Simpan perubahan setelah seluruh data dan dokumen diperiksa kembali.</div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                    </button>
                                    <a href="{{ route('karyawan.index') }}" class="btn btn-outline-secondary">
                                        Batal
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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
<script src="{{ versioned_asset('assets/js/admin-karyawan-edit.js') }}"></script>
@endpush

@endsection
