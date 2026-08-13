@extends('layouts.app')

@section('title', 'Master Struktur Organisasi')

@push('styles')
<style>
    .organization-nav { position: sticky; top: 84px; z-index: 10; }
    .organization-section { scroll-margin-top: 110px; }
    .organization-stat { border-left: 4px solid #1572e8; }
    .organization-table td { vertical-align: middle; }
    .organization-form-card { border: 1px solid #e5e7eb; box-shadow: 0 8px 24px rgba(15, 23, 42, .05); }
    .organization-help { font-size: .82rem; color: #64748b; }
    .employee-search-results { max-height: 240px; overflow-y: auto; }
</style>
@endpush

@section('content')
@php
    $editLevel = $editLevel ?? null;
    $editJobTitle = $editJobTitle ?? null;
    $editPosition = $editPosition ?? null;
    $selectedPositionCompany = old('perusahaan_id', optional($editPosition)->perusahaan_id);
    $selectedPositionDepartment = old('departemen_id', optional($editPosition)->departemen_id ?: $selectedDepartmentId);
    $selectedPositionDivision = old('divisi_id', optional($editPosition)->divisi_id);
@endphp
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 pt-2 pb-4">
            <div>
                <h3 class="fw-bold mb-1">Master Struktur Organisasi</h3>
                <p class="text-muted mb-0">Kelola level, jabatan, posisi struktural, hubungan atasan, dan penempatan karyawan.</p>
            </div>
            <a href="{{ route('organization-structure.chart', ['departemen_id' => $selectedDepartmentId]) }}" class="btn btn-primary">
                <i class="fas fa-sitemap me-1"></i> Lihat Bagan Organisasi
            </a>
        </div>

        <div class="alert alert-info border-0">
            <strong>Prinsip data:</strong> level melekat pada master jabatan/posisi, bukan disalin ke setiap karyawan.
            Perubahan level akan tercermin pada struktur tanpa mengubah ribuan baris karyawan.
        </div>

        <div class="card organization-nav mb-4">
            <div class="card-body py-2 d-flex flex-wrap gap-2">
                <a href="#levels" class="btn btn-sm btn-outline-primary">1. Level</a>
                <a href="#job-titles" class="btn btn-sm btn-outline-primary">2. Jabatan</a>
                <a href="#positions" class="btn btn-sm btn-outline-primary">3. Posisi</a>
                <a href="#assignments" class="btn btn-sm btn-outline-primary">4. Penempatan Karyawan</a>
            </div>
        </div>

        <section id="levels" class="organization-section mb-5">
            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card organization-form-card h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-1">{{ $editLevel ? 'Edit Level' : 'Tambah Level' }}</h5>
                            <small class="text-muted">Rank lebih besar berarti level lebih senior.</small>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ $editLevel ? route('organization-structure.levels.update', $editLevel) : route('organization-structure.levels.store') }}" data-loading-form>
                                @csrf
                                @if($editLevel) @method('PUT') @endif
                                <div class="mb-3">
                                    <label class="form-label">Kode</label>
                                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', optional($editLevel)->code) }}" placeholder="L8" required>
                                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nama Level</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', optional($editLevel)->name) }}" placeholder="Level 8" required>
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">Rank</label>
                                        <input type="number" name="rank" min="1" max="999" class="form-control @error('rank') is-invalid @enderror" value="{{ old('rank', optional($editLevel)->rank) }}" required>
                                        @error('rank')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Urutan</label>
                                        <input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', optional($editLevel)->sort_order ?: 0) }}">
                                    </div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <label class="form-label">Keterangan</label>
                                    <textarea name="description" rows="3" class="form-control">{{ old('description', optional($editLevel)->description) }}</textarea>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="levelActive" {{ old('is_active', $editLevel ? $editLevel->is_active : true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="levelActive">Aktif</label>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary" data-loading-text="Menyimpan...">{{ $editLevel ? 'Simpan Perubahan' : 'Tambah Level' }}</button>
                                    @if($editLevel)<a href="{{ route('organization-structure.index', ['section' => 'levels']) }}" class="btn btn-light">Batal</a>@endif
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card h-100">
                        <div class="card-header bg-white"><h5 class="mb-0">Daftar Level</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table organization-table mb-0">
                                    <thead class="table-light"><tr><th>Rank</th><th>Kode</th><th>Nama</th><th>Dampak</th><th>Status</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        @foreach($levels as $level)
                                        <tr>
                                            <td><span class="badge bg-primary fs-6">{{ $level->rank }}</span></td>
                                            <td>{{ $level->code }}</td>
                                            <td><strong>{{ $level->name }}</strong><small class="d-block text-muted">{{ $level->description }}</small></td>
                                            <td>{{ $level->job_titles_count }} jabatan / {{ $level->organization_positions_count }} override posisi</td>
                                            <td><span class="badge bg-{{ $level->is_active ? 'success' : 'secondary' }}">{{ $level->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                            <td><a href="{{ route('organization-structure.index', ['edit_level' => $level->id, 'section' => 'levels']) }}#levels" class="btn btn-sm btn-warning">Edit</a></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="job-titles" class="organization-section mb-5">
            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card organization-form-card">
                        <div class="card-header bg-white">
                            <h5 class="mb-1">{{ $editJobTitle ? 'Edit Jabatan' : 'Tambah Jabatan' }}</h5>
                            <small class="text-muted">Alias membantu memetakan variasi nama jabatan lama.</small>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ $editJobTitle ? route('organization-structure.job-titles.update', $editJobTitle) : route('organization-structure.job-titles.store') }}" data-loading-form>
                                @csrf
                                @if($editJobTitle) @method('PUT') @endif
                                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', optional($editJobTitle)->code) }}" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="mb-3"><label class="form-label">Nama Indonesia</label><input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', optional($editJobTitle)->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="mb-3"><label class="form-label">Nama Mandarin</label><input type="text" name="name_zh" class="form-control" value="{{ old('name_zh', optional($editJobTitle)->name_zh) }}"></div>
                                <div class="mb-3">
                                    <label class="form-label">Level Default</label>
                                    <select name="job_level_id" class="form-select @error('job_level_id') is-invalid @enderror" required>
                                        <option value="">Pilih level</option>
                                        @foreach($activeLevels as $level)<option value="{{ $level->id }}" {{ (string) old('job_level_id', optional($editJobTitle)->job_level_id) === (string) $level->id ? 'selected' : '' }}>{{ $level->code }} - {{ $level->name }} (rank {{ $level->rank }})</option>@endforeach
                                    </select>
                                    @error('job_level_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Alias — satu per baris</label>
                                    <textarea name="aliases" rows="5" class="form-control @error('aliases') is-invalid @enderror" placeholder="SUPERVISOR PRODUKSI&#10;SUPERVISOR 调度">{{ old('aliases', $editJobTitle ? $editJobTitle->aliases->pluck('alias')->implode("\n") : '') }}</textarea>
                                    @error('aliases')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3"><label class="form-label">Keterangan</label><textarea name="description" rows="2" class="form-control">{{ old('description', optional($editJobTitle)->description) }}</textarea></div>
                                <div class="form-check form-switch mb-3"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="jobTitleActive" {{ old('is_active', $editJobTitle ? $editJobTitle->is_active : true) ? 'checked' : '' }}><label class="form-check-label" for="jobTitleActive">Aktif</label></div>
                                <div class="d-flex gap-2"><button class="btn btn-primary" data-loading-text="Menyimpan...">{{ $editJobTitle ? 'Simpan Perubahan' : 'Tambah Jabatan' }}</button>@if($editJobTitle)<a href="{{ route('organization-structure.index', ['section' => 'job-titles']) }}#job-titles" class="btn btn-light">Batal</a>@endif</div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <form method="GET" action="{{ route('organization-structure.index') }}" class="row g-2 align-items-center">
                                <div class="col"><h5 class="mb-0">Master Jabatan</h5></div>
                                <div class="col-md-6"><input type="search" name="job_title_search" class="form-control" value="{{ request('job_title_search') }}" placeholder="Cari kode atau nama jabatan..."></div>
                                <div class="col-auto"><button class="btn btn-outline-primary">Cari</button></div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table organization-table mb-0">
                                    <thead class="table-light"><tr><th>Jabatan</th><th>Level</th><th>Alias</th><th>Dipakai</th><th>Status</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        @forelse($jobTitles as $jobTitle)
                                        <tr>
                                            <td><strong>{{ $jobTitle->name }}</strong>@if($jobTitle->name_zh)<span class="d-block">{{ $jobTitle->name_zh }}</span>@endif<small class="text-muted">{{ $jobTitle->code }}</small></td>
                                            <td><span class="badge bg-primary">{{ optional($jobTitle->level)->code }}</span> rank {{ optional($jobTitle->level)->rank }}</td>
                                            <td><span title="{{ $jobTitle->aliases->pluck('alias')->implode(' | ') }}">{{ $jobTitle->aliases->count() }} alias</span></td>
                                            <td>{{ $jobTitle->employees_count }} karyawan / {{ $jobTitle->organization_positions_count }} posisi</td>
                                            <td><span class="badge bg-{{ $jobTitle->is_active ? 'success' : 'secondary' }}">{{ $jobTitle->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                            <td><a href="{{ route('organization-structure.index', ['edit_job_title' => $jobTitle->id, 'section' => 'job-titles']) }}#job-titles" class="btn btn-sm btn-warning">Edit</a></td>
                                        </tr>
                                        @empty<tr><td colspan="6" class="text-center text-muted py-4">Jabatan tidak ditemukan.</td></tr>@endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if($jobTitles->hasPages())<div class="card-footer">{{ $jobTitles->links() }}</div>@endif
                    </div>
                </div>
            </div>
        </section>

        <section id="positions" class="organization-section mb-5">
            <div class="row g-4">
                <div class="col-xl-4">
                    <div class="card organization-form-card">
                        <div class="card-header bg-white"><h5 class="mb-1">{{ $editPosition ? 'Edit Posisi Organisasi' : 'Tambah Posisi Organisasi' }}</h5><small class="text-muted">Posisi adalah node yang akan tampil pada bagan.</small></div>
                        <div class="card-body">
                            <form method="POST" action="{{ $editPosition ? route('organization-structure.positions.update', $editPosition) : route('organization-structure.positions.store') }}" id="positionForm" data-loading-form>
                                @csrf
                                @if($editPosition) @method('PUT') @endif
                                <div class="mb-3"><label class="form-label">Kode Posisi</label><input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', optional($editPosition)->code) }}" placeholder="VDNI-GA-SPV-01" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="mb-3"><label class="form-label">Nama Posisi</label><input type="text" name="position_name" class="form-control @error('position_name') is-invalid @enderror" value="{{ old('position_name', optional($editPosition)->position_name) }}" placeholder="Supervisor Electrical Workshop" required><small class="text-muted">Nama penempatan spesifik pada struktur, berbeda dari master jabatan.</small>@error('position_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="mb-3"><label class="form-label">Perusahaan</label><select name="perusahaan_id" id="positionCompany" class="form-select" required><option value="">Pilih perusahaan</option>@foreach($companies as $company)<option value="{{ $company->id }}" {{ (string) $selectedPositionCompany === (string) $company->id ? 'selected' : '' }}>{{ $company->kode_perusahaan }} - {{ $company->nama_perusahaan }}</option>@endforeach</select></div>
                                <div class="mb-3"><label class="form-label">Departemen</label><select name="departemen_id" id="positionDepartment" class="form-select @error('departemen_id') is-invalid @enderror" required><option value="">Pilih departemen</option>@foreach($departments as $department)<option value="{{ $department->id }}" data-company-id="{{ $department->perusahaan_id }}" {{ (string) $selectedPositionDepartment === (string) $department->id ? 'selected' : '' }}>{{ $department->departemen }}</option>@endforeach</select>@error('departemen_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="mb-3"><label class="form-label">Divisi</label><select name="divisi_id" id="positionDivision" class="form-select @error('divisi_id') is-invalid @enderror"><option value="">Tanpa divisi / tingkat departemen</option>@foreach($divisions as $division)<option value="{{ $division->id }}" data-department-id="{{ $division->departemen_id }}" {{ (string) $selectedPositionDivision === (string) $division->id ? 'selected' : '' }}>{{ $division->nama_divisi }}</option>@endforeach</select>@error('divisi_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="mb-3"><label class="form-label">Jabatan</label><select name="job_title_id" class="form-select @error('job_title_id') is-invalid @enderror" required><option value="">Pilih jabatan</option>@foreach($activeJobTitles as $jobTitle)<option value="{{ $jobTitle->id }}" {{ (string) old('job_title_id', optional($editPosition)->job_title_id) === (string) $jobTitle->id ? 'selected' : '' }}>{{ $jobTitle->display_name }} — {{ optional($jobTitle->level)->code }}</option>@endforeach</select>@error('job_title_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="mb-3"><label class="form-label">Override Level <span class="text-muted">(opsional)</span></label><select name="job_level_id" class="form-select"><option value="">Gunakan level default jabatan</option>@foreach($activeLevels as $level)<option value="{{ $level->id }}" {{ (string) old('job_level_id', optional($editPosition)->job_level_id) === (string) $level->id ? 'selected' : '' }}>{{ $level->code }} - {{ $level->name }}</option>@endforeach</select></div>
                                <div class="mb-3"><label class="form-label">Atasan Struktural</label><select name="parent_position_id" id="positionParent" class="form-select @error('parent_position_id') is-invalid @enderror"><option value="">Tidak ada / posisi puncak</option>@foreach($availableParents as $parent)<option value="{{ $parent->id }}" data-department-id="{{ $parent->departemen_id }}" {{ (string) old('parent_position_id', optional($editPosition)->parent_position_id) === (string) $parent->id ? 'selected' : '' }}>{{ $parent->display_name }} — {{ optional($parent->jobTitle)->display_name }} ({{ $parent->code }})</option>@endforeach</select>@error('parent_position_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                <div class="row g-3"><div class="col-6"><label class="form-label">Rencana HC</label><input type="number" name="planned_headcount" min="1" max="10000" class="form-control" value="{{ old('planned_headcount', optional($editPosition)->planned_headcount ?: 1) }}" required></div><div class="col-6"><label class="form-label">Urutan</label><input type="number" name="sort_order" min="0" class="form-control" value="{{ old('sort_order', optional($editPosition)->sort_order ?: 0) }}"></div></div>
                                <div class="row g-3 mt-0"><div class="col-6"><label class="form-label">Berlaku Mulai</label><input type="date" name="effective_from" class="form-control" value="{{ old('effective_from', optional(optional($editPosition)->effective_from)->format('Y-m-d')) }}"></div><div class="col-6"><label class="form-label">Berlaku Sampai</label><input type="date" name="effective_until" class="form-control" value="{{ old('effective_until', optional(optional($editPosition)->effective_until)->format('Y-m-d')) }}"></div></div>
                                <div class="mb-3 mt-3"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', optional($editPosition)->notes) }}</textarea></div>
                                <div class="form-check form-switch mb-3"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="positionActive" {{ old('is_active', $editPosition ? $editPosition->is_active : true) ? 'checked' : '' }}><label for="positionActive" class="form-check-label">Aktif</label></div>
                                <div class="d-flex gap-2"><button class="btn btn-primary" data-loading-text="Menyimpan...">{{ $editPosition ? 'Simpan Perubahan' : 'Tambah Posisi' }}</button>@if($editPosition)<a href="{{ route('organization-structure.index', ['section' => 'positions']) }}#positions" class="btn btn-light">Batal</a>@endif</div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <form method="GET" action="{{ route('organization-structure.index') }}" class="row g-2 align-items-center"><div class="col"><h5 class="mb-0">Daftar Posisi Organisasi</h5></div><div class="col-md-6"><select name="departemen_id" class="form-select"><option value="">Semua departemen</option>@foreach($departments as $department)<option value="{{ $department->id }}" {{ (string) $selectedDepartmentId === (string) $department->id ? 'selected' : '' }}>{{ optional($department->perusahaan)->kode_perusahaan }} — {{ $department->departemen }}</option>@endforeach</select></div><div class="col-auto"><button class="btn btn-outline-primary">Filter</button></div></form>
                        </div>
                        <div class="card-body p-0"><div class="table-responsive"><table class="table organization-table mb-0"><thead class="table-light"><tr><th>Posisi</th><th>Scope</th><th>Level</th><th>Atasan</th><th>HC</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                            @forelse($positions as $position)
                            <tr>
                                <td><strong>{{ $position->display_name }}</strong><small class="d-block text-muted">Jabatan: {{ optional($position->jobTitle)->display_name }}</small><small class="d-block text-muted">{{ $position->code }}</small></td>
                                <td>{{ optional($position->departemen)->departemen }}<small class="d-block text-muted">{{ optional($position->divisi)->nama_divisi ?: 'Tingkat departemen' }}</small></td>
                                <td><span class="badge bg-primary">{{ optional($position->effective_level)->code }}</span> rank {{ optional($position->effective_level)->rank }}@if($position->job_level_id)<small class="d-block text-warning">Override</small>@endif</td>
                                <td>{{ optional($position->parent)->display_name ?: 'Posisi puncak' }}</td>
                                <td>{{ $position->activeAssignments->count() }} / {{ $position->planned_headcount }}@if($position->activeAssignments->isNotEmpty())<small class="d-block text-muted">{{ $position->activeAssignments->pluck('employee.nama_karyawan')->filter()->take(3)->implode(', ') }}</small>@endif</td>
                                <td><span class="badge bg-{{ $position->is_active ? 'success' : 'secondary' }}">{{ $position->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td><a href="{{ route('organization-structure.index', ['edit_position' => $position->id, 'departemen_id' => $selectedDepartmentId, 'section' => 'positions']) }}#positions" class="btn btn-sm btn-warning">Edit</a></td>
                            </tr>
                            @empty<tr><td colspan="7" class="text-center text-muted py-4">Belum ada posisi organisasi pada filter ini.</td></tr>@endforelse
                        </tbody></table></div></div>
                        @if($positions->hasPages())<div class="card-footer">{{ $positions->links() }}</div>@endif
                    </div>
                </div>
            </div>
        </section>

        <section id="assignments" class="organization-section mb-5">
            <div class="card organization-form-card">
                <div class="card-header bg-white"><h5 class="mb-1">Tempatkan Karyawan</h5><small class="text-muted">Assignment aktif lama akan ditutup satu hari sebelum tanggal efektif assignment baru.</small></div>
                <div class="card-body">
                    <form method="POST" action="#" id="assignmentForm" data-action-template="{{ route('organization-structure.assignments.store', ['organizationPosition' => '__POSITION__']) }}" data-loading-form>
                        @csrf
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label class="form-label">Posisi Organisasi</label>
                                <select id="assignmentPosition" class="form-select" required>
                                    <option value="">Pilih posisi</option>
                                    @foreach($availableParents as $position)<option value="{{ $position->id }}" data-department-id="{{ $position->departemen_id }}" data-division-id="{{ $position->divisi_id }}">{{ optional($position->departemen)->departemen }} — {{ $position->display_name }} / {{ optional($position->jobTitle)->display_name }} ({{ $position->code }})</option>@endforeach
                                </select>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Cari Karyawan Aktif</label>
                                <div class="input-group"><input type="search" id="employeeSearch" class="form-control" placeholder="NIK atau nama, minimal 2 karakter"><button type="button" id="employeeSearchButton" class="btn btn-outline-primary">Cari</button></div>
                                <div id="employeeSearchFeedback" class="organization-help mt-1">Pilih posisi terlebih dahulu agar hasil sesuai departemen/divisi.</div>
                                <div id="employeeSearchResults" class="list-group employee-search-results mt-2"></div>
                                <input type="hidden" name="employee_nik" id="selectedEmployeeNik" value="{{ old('employee_nik') }}" required>
                            </div>
                            <div class="col-lg-2"><label class="form-label">Tanggal Efektif</label><input type="date" name="effective_from" class="form-control" value="{{ old('effective_from', now()->toDateString()) }}" required></div>
                            <div class="col-lg-2"><label class="form-label">Nomor Referensi</label><input type="text" name="reference_number" class="form-control" value="{{ old('reference_number') }}"></div>
                            <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
                            <div class="col-12"><button class="btn btn-primary" data-loading-text="Menempatkan...">Simpan Penempatan</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function filterSelect(select, dataKey, value) {
        if (!select) return;
        Array.from(select.options).forEach(function (option, index) {
            if (index === 0) return;
            const visible = !value || option.dataset[dataKey] === String(value);
            option.hidden = !visible;
            if (!visible && option.selected) select.value = '';
        });
    }

    const company = document.getElementById('positionCompany');
    const department = document.getElementById('positionDepartment');
    const division = document.getElementById('positionDivision');
    const parent = document.getElementById('positionParent');
    function refreshPositionScope() {
        filterSelect(department, 'companyId', company ? company.value : '');
        filterSelect(division, 'departmentId', department ? department.value : '');
        filterSelect(parent, 'departmentId', department ? department.value : '');
    }
    if (company) company.addEventListener('change', function () { department.value = ''; division.value = ''; parent.value = ''; refreshPositionScope(); });
    if (department) department.addEventListener('change', function () { division.value = ''; parent.value = ''; refreshPositionScope(); });
    refreshPositionScope();

    const assignmentForm = document.getElementById('assignmentForm');
    const assignmentPosition = document.getElementById('assignmentPosition');
    const searchInput = document.getElementById('employeeSearch');
    const searchButton = document.getElementById('employeeSearchButton');
    const results = document.getElementById('employeeSearchResults');
    const feedback = document.getElementById('employeeSearchFeedback');
    const selectedNik = document.getElementById('selectedEmployeeNik');

    function updateAssignmentAction() {
        const positionId = assignmentPosition.value;
        assignmentForm.action = positionId ? assignmentForm.dataset.actionTemplate.replace('__POSITION__', positionId) : '#';
        selectedNik.value = '';
        results.innerHTML = '';
    }

    if (assignmentPosition) assignmentPosition.addEventListener('change', updateAssignmentAction);

    async function searchEmployees() {
        const option = assignmentPosition.selectedOptions[0];
        const query = searchInput.value.trim();
        if (!assignmentPosition.value) { feedback.textContent = 'Pilih posisi organisasi terlebih dahulu.'; return; }
        if (query.length < 2) { feedback.textContent = 'Masukkan minimal 2 karakter.'; return; }

        searchButton.disabled = true;
        searchButton.textContent = 'Mencari...';
        results.innerHTML = '';
        try {
            const params = new URLSearchParams({ q: query, departemen_id: option.dataset.departmentId || '', divisi_id: option.dataset.divisionId || '' });
            const response = await fetch('{{ route('organization-structure.employees.search') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Pencarian gagal.');
            if (!payload.data.length) { feedback.textContent = 'Karyawan aktif tidak ditemukan pada scope posisi.'; return; }
            feedback.textContent = 'Klik karyawan untuk memilih.';
            payload.data.forEach(function (employee) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action';
                button.innerHTML = '<strong>' + employee.text.replace(/[&<>"']/g, function (m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]; }) + '</strong><small class="d-block text-muted"></small>';
                button.querySelector('small').textContent = employee.job_title || 'Jabatan belum diisi';
                button.addEventListener('click', function () {
                    selectedNik.value = employee.id;
                    searchInput.value = employee.text;
                    feedback.textContent = 'Karyawan dipilih: ' + employee.text;
                    results.innerHTML = '';
                });
                results.appendChild(button);
            });
        } catch (error) {
            feedback.textContent = error.message || 'Pencarian gagal. Silakan coba lagi.';
        } finally {
            searchButton.disabled = false;
            searchButton.textContent = 'Cari';
        }
    }
    if (searchButton) searchButton.addEventListener('click', searchEmployees);
    if (searchInput) searchInput.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); searchEmployees(); } });

    document.querySelectorAll('[data-loading-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form === assignmentForm && (!assignmentPosition.value || !selectedNik.value)) {
                event.preventDefault();
                feedback.textContent = 'Posisi dan karyawan wajib dipilih.';
                return;
            }
            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (!button || button.disabled) { event.preventDefault(); return; }
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.textContent = button.dataset.loadingText || 'Memproses...';
        });
    });

    const requestedSection = @json(request('section'));
    if (requestedSection) {
        const section = document.getElementById(requestedSection);
        if (section) setTimeout(function () { section.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 100);
    }
})();
</script>
@endpush
