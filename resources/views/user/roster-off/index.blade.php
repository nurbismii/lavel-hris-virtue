@extends('layouts.app')

@push('styles')
<style>
    .roster-off-page {
        --off-primary: #2563eb;
        --off-soft: #f8fafc;
        --off-border: #e5e7eb;
        --off-dark: #0f172a;
        --off-muted: #64748b;
        --off-radius: 16px;
    }

    .roster-off-hero,
    .roster-off-card {
        background: #ffffff;
        border: 1px solid var(--off-border);
        border-radius: var(--off-radius);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
    }

    .roster-off-hero {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    }

    .roster-off-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 8px 12px;
        background: #eff6ff;
        color: var(--off-primary);
        font-weight: 700;
        font-size: 12px;
    }

    .roster-off-info {
        background: var(--off-soft);
        border-radius: 14px;
        padding: 14px;
        height: 100%;
    }

    .roster-off-info small {
        color: var(--off-muted);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
    }

    .roster-off-info strong {
        display: block;
        color: var(--off-dark);
        margin-top: 4px;
    }

    .roster-off-table th,
    .roster-off-table td {
        vertical-align: middle;
    }

    @media (max-width: 767.98px) {
        .roster-off-hero,
        .roster-off-card {
            border-radius: 14px;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner roster-off-page">
        <div class="roster-off-hero p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
                <div>
                    <span class="roster-off-chip mb-3">
                        <i class="fas fa-calendar-day"></i>
                        Khusus Karyawan Roster
                    </span>
                    <h4 class="fw-bold mb-1">Pengajuan OFF Roster</h4>
                    <p class="text-muted mb-0">
                        Ajukan hari OFF, Setelah disetujui HOD dan belum ditolak HR, tanggal OFF akan terdeteksi di jadwal roster mingguan dan status presensi.
                    </p>
                </div>

                <div class="ms-lg-auto">
                    <a href="{{ route('roster.index') }}" class="btn btn-outline-primary">
                        <i class="fas fa-plane-departure me-1"></i>
                        Data Cuti Roster
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="roster-off-info">
                    <small>Nama Karyawan</small>
                    <strong>{{ optional($employee)->nama_karyawan ?? auth()->user()->name }}</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="roster-off-info">
                    <small>NIK</small>
                    <strong>{{ auth()->user()->nik_karyawan }}</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="roster-off-info">
                    <small>Total Riwayat OFF</small>
                    <strong>{{ number_format($offRequests->count()) }} Pengajuan</strong>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="roster-off-card p-3 p-md-4">
                    <h5 class="fw-bold mb-1">Ajukan OFF</h5>
                    <p class="text-muted small mb-3">Tanggal OFF tidak boleh sebelum hari ini dan tidak boleh duplikat dengan pengajuan yang masih aktif.</p>

                    <form action="{{ route('roster-off.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Tanggal OFF</label>
                            <input type="date" name="tanggal_off" class="form-control @error('tanggal_off') is-invalid @enderror" value="{{ old('tanggal_off') }}" required>
                            @error('tanggal_off')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alasan</label>
                            <textarea name="alasan" rows="4" maxlength="1000" class="form-control @error('alasan') is-invalid @enderror" placeholder="Contoh: pengganti hari libur roster">{{ old('alasan') }}</textarea>
                            @error('alasan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-1"></i>
                            Kirim Pengajuan
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="roster-off-card p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Riwayat OFF</h5>
                            <p class="text-muted small mb-0">Daftar semua pengajuan OFF roster milik karyawan ini.</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm roster-off-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal OFF</th>
                                    <th>Alasan</th>
                                    <th>Status HOD</th>
                                    <th>Status HR</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($offRequests as $offRequest)
                                <tr>
                                    <td class="fw-semibold">{{ formatDateIndonesia($offRequest->tanggal_off) }}</td>
                                    <td>{{ $offRequest->alasan ?: '-' }}</td>
                                    <td>{!! $offRequest->status_hod_label !!}</td>
                                    <td>{!! $offRequest->status_hrd_label !!}</td>
                                    <td>
                                        @if($offRequest->can_be_managed_by_employee)
                                            <form action="{{ route('roster-off.destroy', $offRequest) }}" method="POST" onsubmit="return confirm('Hapus pengajuan OFF ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash me-1"></i>
                                                    Hapus
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted small">Terkunci</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Belum ada riwayat pengajuan OFF roster.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
