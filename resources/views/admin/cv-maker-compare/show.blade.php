@extends('layouts.app')

@section('title', 'Detail Compare CV Maker')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-cv-maker-compare.css') }}">
@endpush

@section('content')
@php
$groupLabels = [
    'identity' => 'Identitas',
    'family' => 'Keluarga',
    'address' => 'Alamat',
    'administration' => 'Administrasi',
    'work' => 'Organisasi',
    'location' => 'Wilayah',
    'education' => 'Pendidikan',
];
$cvProfile = $detail['cv_profile'] ?? null;
$progressStatus = $detail['progress_status'] ?? null;
$progressHistories = $detail['progress_histories'] ?? collect();
$vitae = $detail['vitae'] ?? [];
$comparison = $detail['comparison'] ?? ['groups' => [], 'mismatch_count' => 0, 'compared_count' => 0];
$relatedComparison = $detail['related_comparison'] ?? [];
$canUpdateFromCv = (bool) ($detail['can_update'] ?? false);
$mismatchKeys = collect($comparison['groups'] ?? [])
    ->flatMap(fn($items) => $items)
    ->filter(fn($item) => !empty($item['mismatch']))
    ->pluck('key')
    ->filter()
    ->values()
    ->all();
$isMismatch = function (array $keys) use ($mismatchKeys): bool {
    return count(array_intersect($keys, $mismatchKeys)) > 0;
};
$cvName = $vitae['profile']['name'] ?? ($employee->nama_karyawan ?: $employee->nik);
$cvInitials = collect(explode(' ', trim((string) $cvName)))
    ->filter()
    ->take(2)
    ->map(fn($part) => mb_substr($part, 0, 1))
    ->implode('');
$profile = $vitae['profile'] ?? [];
$employeePhotoUrl = $employee->document_photo_url;
$hasCvValue = function ($value): bool {
    return filled($value) && trim((string) $value) !== '-';
};
$cvText = function ($value, string $fallback = '-') use ($hasCvValue): string {
    return $hasCvValue($value) ? (string) $value : $fallback;
};
$cvMultilineBullets = function ($value) use ($hasCvValue): array {
    if (!$hasCvValue($value)) {
        return [];
    }

    $text = str_replace(["\r\n", "\r"], "\n", (string) $value);

    if (strpos($text, "\n") === false) {
        return [];
    }

    return collect(explode("\n", $text))
        ->map(function ($line) {
            $line = preg_replace('/^\s*(?:[-*]+|\d+[.)])\s*/', '', (string) $line) ?: (string) $line;

            return trim($line, " \t\n\r\0\x0B;");
        })
        ->filter()
        ->values()
        ->all();
};
$cvHeaderMeta = collect([
    ['value' => 'NIK: ' . $employee->nik, 'keys' => []],
    ['value' => $profile['birth'] ?? null, 'keys' => ['birth_date']],
    ['value' => $profile['gender'] ?? null, 'keys' => ['gender']],
    ['value' => $profile['blood_type'] ?? null, 'keys' => ['blood_type']],
    ['value' => !empty($profile['height']) ? 'Tinggi ' . $profile['height'] . ' cm' : null, 'keys' => ['height']],
    ['value' => !empty($profile['weight']) ? 'Berat ' . $profile['weight'] . ' kg' : null, 'keys' => ['weight']],
    ['value' => $profile['marital_status'] ?? null, 'keys' => ['marital_status']],
])->filter(fn($item) => $hasCvValue($item['value']))->values();
$cvAddressLines = collect([
    ['value' => !empty($profile['ktp_address']) ? 'KTP: ' . $profile['ktp_address'] : null, 'keys' => ['ktp_address']],
    ['value' => !empty($profile['address']) ? 'Domisili: ' . $profile['address'] : null, 'keys' => ['domicile_address']],
    ['value' => $profile['location'] ?? null, 'keys' => ['province', 'regency', 'district', 'village']],
])->filter(fn($item) => $hasCvValue($item['value']))->values();
$cvContactMeta = collect([
    ['value' => $profile['phone'] ?? null, 'keys' => ['phone']],
    ['value' => $profile['email'] ?? null, 'keys' => []],
])->filter(fn($item) => $hasCvValue($item['value']))->values();
$hasSkills = !empty($profile['technical_skills']) || !empty($profile['non_technical_skills']);
$hasAdditional = !empty($vitae['projects']) || !empty($vitae['organizations']) || !empty($vitae['languages']);
$completeProfileGroups = [
    'Identitas & keluarga' => [
        'Nama lengkap' => $profile['name'] ?? null,
        'Tempat lahir' => $profile['birth_place'] ?? null,
        'Tanggal lahir' => $profile['birth_date'] ?? null,
        'Nomor KTP' => $profile['ktp_number'] ?? null,
        'Nomor KK' => $profile['family_card_number'] ?? null,
        'NPWP' => $profile['npwp_number'] ?? null,
        'Nomor rekening' => $profile['bank_account_number'] ?? null,
        'Jenis kelamin' => $profile['gender'] ?? null,
        'Tinggi / berat' => implode(' / ', array_filter([$profile['height'] ?? null, $profile['weight'] ?? null])),
        'Golongan darah' => $profile['blood_type'] ?? null,
        'Agama' => $profile['religion'] ?? null,
        'Status perkawinan' => $profile['marital_status'] ?? null,
        'Tanggal menikah' => $profile['marriage_date'] ?? null,
        'Nama pasangan' => $profile['spouse_name'] ?? null,
        'Nama ibu kandung' => $profile['mother_name'] ?? null,
        'Memiliki anak' => $profile['has_children'] ?? null,
        'Nama anak' => !empty($profile['children_names']) ? implode(', ', $profile['children_names']) : null,
    ],
    'Alamat & kontak' => [
        'Alamat KTP' => $profile['ktp_address'] ?? null,
        'RT / RW' => implode(' / ', array_filter([$profile['rt'] ?? null, $profile['rw'] ?? null])),
        'Domisili sama dengan KTP' => $profile['domicile_same_as_ktp'] ?? null,
        'Provinsi' => $profile['province'] ?? null,
        'Kabupaten/Kota' => $profile['regency'] ?? null,
        'Kecamatan' => $profile['district'] ?? null,
        'Kelurahan/Desa' => $profile['village'] ?? null,
        'Alamat domisili' => $profile['address'] ?? null,
        'Telepon' => $profile['phone'] ?? null,
        'Email' => $profile['email'] ?? null,
        'Instagram' => $profile['instagram'] ?? null,
        'LinkedIn' => $profile['linkedin'] ?? null,
        'Facebook' => $profile['facebook'] ?? null,
    ],
    'Kompetensi & minat' => [
        'Keahlian teknis' => !empty($profile['technical_skills']) ? implode(', ', $profile['technical_skills']) : null,
        'Keahlian non-teknis' => !empty($profile['non_technical_skills']) ? implode(', ', $profile['non_technical_skills']) : null,
        'Hobi' => !empty($profile['hobbies']) ? implode(', ', $profile['hobbies']) : null,
        'Bakat' => !empty($profile['talents']) ? implode(', ', $profile['talents']) : null,
    ],
];
@endphp

