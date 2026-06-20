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
$cvName = $vitae['profile']['name'] ?? ($employee->nama_karyawan ?: $employee->nik);
$cvInitials = collect(explode(' ', trim((string) $cvName)))
    ->filter()
    ->take(2)
    ->map(fn($part) => mb_substr($part, 0, 1))
    ->implode('');
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
                    <aside class="cv-vitae-sidebar">
                        <div class="cv-vitae-avatar" aria-hidden="true">{{ $cvInitials ?: 'CV' }}</div>
                        <h2>{{ $vitae['profile']['name'] ?: '-' }}</h2>
                        <div class="cv-vitae-position">{{ $vitae['profile']['position'] ?: '-' }}</div>

                        <div class="cv-vitae-side-section">
                            <h3>Kontak</h3>
                            <dl>
                                <dt>No. HP</dt>
                                <dd>{{ $vitae['profile']['phone'] ?: '-' }}</dd>
                                <dt>Email</dt>
                                <dd>{{ $vitae['profile']['email'] ?: '-' }}</dd>
                                <dt>Alamat</dt>
                                <dd>{{ $vitae['profile']['address'] ?: '-' }}</dd>
                                <dt>Wilayah</dt>
                                <dd>{{ $vitae['profile']['location'] ?: '-' }}</dd>
                            </dl>
                        </div>

                        <div class="cv-vitae-side-section">
                            <h3>Data Pribadi</h3>
                            <dl>
                                <dt>TTL</dt>
                                <dd>{{ $vitae['profile']['birth'] ?: '-' }}</dd>
                                <dt>Gender</dt>
                                <dd>{{ $vitae['profile']['gender'] ?: '-' }}</dd>
                                <dt>Status</dt>
                                <dd>{{ $vitae['profile']['marital_status'] ?: '-' }}</dd>
                                <dt>Organisasi Kerja</dt>
                                <dd>{{ $vitae['profile']['organization'] ?: '-' }}</dd>
                            </dl>
                        </div>

                        @if(!empty($vitae['profile']['technical_skills']) || !empty($vitae['profile']['non_technical_skills']))
                        <div class="cv-vitae-side-section">
                            <h3>Skill</h3>
                            @if(!empty($vitae['profile']['technical_skills']))
                            <div class="cv-vitae-skill-title">Teknis</div>
                            <div class="cv-vitae-tags">
                                @foreach($vitae['profile']['technical_skills'] as $skill)
                                <span>{{ $skill }}</span>
                                @endforeach
                            </div>
                            @endif
                            @if(!empty($vitae['profile']['non_technical_skills']))
                            <div class="cv-vitae-skill-title">Non Teknis</div>
                            <div class="cv-vitae-tags">
                                @foreach($vitae['profile']['non_technical_skills'] as $skill)
                                <span>{{ $skill }}</span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endif

                        @if(!empty($vitae['languages']))
                        <div class="cv-vitae-side-section">
                            <h3>Bahasa</h3>
                            <ul class="cv-vitae-compact-list">
                                @foreach($vitae['languages'] as $language)
                                <li>
                                    <span>{{ $language['language'] ?: '-' }}</span>
                                    <small>{{ $language['level'] ?: '-' }}</small>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </aside>

                    <div class="cv-vitae-main">
                        @if(!empty($vitae['profile']['summary']))
                        <section class="cv-vitae-section">
                            <h3>Ringkasan Profil</h3>
                            <p>{!! nl2br(e($vitae['profile']['summary'])) !!}</p>
                        </section>
                        @endif

                        @if(!empty($vitae['experiences']))
                        <section class="cv-vitae-section">
                            <h3>Pengalaman Kerja</h3>
                            @foreach($vitae['experiences'] as $experience)
                            <div class="cv-vitae-item">
                                <div class="cv-vitae-item__head">
                                    <div>
                                        <h4>{{ $experience['title'] ?: '-' }}</h4>
                                        <span>{{ $experience['company'] ?: '-' }}</span>
                                    </div>
                                    <small>{{ $experience['period'] ?: '-' }}</small>
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
                        <section class="cv-vitae-section">
                            <h3>Pendidikan</h3>
                            @foreach($vitae['educations'] as $education)
                            <div class="cv-vitae-item">
                                <div class="cv-vitae-item__head">
                                    <div>
                                        <h4>{{ $education['title'] ?: '-' }}</h4>
                                        <span>{{ $education['institution'] ?: '-' }}</span>
                                    </div>
                                    <small>{{ $education['year'] ?: '-' }}</small>
                                </div>
                            </div>
                            @endforeach
                        </section>
                        @endif

                        <div class="cv-vitae-two-col">
                            @if(!empty($vitae['certifications']))
                            <section class="cv-vitae-section">
                                <h3>Sertifikasi</h3>
                                @foreach($vitae['certifications'] as $certification)
                                <div class="cv-vitae-item cv-vitae-item--compact">
                                    <h4>{{ $certification['title'] ?: '-' }}</h4>
                                    <span>{{ $certification['issuer'] ?: '-' }}</span>
                                    <small>{{ $certification['period'] ?: '-' }}</small>
                                </div>
                                @endforeach
                            </section>
                            @endif

                            @if(!empty($vitae['organizations']))
                            <section class="cv-vitae-section">
                                <h3>Organisasi</h3>
                                @foreach($vitae['organizations'] as $organization)
                                <div class="cv-vitae-item cv-vitae-item--compact">
                                    <h4>{{ $organization['title'] ?: '-' }}</h4>
                                    <span>{{ $organization['role'] ?: '-' }}</span>
                                    <small>{{ $organization['period'] ?: '-' }}</small>
                                </div>
                                @endforeach
                            </section>
                            @endif

                            @if(!empty($vitae['projects']))
                            <section class="cv-vitae-section">
                                <h3>Proyek</h3>
                                @foreach($vitae['projects'] as $project)
                                <div class="cv-vitae-item cv-vitae-item--compact">
                                    <h4>{{ $project['name'] ?: '-' }}</h4>
                                    <small>{{ $project['year'] ?: '-' }}</small>
                                </div>
                                @endforeach
                            </section>
                            @endif
                        </div>
                    </div>
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
