@extends('layouts.app')

@section('title', 'Bagan Struktur Organisasi')

@push('styles')
<style>
    .org-chart-shell { overflow: auto; min-height: 420px; padding: 24px; background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); }
    .org-tree, .org-tree ul { display: flex; justify-content: center; position: relative; margin: 0; padding: 24px 0 0; list-style: none; min-width: max-content; }
    .org-tree > li { padding-top: 0; }
    .org-tree li { position: relative; padding: 24px 10px 0; text-align: center; }
    .org-tree li::before, .org-tree li::after { content: ''; position: absolute; top: 0; right: 50%; width: 50%; height: 24px; border-top: 2px solid #cbd5e1; }
    .org-tree li::after { left: 50%; right: auto; border-left: 2px solid #cbd5e1; }
    .org-tree li:only-child::before, .org-tree li:only-child::after { display: none; }
    .org-tree li:first-child::before, .org-tree li:last-child::after { border: 0; }
    .org-tree li:last-child::before { border-right: 2px solid #cbd5e1; border-radius: 0 8px 0 0; }
    .org-tree li:first-child::after { border-radius: 8px 0 0 0; }
    .org-tree ul::before { content: ''; position: absolute; top: 0; left: 50%; height: 24px; border-left: 2px solid #cbd5e1; }
    .org-card { width: 260px; min-height: 140px; margin: 0 auto; border: 1px solid #dbe4ef; border-top: 5px solid var(--org-level-color, #1572e8); border-radius: 12px; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, .08); text-align: left; overflow: hidden; }
    .org-card__body { padding: 14px; }
    .org-card__title { font-weight: 700; line-height: 1.3; color: #172b4d; }
    .org-card__meta { color: #64748b; font-size: .78rem; margin-top: 4px; }
    .org-card__employee { margin-top: 10px; padding: 8px 10px; border-radius: 8px; background: #f1f5f9; font-size: .82rem; }
    .org-card__vacant { background: #fff7ed; color: #9a3412; border: 1px dashed #fdba74; }
    .org-level-8, .org-level-7 { --org-level-color: #7c3aed; }
    .org-level-6, .org-level-5 { --org-level-color: #2563eb; }
    .org-level-4, .org-level-3 { --org-level-color: #0891b2; }
    .org-level-2, .org-level-1 { --org-level-color: #16a34a; }
    @media (max-width: 768px) {
        .org-chart-shell { padding: 12px; }
        .org-tree, .org-tree ul { display: block; min-width: 0; padding: 0; }
        .org-tree li { padding: 0 0 12px 18px; text-align: left; }
        .org-tree li::before, .org-tree li::after, .org-tree ul::before { display: none; }
        .org-tree ul { margin-left: 18px; border-left: 2px solid #cbd5e1; }
        .org-card { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 pt-2 pb-4">
            <div>
                <h3 class="fw-bold mb-1">Bagan Struktur Organisasi</h3>
                <p class="text-muted mb-0">Hubungan bagan berasal dari atasan struktural pada master posisi, bukan hanya urutan level.</p>
            </div>
            @if(auth()->user()->hasRole(['Super Admin', 'HR']))
            <a href="{{ route('organization-structure.index', ['departemen_id' => $selectedDepartmentId]) }}" class="btn btn-outline-primary"><i class="fas fa-cog me-1"></i> Kelola Master</a>
            @endif
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('organization-structure.chart') }}" class="row g-3 align-items-end">
                    <div class="col-lg-8">
                        <label class="form-label">Departemen</label>
                        <select name="departemen_id" class="form-select" required>
                            <option value="">Pilih departemen</option>
                            @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ (string) $selectedDepartmentId === (string) $department->id ? 'selected' : '' }}>
                                {{ optional($department->perusahaan)->kode_perusahaan }} — {{ $department->departemen }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4"><button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Tampilkan Struktur</button></div>
                </form>
            </div>
        </div>

        @if($selectedDepartment)
        <div class="card">
            <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
                <div><h5 class="mb-1">{{ $selectedDepartment->departemen }}</h5><small class="text-muted">{{ optional($selectedDepartment->perusahaan)->nama_perusahaan }}</small></div>
                <div class="d-flex flex-wrap gap-2"><span class="badge bg-light text-dark border">Level dapat diubah melalui master VPeople</span><span class="badge bg-light text-dark border">Posisi kosong tetap ditampilkan</span></div>
            </div>
            <div class="card-body p-0">
                @if($chartRoots->isEmpty())
                <div class="text-center py-5 px-3">
                    <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                    <h5>Struktur belum disusun</h5>
                    <p class="text-muted mb-3">Tambahkan posisi organisasi dan tentukan atasan struktural untuk departemen ini.</p>
                    @if(auth()->user()->hasRole(['Super Admin', 'HR']))<a href="{{ route('organization-structure.index', ['departemen_id' => $selectedDepartmentId, 'section' => 'positions']) }}#positions" class="btn btn-primary">Susun Posisi</a>@endif
                </div>
                @else
                <div class="org-chart-shell" aria-label="Bagan organisasi {{ $selectedDepartment->departemen }}">
                    <ul class="org-tree">
                        @foreach($chartRoots as $position)
                            @include('admin.organization-structure.partials.chart-node', ['position' => $position])
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>
        @else
        <div class="alert alert-warning">Tidak ada departemen yang tersedia dalam scope akses Anda.</div>
        @endif
    </div>
</div>
@endsection
