@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary mb-1">Approval HR Koreksi Presensi</h3>
                <small class="text-muted">Approval final HR sekaligus penerapan koreksi ke data presensi.</small>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur koreksi presensi belum aktif karena tabel <code>attendance_corrections</code> belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
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
                                    <th>Status HR</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($corrections as $correction)
                                    @php
                                        $hrdStatus = (int) $correction->status_hrd;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ optional($correction->employee)->nama_karyawan ?: '-' }}</strong>
                                            <small class="d-block text-muted">{{ $correction->nik_karyawan }}</small>
                                        </td>
                                        <td>
                                            {{ formatDateIndonesia($correction->tanggal) }}
                                            <small class="d-block text-muted">HOD: {{ $correction->hod_processed_at ? $correction->hod_processed_at->format('d/m/Y H:i') : '-' }}</small>
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
                                            <span class="badge {{ \App\Models\AttendanceCorrection::statusBadgeClass($correction->status_hrd) }}">{{ $correction->hrd_status_label }}</span>
                                            @if($correction->hrd_rejection_reason)
                                                <small class="d-block text-danger mt-1">{{ $correction->hrd_rejection_reason }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($hrdStatus === \App\Models\AttendanceCorrection::STATUS_PENDING)
                                                <form action="{{ route('approval.attendance-corrections.hrd.process', $correction->id) }}" method="POST" data-approval-confirm-message="Setujui dan terapkan koreksi ini ke data presensi?">
                                                    @csrf
                                                    <div class="d-flex gap-2">
                                                        <button name="action" value="1" class="btn btn-success btn-sm">
                                                            Approve HR
                                                        </button>
                                                        <button type="button" name="action" value="2" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                                            Reject HR
                                                        </button>
                                                    </div>
                                                </form>
                                            @elseif($hrdStatus === \App\Models\AttendanceCorrection::STATUS_APPROVED)
                                                <span class="badge bg-success">Sudah diterapkan</span>
                                                <small class="d-block text-muted mt-1">{{ $correction->applied_at ? $correction->applied_at->format('d/m/Y H:i') : '-' }}</small>
                                            @else
                                                <span class="badge bg-danger">Ditolak HR</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Tidak ada koreksi presensi yang menunggu approval HR.
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
