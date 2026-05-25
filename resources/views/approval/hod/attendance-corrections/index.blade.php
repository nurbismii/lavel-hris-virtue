@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary mb-1">Approval Koreksi Presensi</h3>
                <small class="text-muted">Review HOD untuk koreksi presensi dan izin presensi parsial karyawan.</small>
            </div>
        </div>

        @if(!$isTableReady)
        <div class="alert alert-warning">
            Fitur Koreksi Presensi belum aktif lengkap. Jalankan <code>php artisan migrate</code> terlebih dahulu.
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Tanggal</th>
                                <th>Koreksi Diminta</th>
                                <th>Alasan</th>
                                <th>Delegasi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($corrections as $correction)
                            @php
                            $hodStatus = (int) $correction->status_hod;
                            $hrdStatus = (int) $correction->status_hrd;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ optional($correction->employee)->nama_karyawan ?: '-' }}</strong>
                                    <small class="d-block text-muted">{{ $correction->nik_karyawan }}</small>
                                </td>
                                <td>
                                    {{ formatDateIndonesia($correction->tanggal) }}
                                    <small class="d-block text-muted">{{ $correction->created_at->format('d/m/Y H:i') }}</small>
                                </td>
                                <td>
                                    @foreach($correction->requestedChanges() as $label => $value)
                                    <span class="badge bg-light text-dark border me-1 mb-1">{{ $label }}: {{ $value }}</span>
                                    @endforeach
                                    @if($correction->attachment_path)
                                    <a href="{{ route('attendance-corrections.attachment', $correction->id) }}" target="_blank" class="d-block small mt-1">
                                        <i class="fas fa-paperclip me-1"></i> Lihat lampiran
                                    </a>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($correction->reason, 120) }}</td>
                                <td>
                                    <span class="badge {{ $correction->delegate_status === null ? 'bg-secondary' : \App\Models\AttendanceCorrection::statusBadgeClass($correction->delegate_status) }}">
                                        {{ $correction->delegate_status_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $correction->overall_badge_class }}">{{ $correction->overall_status_label }}</span>
                                </td>
                                <td>
                                    @if($hodStatus === \App\Models\AttendanceCorrection::STATUS_PENDING)
                                    <form action="{{ route('approval.attendance-corrections.hod.process', $correction->id) }}" method="POST" data-approval-confirm-message="Setujui koreksi presensi ini dan teruskan ke HR?">
                                        @csrf
                                        <div class="d-flex gap-2">
                                            <button name="action" value="1" class="btn btn-success btn-sm">
                                                Approve HOD
                                            </button>
                                            <button type="button" name="action" value="2" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                                Reject HOD
                                            </button>
                                        </div>
                                    </form>
                                    @elseif($hodStatus === \App\Models\AttendanceCorrection::STATUS_APPROVED && $hrdStatus === \App\Models\AttendanceCorrection::STATUS_PENDING)
                                    <span class="badge bg-info">Menunggu HR</span>
                                    <small class="d-block text-muted mt-1">Disetujui HOD</small>
                                    @elseif($hodStatus === \App\Models\AttendanceCorrection::STATUS_APPROVED && $hrdStatus === \App\Models\AttendanceCorrection::STATUS_APPROVED)
                                    <span class="badge bg-success">Selesai</span>
                                    <small class="d-block text-muted mt-1">Sudah diterapkan HR</small>
                                    @elseif($hodStatus === \App\Models\AttendanceCorrection::STATUS_APPROVED && $hrdStatus === \App\Models\AttendanceCorrection::STATUS_REJECTED)
                                    <span class="badge bg-danger">Ditolak HR</span>
                                    @if($correction->hrd_rejection_reason)
                                    <small class="d-block text-danger mt-1">{{ $correction->hrd_rejection_reason }}</small>
                                    @endif
                                    @else
                                    <span class="badge bg-danger">Ditolak HOD</span>
                                    @if($correction->hod_rejection_reason)
                                    <small class="d-block text-danger mt-1">{{ $correction->hod_rejection_reason }}</small>
                                    @endif
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Tidak ada Koreksi Presensi dalam scope Anda.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $corrections->links() }}
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection