@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="text-primary mb-1">{{ __('self_service.attendance_correction.index_title') }}</h3>
                <small class="text-muted">{{ __('self_service.attendance_correction.index_subtitle') }}</small>
            </div>
            <a href="{{ route('attendance-corrections.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> {{ __('self_service.actions.apply_attendance_correction') }}
            </a>
        </div>

        @if(!$isTableReady)
        <div class="alert alert-warning">
            {!! __('self_service.attendance_correction.feature_inactive', ['command' => '<code>php artisan migrate</code>']) !!}
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('tables.date') }}</th>
                                <th>{{ __('tables.requested_correction') }}</th>
                                <th>{{ __('tables.status') }}</th>
                                <th>{{ __('tables.delegation') }}</th>
                                <th>{{ __('tables.hod') }}</th>
                                <th>{{ __('tables.hr') }}</th>
                                <th>{{ __('tables.attachment') }}</th>
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
                                    <span class="badge {{ $correction->delegate_status === null ? 'bg-secondary' : \App\Models\AttendanceCorrection::statusBadgeClass($correction->delegate_status) }}">{{ $correction->delegate_status_label }}</span>
                                    @if($correction->delegateProcessor)
                                    <small class="d-block text-muted mt-1">{{ $correction->delegateProcessor->name }}</small>
                                    @endif
                                    @if($correction->delegate_rejection_reason)
                                    <small class="d-block text-danger mt-1">{{ $correction->delegate_rejection_reason }}</small>
                                    @endif
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
                                        <i class="fas fa-paperclip me-1"></i> {{ __('self_service.actions.view') }}
                                    </a>
                                    @else
                                    <span class="text-muted small">{{ __('self_service.common.not_available') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ __('self_service.attendance_correction.empty') }}
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
