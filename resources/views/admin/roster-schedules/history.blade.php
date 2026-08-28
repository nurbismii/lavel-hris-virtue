@extends('layouts.app')

@section('title', 'Riwayat Roster Excel')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Riwayat Roster dari Excel</h4>
                <small class="text-muted">Arsip tanggal dan keterangan asli per tahun/periode. Data ini tidak berubah ketika jadwal aktif diedit.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('roster-schedules.index') }}" class="btn btn-light border">Kembali ke Jadwal</a>
            </div>
        </div>

        <div class="alert alert-warning">
            Label <strong>Perlu Review</strong> berarti keterangan Excel tidak menyebut “Cuti” atau “Insentif” secara tegas. HR perlu mengonfirmasi berdasarkan dokumen pendukung.
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">Cari karyawan</label>
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Nama atau NIK">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label">Tahun</label>
                        <select name="year" class="form-select">
                            <option value="">Semua tahun</option>
                            @foreach($yearOptions as $year)
                            <option value="{{ $year }}" @selected((string)($filters['year'] ?? '') === (string)$year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label">Klasifikasi</label>
                        <select name="classification" class="form-select">
                            <option value="">Semua</option>
                            @foreach($classificationOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['classification'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label class="form-label">Review</label>
                        <select name="review_status" class="form-select">
                            <option value="">Semua</option>
                            <option value="pending" @selected(($filters['review_status'] ?? '') === 'pending')>Perlu Review</option>
                            <option value="confirmed" @selected(($filters['review_status'] ?? '') === 'confirmed')>Terkonfirmasi</option>
                            <option value="not_required" @selected(($filters['review_status'] ?? '') === 'not_required')>Tidak diperlukan</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary flex-fill">Filter</button>
                        <a href="{{ route('roster-schedules.history') }}" class="btn btn-light border">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Karyawan</th>
                                <th>Periode</th>
                                <th>Jadwal Off</th>
                                <th>Klasifikasi</th>
                                <th>Keterangan Periode</th>
                                <th>Sumber</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($histories as $history)
                            @php
                                $badgeClass = match($history->classification) {
                                    'cuti_roster' => 'success',
                                    'insentif' => 'info',
                                    'not_applicable' => 'secondary',
                                    'need_review' => 'danger',
                                    default => 'primary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ optional($history->employee)->nama_karyawan ?: 'Karyawan tidak ditemukan' }}</div>
                                    <small class="text-muted">{{ $history->employee_nik }}</small>
                                </td>
                                <td><span class="badge bg-primary">{{ $history->period_label }}</span></td>
                                <td>{{ $history->scheduled_off_start->format('d M Y') }}<br><small class="text-muted">s.d. {{ $history->scheduled_off_end->format('d M Y') }}</small></td>
                                <td>
                                    <span class="badge bg-{{ $badgeClass }}">{{ $history->classification_label }}</span>
                                    @if($history->review_status === 'pending')
                                    <div><span class="badge bg-warning text-dark mt-1">Perlu Review</span></div>
                                    @elseif($history->review_status === 'confirmed')
                                    <div><span class="badge bg-success mt-1">Dikonfirmasi HR</span></div>
                                    @endif
                                </td>
                                <td style="min-width:260px;max-width:420px">
                                    <div title="{{ $history->raw_remark }}">{{ $history->remark_segment ?: '-' }}</div>
                                    @if($history->review_note)
                                    <small class="text-muted">Review: {{ $history->review_note }}</small>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $history->source_file }}</small><br>
                                    <small class="text-muted">{{ $history->source_sheet }}!{{ $history->source_column }}{{ $history->source_row }}</small>
                                    @if($history->source_remark_column)
                                    <br><small class="text-muted">Remark: {{ $history->source_remark_column }}{{ $history->source_row }}</small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('roster-schedules.history.review', $history) }}" class="btn btn-sm btn-outline-primary">Review</a>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada riwayat roster sesuai filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($histories->hasPages())
            <div class="card-footer bg-white">{{ $histories->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
