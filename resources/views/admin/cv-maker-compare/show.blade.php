@extends('layouts.app')

@section('title', 'Detail Compare CV Maker')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/admin-cv-maker-compare.css') }}">
@endpush

@section('content')
@php
$groupLabels = [
    'identity' => 'Identitas',
    'work' => 'Organisasi',
    'location' => 'Wilayah',
    'education' => 'Pendidikan',
];
$cvProfile = $detail['cv_profile'] ?? null;
$vitae = $detail['vitae'] ?? [];
$comparison = $detail['comparison'] ?? ['groups' => [], 'mismatch_count' => 0, 'compared_count' => 0];
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
$cvPersonalMeta = collect([
    ['value' => 'NIK: ' . $employee->nik, 'keys' => []],
    ['value' => $profile['birth'] ?? null, 'keys' => ['birth_date']],
    ['value' => $profile['gender'] ?? null, 'keys' => ['gender']],
    ['value' => $profile['marital_status'] ?? null, 'keys' => ['marital_status']],
])->filter(fn($item) => $hasCvValue($item['value']))->values();
$cvAddressLines = collect([
    $profile['address'] ?? null,
    $profile['location'] ?? null,
])->filter($hasCvValue)->values();
$cvContactMeta = collect([
    ['value' => $profile['phone'] ?? null, 'keys' => ['phone']],
    ['value' => $profile['email'] ?? null, 'keys' => []],
])->filter(fn($item) => $hasCvValue($item['value']))->values();
$hasSkills = !empty($profile['technical_skills']) || !empty($profile['non_technical_skills']);
$hasAdditional = !empty($vitae['projects']) || !empty($vitae['organizations']) || !empty($vitae['languages']);
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

                            <div class="cv-vitae-meta-line">
                                @foreach($cvPersonalMeta as $meta)
                                <span class="{{ $isMismatch($meta['keys']) ? 'cv-vitae-field--mismatch' : '' }}">
                                    {{ $meta['value'] }}
                                </span>
                                @endforeach
                            </div>

                            @if($hasCvValue($profile['organization'] ?? null))
                            <div class="cv-vitae-meta-line cv-vitae-meta-line--work{{ $isMismatch(['work_area', 'department', 'division', 'position']) ? ' cv-vitae-field--mismatch' : '' }}">
                                {{ $cvText($profile['organization'] ?? null) }}
                                @if($hasCvValue($profile['position'] ?? null))
                                <span>{{ $cvText($profile['position'] ?? null) }}</span>
                                @endif
                            </div>
                            @endif

                            @if($cvAddressLines->isNotEmpty())
                            <div class="cv-vitae-address{{ $isMismatch(['address', 'province', 'regency', 'district', 'village']) ? ' cv-vitae-field--mismatch' : '' }}">
                                @foreach($cvAddressLines as $line)
                                <div>{{ $line }}</div>
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

                    @if(!empty($vitae['experiences']))
                    <section class="cv-vitae-section">
                        <h3>Pengalaman Kerja</h3>
                        @foreach($vitae['experiences'] as $experience)
                        <div class="cv-vitae-item">
                            <h4>{{ $cvText($experience['title'] ?? null) }}</h4>
                            <div class="cv-vitae-item__meta">
                                @foreach(collect([$experience['company'] ?? null, $experience['department'] ?? null, $experience['period'] ?? null])->filter($hasCvValue)->values() as $meta)
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

        <section class="ui-panel cv-detail-compare-panel">
            <div class="ui-panel__header">
                <div>
                    <h5 class="ui-panel__title">Perbandingan Field</h5>
                    <p class="ui-panel__meta">Nilai merah berarti HRIS dan CV Maker sama-sama tersedia tetapi berbeda.</p>
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
                                    <span title="HRIS">{!! $item['hris'] !!}</span>
                                    <span title="CV Maker">{!! $item['cv'] !!}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</div>

@include('admin.cv-maker-compare.partials.update-modal')
@endsection

@push('scripts')
<script>
    window.onCvMakerHrisUpdated = function() {
        window.location.reload();
    };
</script>
@include('admin.cv-maker-compare.partials.update-scripts')
@endpush
