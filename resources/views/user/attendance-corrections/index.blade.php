@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="text-primary mb-1">Pengajuan Presensi</h3>
                <small class="text-muted">Riwayat koreksi jam/status dan izin presensi parsial Anda.</small>
            </div>
            <a href="{{ route('attendance-corrections.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Ajukan Koreksi
            </a>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur pengajuan presensi belum aktif lengkap. Jalankan <code>php artisan migrate</code> terlebih dahulu.
            </div>
        @else
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Koreksi Diminta</th>
                                    <th>Status</th>
                                    <th>HOD</th>
                                    <th>HR</th>
                                    <th>Lampiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($corrections as $correction)
                                    <tr>
                                        <td>
                                            <strong>{{ formatDateIndonesia($correction->tanggal) }}</strong>
                                            <small class="d-block text-muted">{{ $correction->created_at->format('d/m/Y H:i') }}</small>
                                        </td>
                                        <td>
                                            @foreach($correction->requestedChanges() as $label => $value)
                                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $label }}: {{ $value }}</span>
                                            @endforeach
                                            <small class="d-block text-muted mt-1">{{ \Illuminate\Support\Str::limit($correction->reason, 100) }}</small>
                                        </td>
                                        <td>
                                            <span class="badge {{ $correction->overall_badge_class }}">{{ $correction->overall_status_label }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ \App\Models\AttendanceCorrection::statusBadgeClass($correction->status_hod) }}">{{ $correction->hod_status_label }}</span>
                                            @if($correction->hodProcessor)
                                                <small class="d-block text-muted mt-1">{{ $correction->hodProcessor->name }}</small>
                                            @endif
                                            @if($correction->hod_rejection_reason)
                                                <small class="d-block text-danger mt-1">{{ $correction->hod_rejection_reason }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ \App\Models\AttendanceCorrection::statusBadgeClass($correction->status_hrd) }}">{{ $correction->hrd_status_label }}</span>
                                            @if($correction->hrdProcessor)
                                                <small class="d-block text-muted mt-1">{{ $correction->hrdProcessor->name }}</small>
                                            @endif
                                            @if($correction->hrd_rejection_reason)
                                                <small class="d-block text-danger mt-1">{{ $correction->hrd_rejection_reason }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if($correction->attachment_path)
                                                <a href="{{ route('attendance-corrections.attachment', $correction->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-paperclip me-1"></i> Lihat
                                                </a>
                                            @else
                                                <span class="text-muted small">Tidak ada</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Belum ada pengajuan koreksi presensi.
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