<div class="container-fluid">
    <div class="page-inner ui-page cv-compare-page">
        <div class="ui-page-header cv-compare-header">
            <div class="ui-page-heading">
                <div class="ui-page-icon" aria-hidden="true">
                    <i class="fas fa-eye"></i>
                </div>
                <div>
                    <h4 class="ui-page-title">Detail Compare CV Maker</h4>
                    <p class="ui-page-subtitle">{{ $employee->nik }} - {{ $employee->nama_karyawan ?: '-' }}</p>
                </div>
            </div>
            <a href="{{ route('cv-maker-compare.index') }}" class="btn btn-light border ui-btn-icon">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>
        </div>

        @if(!$integrationAvailable)
        <div class="alert ui-alert ui-alert--warning cv-compare-alert mb-3">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Koneksi CV Maker belum dikonfigurasi. Set env <code>CV_MAKER_DB_*</code> dan <code>CV_MAKER_NIK_HASH_KEY</code>.
        </div>
        @endif

        <section class="ui-panel cv-detail-summary-panel mb-3">
            <div class="ui-panel__body">
                <div class="row g-3 align-items-start">
                    <div class="col-xl-4 col-md-6">
                        <div class="cv-detail-summary">
                            <div class="cv-detail-summary__label">Karyawan HRIS</div>
                            <div class="cv-detail-summary__value">{{ $employee->nama_karyawan ?: '-' }}</div>
                            <div class="cv-detail-summary__meta">NIK {{ $employee->nik }}</div>
                            <div class="cv-detail-summary__meta">
                                {{ $employee->area_kerja ?: '-' }} /
                                {{ optional($employee->departemen)->departemen ?: '-' }} /
                                {{ optional($employee->divisi)->nama_divisi ?: '-' }}
                            </div>
                            <div class="cv-detail-summary__meta">{{ $employee->posisi ?: ($employee->jabatan ?: '-') }}</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="cv-detail-summary">
                            <div class="cv-detail-summary__label">CV Maker</div>
                            <div class="cv-detail-summary__value">{!! $detail['cv_status'] !!}</div>
                            @if($cvProfile && !empty($cvProfile['profile_id']))
                            <div class="cv-detail-summary__meta">Profile ID: {{ $cvProfile['profile_id'] }}</div>
                            @if(!empty($cvProfile['updated_at']))
                            <div class="cv-detail-summary__meta">
                                Update terakhir: {{ \Carbon\Carbon::parse($cvProfile['updated_at'])->format('d/m/Y H:i') }}
                            </div>
                            @endif
                            @else
                            <div class="cv-detail-summary__meta">Profil CV Maker belum tersedia untuk karyawan ini.</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-6">
                        <div class="cv-detail-summary">
                            <div class="cv-detail-summary__label">Hasil</div>
                            <div class="cv-detail-summary__value">{!! $detail['summary'] !!}</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="cv-detail-actions">
                            <a href="{{ route('karyawan.edit', $employee->nik) }}" class="btn btn-outline-primary ui-btn-icon">
                                <i class="fas fa-edit"></i>
                                Edit HRIS
                            </a>
                            <button
                                type="button"
                                class="btn btn-danger ui-btn-icon js-cv-update-preview"
                                data-preview-url="{{ route('cv-maker-compare.preview-update', $employee->nik) }}"
                                data-update-url="{{ route('cv-maker-compare.update-hris', $employee->nik) }}"
                                data-employee-name="{{ $employee->nama_karyawan ?: $employee->nik }}"
                                {{ $canUpdateFromCv ? '' : 'disabled' }}>
                                <i class="fas fa-sync-alt"></i>
                                Update dari CV Maker
                            </button>
                            @if(!$canUpdateFromCv)
                            <small class="text-muted d-block">Update tersedia jika koneksi CV Maker aktif dan profil karyawan ditemukan.</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-panel cv-progress-detail-panel mb-3">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title">Progress Pengisian CV</h5>
                    <p class="ui-panel__meta">Snapshot HRIS dari pengisian CV Maker. Riwayat mulai tercatat sejak fitur ini aktif.</p>
                </div>
            </div>
            <div class="ui-panel__body">
                <div class="cv-progress-detail-grid">
                    <div class="cv-progress-detail-summary">
                        {!! $detail['progress_html'] ?? '' !!}
                        @if($progressStatus)
                        <div class="cv-progress-detail-summary__meta">
                            Sync terakhir:
                            {{ $progressStatus->last_synced_at ? $progressStatus->last_synced_at->format('d/m/Y H:i') : '-' }}
                        </div>
                        @else
                        <div class="cv-progress-detail-summary__meta">Jalankan scheduler sync untuk membuat snapshot awal.</div>
                        @endif
                        @if($progressStatus)
                        <form id="cvReviewStatusForm" action="{{ route('cv-maker-compare.review-status.update', $employee->nik) }}" method="POST" class="mt-3">
                            @csrf
                            <label class="form-label fw-semibold" for="cvReviewStatus">Status pemeriksaan HR</label>
                            <select class="form-select form-select-sm" id="cvReviewStatus" name="review_status">
                                @foreach(\App\Models\CvMakerProgressStatus::reviewLabels() as $value => $label)
                                <option value="{{ $value }}" {{ ($progressStatus->review_status ?: 'unreviewed') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="form-label fw-semibold mt-2" for="cvReviewNote">Catatan</label>
                            <textarea class="form-control form-control-sm" id="cvReviewNote" name="review_note" rows="3" maxlength="500" placeholder="Wajib jika perlu konfirmasi karyawan">{{ $progressStatus->review_note }}</textarea>
                            @if($progressStatus->reviewer)
                            <div class="small text-muted mt-1">
                                Terakhir oleh {{ $progressStatus->reviewer->name }}
                                {{ $progressStatus->reviewed_at ? 'pada ' . $progressStatus->reviewed_at->format('d/m/Y H:i') : '' }}
                            </div>
                            @endif
                            <button type="submit" class="btn btn-sm btn-primary ui-btn-icon mt-2">
                                <i class="fas fa-save"></i> Simpan Status Pemeriksaan
                            </button>
                        </form>
                        @endif
                    </div>
                    <div class="cv-progress-history">
                        <div class="cv-progress-history__title">Riwayat Terbaru</div>
                        @if($progressHistories->isEmpty())
                        <div class="cv-progress-history__empty">
                            Belum ada riwayat progress.
                        </div>
                        @else
                        <div class="cv-progress-history__list">
                            @foreach($progressHistories as $history)
                            <div class="cv-progress-history__item">
                                <div class="cv-progress-history__main">
                                    <span class="badge bg-light text-dark border">{{ str_replace('_', ' ', ucfirst($history->event_type)) }}</span>
                                    <span>{{ $history->message ?: 'Progress CV Maker diperbarui.' }}</span>
                                </div>
                                <div class="cv-progress-history__meta">
                                    {{ $history->created_at ? $history->created_at->format('d/m/Y H:i') : '-' }}
                                    @if($history->from_step || $history->to_step)
                                    <span>&middot;</span>
                                    Tahap {{ $history->from_step ?: '-' }} ke {{ $history->to_step ?: '-' }}
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="ui-panel cv-vitae-panel mb-3">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title">Tampilan CV</h5>
                    <p class="ui-panel__meta">Preview data CV Maker dalam format vitae untuk validasi input anggota.</p>
                </div>
                @if(!empty($vitae['profile']['last_generated_at']))
                <span class="badge bg-light text-dark border">Generated {{ $vitae['profile']['last_generated_at'] }}</span>
                @endif
            </div>
            <div class="ui-panel__body">
                @if(empty($vitae['has_content']))
                <div class="alert ui-alert mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Data CV Maker belum cukup untuk ditampilkan sebagai CV.
                </div>
                @else
                <article class="cv-vitae-sheet">
                    <header class="cv-vitae-header">
                        <div class="cv-vitae-identity">
                            <h1 class="{{ $isMismatch(['name']) ? 'cv-vitae-field--mismatch' : '' }}">
                                {{ $cvText($profile['name'] ?? null) }}
                                @if($isMismatch(['name']))
                                <span class="cv-vitae-mismatch-badge">Tidak sesuai HRIS</span>
                                @endif
                            </h1>

                            @if($cvHeaderMeta->isNotEmpty())
                            <div class="cv-vitae-meta-line cv-vitae-meta-line--primary">
                                @foreach($cvHeaderMeta as $meta)
                                <span class="{{ $isMismatch($meta['keys']) ? 'cv-vitae-field--mismatch' : '' }}">
                                    {{ $meta['value'] }}
                                </span>
                                @endforeach
                            </div>
                            @endif

                            @if($hasCvValue($profile['organization'] ?? null) || $hasCvValue($profile['position'] ?? null))
                            <div class="cv-vitae-meta-line cv-vitae-meta-line--work{{ $isMismatch(['work_area', 'department', 'division', 'position']) ? ' cv-vitae-field--mismatch' : '' }}">
                                @if($hasCvValue($profile['organization'] ?? null))
                                <span>{{ $cvText($profile['organization'] ?? null) }}</span>
                                @endif
                                @if($hasCvValue($profile['position'] ?? null))
                                <span>{{ $cvText($profile['position'] ?? null) }}</span>
                                @endif
                            </div>
                            @endif

                            @if($cvAddressLines->isNotEmpty())
                            <div class="cv-vitae-address{{ $isMismatch(['ktp_address', 'domicile_address', 'province', 'regency', 'district', 'village']) ? ' cv-vitae-field--mismatch' : '' }}">
                                @foreach($cvAddressLines as $line)
                                <div class="{{ $isMismatch($line['keys']) ? 'cv-vitae-field--mismatch' : '' }}">{{ $line['value'] }}</div>
                                @endforeach
                            </div>
                            @endif

                            @if($cvContactMeta->isNotEmpty())
                            <div class="cv-vitae-meta-line cv-vitae-contact-line">
                                @foreach($cvContactMeta as $meta)
                                <span class="{{ $isMismatch($meta['keys']) ? 'cv-vitae-field--mismatch' : '' }}">
                                    {{ $meta['value'] }}
                                </span>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        <div class="cv-vitae-photo" aria-label="Foto karyawan">
                            @if($employeePhotoUrl)
                            <img src="{{ $employeePhotoUrl }}" alt="Foto {{ $cvText($profile['name'] ?? $employee->nama_karyawan) }}">
                            @else
                            <span>{{ $cvInitials ?: 'CV' }}</span>
                            @endif
                        </div>
                    </header>

                    @if($hasCvValue($profile['summary'] ?? null))
                    <section class="cv-vitae-section">
                        <h3>Ringkasan Profil</h3>
                        @php
                        $summaryBullets = $cvMultilineBullets($profile['summary'] ?? null);
                        @endphp
                        @if(count($summaryBullets) > 1)
                        <ul class="cv-vitae-bullet-list">
                            @foreach($summaryBullets as $summaryLine)
                            <li>{{ $summaryLine }}</li>
                            @endforeach
                        </ul>
                        @else
                        <p>{!! nl2br(e($profile['summary'])) !!}</p>
                        @endif
                    </section>
                    @endif

                    @if(!empty($vitae['educations']))
                    <section class="cv-vitae-section{{ $isMismatch(['education_level', 'education_institution', 'education_major', 'graduation_year']) ? ' cv-vitae-section--mismatch' : '' }}">
                        <h3>
                            <span>Pendidikan</span>
                            @if($isMismatch(['education_level', 'education_institution', 'education_major', 'graduation_year']))
                            <span class="cv-vitae-mismatch-badge">Tidak sesuai HRIS</span>
                            @endif
                        </h3>
                        @foreach($vitae['educations'] as $education)
                        <div class="cv-vitae-item cv-vitae-item--education">
                            <h4>{{ $cvText($education['title'] ?? null) }}</h4>
                            <div class="cv-vitae-item__meta">
                                @foreach(collect([$education['institution'] ?? null, $education['year'] ?? null])->filter($hasCvValue)->values() as $meta)
                                <span>{{ $meta }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </section>
                    @endif

                    @if(!empty($vitae['experiences']))
                    <section class="cv-vitae-section">
                        <h3>Pengalaman Kerja</h3>
                        @foreach($vitae['experiences'] as $experience)
                        <div class="cv-vitae-item">
                            <h4>{{ $cvText($experience['title'] ?? null) }}</h4>
                            <div class="cv-vitae-item__meta">
                                @foreach(collect([$experience['company'] ?? null, $experience['department'] ?? null, $experience['division'] ?? null, $experience['period'] ?? null])->filter($hasCvValue)->values() as $meta)
                                <span>{{ $meta }}</span>
                                @endforeach
                            </div>
                            @if(!empty($experience['responsibilities']))
                            <ul>
                                @foreach($experience['responsibilities'] as $responsibility)
                                <li>{{ $responsibility }}</li>
                                @endforeach
                            </ul>
                            @endif
                        </div>
                        @endforeach
                    </section>
                    @endif

                    @if($hasSkills)
                    <section class="cv-vitae-section">
                        <h3>Keahlian</h3>
                        @if(!empty($profile['technical_skills']))
                        <p class="cv-vitae-skill-line">
                            <strong>Teknis:</strong>
                            {{ implode(', ', $profile['technical_skills']) }}
                        </p>
                        @endif
                        @if(!empty($profile['non_technical_skills']))
                        <p class="cv-vitae-skill-line">
                            <strong>Non-teknis:</strong>
                            {{ implode(', ', $profile['non_technical_skills']) }}
                        </p>
                        @endif
                    </section>
                    @endif

                    @if(!empty($vitae['certifications']))
                    <section class="cv-vitae-section">
                        <h3>Sertifikasi & Pelatihan</h3>
                        <div class="cv-vitae-table-wrap">
                            <table class="cv-vitae-table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Penerbit/Penyelenggara</th>
                                        <th>Tahun</th>
                                        <th>Berlaku s/d</th>
                                        <th>Jenis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($vitae['certifications'] as $certification)
                                    <tr>
                                        <td>{{ $cvText($certification['title'] ?? null) }}</td>
                                        <td>{{ $cvText($certification['issuer'] ?? null) }}</td>
                                        <td>{{ $cvText($certification['year'] ?? null) }}</td>
                                        <td>{{ $cvText($certification['valid_until'] ?? null) }}</td>
                                        <td>{{ $cvText($certification['type'] ?? null) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                    @endif

                    @if($hasAdditional)
                    <section class="cv-vitae-section">
                        <h3>Tambahan</h3>
                        @if(!empty($vitae['projects']))
                        <p class="cv-vitae-additional-line">
                            <strong>Proyek:</strong>
                            @foreach($vitae['projects'] as $project)
                            {{ $cvText($project['name'] ?? null) }}@if($hasCvValue($project['year'] ?? null)) ({{ $project['year'] }})@endif{{ !$loop->last ? ', ' : '' }}
                            @endforeach
                        </p>
                        @endif
                        @if(!empty($vitae['organizations']))
                        <p class="cv-vitae-additional-line">
                            <strong>Organisasi:</strong>
                            @foreach($vitae['organizations'] as $organization)
                            {{ $cvText($organization['title'] ?? null) }}@if($hasCvValue($organization['role'] ?? null)) - {{ $organization['role'] }}@endif @if($hasCvValue($organization['period'] ?? null))({{ $organization['period'] }})@endif{{ !$loop->last ? ', ' : '' }}
                            @endforeach
                        </p>
                        @endif
                        @if(!empty($vitae['languages']))
                        <p class="cv-vitae-additional-line">
                            <strong>Bahasa:</strong>
                            @foreach($vitae['languages'] as $language)
                            {{ $cvText($language['language'] ?? null) }}@if($hasCvValue($language['level'] ?? null)) - {{ $language['level'] }}@endif{{ !$loop->last ? ', ' : '' }}
                            @endforeach
                        </p>
                        @endif
                    </section>
                    @endif
                </article>
                @endif
            </div>
        </section>

        @if($cvProfile && !empty($cvProfile['profile_id']))
        <section class="ui-panel mb-3">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title">Seluruh Data Vitae</h5>
                    <p class="ui-panel__meta">Field tanpa pasangan kolom di V-People tetap ditampilkan sebagai referensi HR.</p>
                </div>
                @if(!empty($profile['photo_available']))
                <a href="{{ route('cv-maker-compare.profiles.photo', ['nik' => $employee->nik, 'profile' => $cvProfile['profile_id']]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary ui-btn-icon">
                    <i class="fas fa-image"></i> Foto Vitae
                </a>
                @endif
            </div>
            <div class="ui-panel__body">
                <div class="row g-3">
                    @foreach($completeProfileGroups as $sectionLabel => $fields)
                    <div class="col-xl-4 col-md-6">
                        <div class="cv-detail-group h-100">
                            <div class="cv-detail-group__header"><h6>{{ $sectionLabel }}</h6></div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody>
                                        @foreach($fields as $label => $value)
                                        <tr><th class="text-muted fw-normal">{{ $label }}</th><td>{{ filled($value) ? $value : '-' }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                @if(!empty($vitae['achievements']))
                <div class="cv-detail-group mt-3">
                    <div class="cv-detail-group__header"><h6>Prestasi</h6></div>
                    <div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">
                        <thead><tr><th>Bidang</th><th>Prestasi</th><th>Peringkat</th><th>Tingkat</th><th>Periode</th></tr></thead>
                        <tbody>@foreach($vitae['achievements'] as $item)<tr><td>{{ $item['field'] ?: '-' }}</td><td>{{ $item['type'] ?: '-' }}</td><td>{{ $item['rank'] ?: '-' }}</td><td>{{ $item['level'] ?: '-' }}</td><td>{{ $item['period'] ?: '-' }}</td></tr>@endforeach</tbody>
                    </table></div>
                </div>
                @endif

                @if(!empty($vitae['emergency_contacts']))
                <div class="cv-detail-group mt-3">
                    <div class="cv-detail-group__header"><h6>Kontak Darurat</h6></div>
                    <div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">
                        <thead><tr><th>Nama</th><th>Hubungan</th><th>Nomor telepon</th></tr></thead>
                        <tbody>@foreach($vitae['emergency_contacts'] as $item)<tr><td>{{ $item['name'] ?: '-' }}</td><td>{{ $item['relationship'] ?: '-' }}</td><td>{{ $item['phone'] ?: '-' }}</td></tr>@endforeach</tbody>
                    </table></div>
                </div>
                @endif

                @if($canViewDocuments ?? false)
                <div class="cv-detail-group mt-3">
                    <div class="cv-detail-group__header"><h6>File yang Diunggah</h6><span class="badge bg-light text-dark border">{{ count($vitae['documents'] ?? []) }} file</span></div>
                    @if(!empty($vitae['documents']))
                    <div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">
                        <thead><tr><th>Jenis</th><th>Nama file</th><th>Format</th><th>Ukuran</th><th>Upload</th><th></th></tr></thead>
                        <tbody>
                            @foreach($vitae['documents'] as $document)
                            <tr>
                                <td>{{ $document['label'] }}</td><td>{{ $document['original_name'] ?: '-' }}</td><td>{{ $document['mime_type'] ?: '-' }}</td><td>{{ $document['file_size'] }}</td><td>{{ $document['uploaded_at'] ?: '-' }}</td>
                                <td><a href="{{ route('cv-maker-compare.documents.show', ['nik' => $employee->nik, 'document' => $document['id']]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> Lihat</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                    @else
                    <div class="text-muted p-3">Belum ada file yang diunggah di Vitae.</div>
                    @endif
                </div>
                @endif
            </div>
        </section>
        @endif

        <section class="ui-panel cv-detail-compare-panel">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title">Perbandingan Field</h5>
                    <p class="ui-panel__meta">Field HRIS yang memiliki mapping aman dapat dikoreksi langsung. Nilai merah berarti data HRIS dan CV Maker berbeda.</p>
                </div>
            </div>
            <div class="ui-panel__body">
                <div class="cv-detail-grid">
                    @foreach($groupLabels as $groupKey => $groupLabel)
                    <div class="cv-detail-group">
                        <div class="cv-detail-group__header">
                            <h6>{{ $groupLabel }}</h6>
                            @php
                            $groupItems = $comparison['groups'][$groupKey] ?? [];
                            $groupComparedCount = collect($groupItems)->where('skipped', false)->count();
                            $groupMismatchCount = collect($groupItems)->where('mismatch', true)->count();
                            @endphp
                            @if($groupComparedCount < 1)
                            <span class="badge bg-secondary">Diabaikan</span>
                            @elseif($groupMismatchCount > 0)
                            <span class="badge bg-danger">{{ $groupMismatchCount }} mismatch</span>
                            @else
                            <span class="badge bg-success">Sesuai</span>
                            @endif
                        </div>
                        <div class="cv-compare-fields cv-compare-fields--detail">
                            @foreach($groupItems as $item)
                            <div class="cv-compare-field{{ $item['mismatch'] ? ' cv-compare-field--mismatch' : ($item['skipped'] ? ' cv-compare-field--skipped' : '') }}">
                                <div class="cv-compare-field__label">{{ $item['label'] }}</div>
                                <div class="cv-compare-field__values">
                                    <div class="cv-compare-field__source cv-compare-field__source--hris">
                                        <div class="cv-compare-field__source-label">HRIS</div>
                                        @if(!empty($item['editable']))
                                        <form class="js-cv-inline-correction" action="{{ route('cv-maker-compare.correct-field', $employee->nik) }}" method="POST" data-label="{{ $item['label'] }}" data-sensitive="{{ !empty($item['sensitive']) ? '1' : '0' }}">
                                            @csrf
                                            <input type="hidden" name="field_key" value="{{ $item['key'] }}">
                                            <div class="cv-inline-correction__current">Saat ini: {!! $item['hris'] !!}</div>
                                            <div class="input-group input-group-sm">
                                                @if($item['key'] === 'gender')
                                                <select name="value" class="form-select js-cv-correction-value" aria-label="Koreksi {{ $item['label'] }}">
                                                    <option value="L" {{ ($item['edit_value'] ?? '') === 'L' ? 'selected' : '' }}>Laki-laki</option>
                                                    <option value="P" {{ ($item['edit_value'] ?? '') === 'P' ? 'selected' : '' }}>Perempuan</option>
                                                </select>
                                                @elseif($item['key'] === 'marital_status')
                                                <select name="value" class="form-select js-cv-correction-value" aria-label="Koreksi {{ $item['label'] }}">
                                                    @foreach(['Belum Kawin', 'Kawin', 'Cerai'] as $maritalOption)
                                                    <option value="{{ $maritalOption }}" {{ ($item['edit_value'] ?? '') === $maritalOption ? 'selected' : '' }}>{{ $maritalOption }}</option>
                                                    @endforeach
                                                </select>
                                                @else
                                                <input
                                                    type="{{ $item['input_type'] ?? 'text' }}"
                                                    name="value"
                                                    class="form-control js-cv-correction-value"
                                                    value="{{ $item['edit_value'] ?? '' }}"
                                                    placeholder="{{ !empty($item['sensitive']) ? 'Masukkan nilai baru' : 'Koreksi nilai HRIS' }}"
                                                    aria-label="Koreksi {{ $item['label'] }}"
                                                    autocomplete="off"
                                                    @if(in_array($item['key'], ['ktp_number', 'family_card_number'], true)) inputmode="numeric" maxlength="16" pattern="[0-9]{16}" @endif
                                                    @if(($item['input_type'] ?? '') === 'number') min="0" @endif>
                                                @endif
                                                <button type="submit" class="btn btn-primary" title="Simpan koreksi {{ $item['label'] }}">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </div>
                                            @if(!empty($item['sensitive']))
                                            <small class="cv-inline-correction__hint">Nilai asli tetap disamarkan. Isi hanya jika memang akan diganti.</small>
                                            @endif
                                        </form>
                                        @else
                                        <span>{!! $item['hris'] !!}</span>
                                        <small class="cv-inline-correction__hint">Gunakan menu Edit HRIS untuk field relasi/organisasi.</small>
                                        @endif
                                    </div>
                                    <div class="cv-compare-field__source cv-compare-field__source--cv">
                                        <div class="cv-compare-field__source-label">CV Maker</div>
                                        <span>{!! $item['cv'] !!}</span>
                                        @if($canUpdateFromCv && !empty($item['updatable_from_cv']))
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary ui-btn-icon cv-compare-field__apply js-cv-apply-single-field"
                                            data-update-url="{{ route('cv-maker-compare.update-hris', $employee->nik) }}"
                                            data-field-key="{{ $item['key'] }}"
                                            data-field-label="{{ $item['label'] }}"
                                            data-new-value="{{ strip_tags($item['cv']) }}"
                                            data-high-risk="{{ !empty($item['update_from_cv_high_risk']) ? '1' : '0' }}">
                                            <i class="fas fa-arrow-left"></i>
                                            Gunakan nilai ini
                                        </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>

                @if(!empty($relatedComparison))
                <div class="cv-detail-group mt-3">
                    <div class="cv-detail-group__header">
                        <h6>Riwayat & Kompetensi</h6>
                        @if(collect($relatedComparison)->where('mismatch', true)->count() > 0)
                        <span class="badge bg-danger">{{ collect($relatedComparison)->where('mismatch', true)->count() }} mismatch</span>
                        @else
                        <span class="badge bg-success">Sesuai</span>
                        @endif
                    </div>
                    <div class="cv-compare-fields cv-compare-fields--detail">
                        @foreach($relatedComparison as $item)
                        <div class="cv-compare-field{{ !empty($item['mismatch']) ? ' cv-compare-field--mismatch' : (!empty($item['skipped']) ? ' cv-compare-field--skipped' : '') }}">
                            <div class="cv-compare-field__label">{{ $item['label'] }}</div>
                            <div class="cv-compare-field__values">
                                <span title="V-People">{{ $item['hris'] }}</span>
                                <span title="Vitae">{{ $item['cv'] }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </section>
    </div>
</div>

@include('admin.cv-maker-compare.partials.update-modal')
@endsection

@push('scripts')
@include('admin.cv-maker-compare.partials.dialog-scripts')
<script>
    function cvInlineCorrectionError(xhr) {
        let message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Koreksi field gagal disimpan.';

        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            const firstKey = Object.keys(xhr.responseJSON.errors)[0];
            if (firstKey && xhr.responseJSON.errors[firstKey][0]) {
                message = xhr.responseJSON.errors[firstKey][0];
            }
        } else if (xhr.status === 401 || xhr.status === 419) {
            message = 'Sesi login berakhir. Silakan login ulang.';
        } else if (xhr.status === 403) {
            message = 'Anda tidak memiliki akses untuk mengoreksi data ini.';
        } else if (xhr.status === 0) {
            message = 'Koneksi bermasalah. Silakan cek jaringan lalu coba kembali.';
        }

        return message;
    }

    $(document).on('submit', '.js-cv-inline-correction', function(event) {
        event.preventDefault();
        const form = $(this);
        const button = form.find('button[type="submit"]');
        const input = form.find('.js-cv-correction-value');
        const label = form.data('label') || 'Field';
        const isSensitive = String(form.data('sensitive')) === '1';
        const value = String(input.val() || '').trim();

        if (!value) {
            window.CvMakerDialog.fire({ icon: 'warning', title: 'Nilai belum diisi', text: `Masukkan koreksi untuk ${label}.`, confirmButtonText: 'OK' });
            return;
        }

        window.CvMakerDialog.fire({
            icon: 'question',
            title: `Simpan koreksi ${label}?`,
            text: isSensitive
                ? 'Nilai sensitif akan diperbarui dan dicatat pada audit trail tanpa ditampilkan penuh.'
                : `Nilai HRIS akan diubah menjadi "${value}".`,
            showCancelButton: true,
            confirmButtonText: 'Simpan Koreksi',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            const originalHtml = button.html();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            input.prop('disabled', true);

            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: form.find('input[name="_token"]').val(),
                    field_key: form.find('input[name="field_key"]').val(),
                    value: value
                },
                success: function(response) {
                    const refreshRequest = typeof window.onCvMakerHrisUpdated === 'function'
                        ? window.onCvMakerHrisUpdated(response)
                        : null;

                    Promise.resolve(refreshRequest).then(function() {
                        window.CvMakerDialog.fire({
                            icon: 'success',
                            title: response.updated === false ? 'Tidak ada perubahan' : 'Berhasil',
                            text: response.message || 'Koreksi field berhasil disimpan.',
                            confirmButtonText: 'OK'
                        });
                    }).catch(function() {
                        window.CvMakerDialog.fire({
                            icon: 'warning',
                            title: 'Koreksi tersimpan',
                            text: 'Tampilan terbaru gagal dimuat. Silakan refresh halaman.',
                            confirmButtonText: 'OK'
                        });
                    });
                },
                error: function(xhr) {
                    window.CvMakerDialog.fire({ icon: 'error', title: 'Gagal', text: cvInlineCorrectionError(xhr), confirmButtonText: 'OK' });
                },
                complete: function() {
                    button.prop('disabled', false).html(originalHtml);
                    input.prop('disabled', false);
                }
            });
        });
    });

    $(document).on('click', '.js-cv-apply-single-field', function() {
        const button = $(this);
        const updateUrl = button.data('update-url');
        const fieldKey = String(button.data('field-key') || '');
        const fieldLabel = button.data('field-label') || 'Field';
        const newValue = String(button.data('new-value') || '-');
        const isHighRisk = String(button.data('high-risk')) === '1';

        if (!updateUrl || !fieldKey) {
            window.CvMakerDialog.fire({
                icon: 'warning',
                title: 'Update tidak tersedia',
                text: 'Informasi field yang akan diperbarui tidak lengkap.',
                confirmButtonText: 'OK'
            });
            return;
        }

        window.CvMakerDialog.fire({
            icon: isHighRisk ? 'warning' : 'question',
            title: `Update ${fieldLabel}?`,
            text: isHighRisk
                ? 'Field ini termasuk data penting. Pastikan nilai CV Maker telah diverifikasi sebelum melanjutkan.'
                : `Nilai HRIS akan diganti menggunakan nilai CV Maker: "${newValue}".`,
            showCancelButton: true,
            confirmButtonText: 'Ya, Update Field',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            const originalHtml = button.html();
            button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengupdate...');

            $.ajax({
                url: updateUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    selected_fields: [fieldKey],
                    selected_sections: [],
                    include_organization: 0
                },
                success: function(response) {
                    const refreshRequest = typeof window.onCvMakerHrisUpdated === 'function'
                        ? window.onCvMakerHrisUpdated(response)
                        : null;

                    Promise.resolve(refreshRequest).then(function() {
                        window.CvMakerDialog.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: response.message || `${fieldLabel} berhasil diperbarui dari CV Maker.`,
                            confirmButtonText: 'OK'
                        });
                    }).catch(function() {
                        window.CvMakerDialog.fire({
                            icon: 'warning',
                            title: 'Update tersimpan',
                            text: 'Tampilan terbaru gagal dimuat otomatis. Silakan refresh halaman.',
                            confirmButtonText: 'OK'
                        });
                    });
                },
                error: function(xhr) {
                    window.CvMakerDialog.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: cvInlineCorrectionError(xhr),
                        confirmButtonText: 'OK'
                    });
                },
                complete: function() {
                    button.prop('disabled', false).html(originalHtml);
                }
            });
        });
    });

    $(document).on('submit', '#cvReviewStatusForm', function(event) {
        event.preventDefault();
        const form = $(this);
        const button = form.find('button[type="submit"]');
        const originalHtml = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            dataType: 'json',
            data: form.serialize(),
            success: function(response) {
                window.CvMakerDialog.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: response.message || 'Status pemeriksaan berhasil diperbarui.',
                    confirmButtonText: 'OK'
                }).then(function() { window.location.reload(); });
            },
            error: function(xhr) {
                let message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Status pemeriksaan gagal diperbarui.';
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    const firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    if (firstKey) message = xhr.responseJSON.errors[firstKey][0];
                }
                window.CvMakerDialog.fire({ icon: 'error', title: 'Gagal', text: message, confirmButtonText: 'OK' });
            },
            complete: function() {
                button.prop('disabled', false).html(originalHtml);
            }
        });
    });

    window.onCvMakerHrisUpdated = function() {
        return $.ajax({
            url: window.location.href,
            method: 'GET',
            dataType: 'html',
            cache: false
        }).then(function(html) {
            const documentFromResponse = new DOMParser().parseFromString(html, 'text/html');
            const refreshedPage = documentFromResponse.querySelector('.cv-compare-page');
            const currentPage = document.querySelector('.cv-compare-page');

            if (!refreshedPage || !currentPage) {
                return $.Deferred().reject().promise();
            }

            currentPage.replaceWith(refreshedPage);
        });
    };
</script>
@include('admin.cv-maker-compare.partials.update-scripts')
@endpush
