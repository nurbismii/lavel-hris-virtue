@extends('layouts.app')

@section('title', __('self_service.roster.schedule_history_title'))

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-history text-primary me-2"></i>
                    {{ __('self_service.roster.schedule_history_title') }}
                </h4>
                <small class="text-muted">{{ __('self_service.roster.schedule_history_subtitle') }}</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('roster.index') }}" class="btn btn-light border">
                    <i class="fas fa-arrow-left me-1"></i>
                    {{ __('self_service.roster.schedule_history_back') }}
                </a>
            </div>
        </div>

        <div class="alert alert-info border-0 shadow-sm">
            <i class="fas fa-info-circle me-1"></i>
            {{ __('self_service.roster.schedule_history_info') }}
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 pt-3 px-3 px-md-4">
                <h5 class="fw-bold mb-1">{{ __('self_service.roster.upcoming_schedule_title') }}</h5>
                <small class="text-muted">{{ __('self_service.roster.upcoming_schedule_help') }}</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('self_service.roster.schedule_history_period') }}</th>
                                <th>{{ __('self_service.roster.upcoming_schedule_work_period') }}</th>
                                <th>{{ __('self_service.roster.upcoming_schedule_off_period') }}</th>
                                <th>{{ __('self_service.roster.upcoming_schedule_realization') }}</th>
                                <th class="text-end">{{ __('self_service.roster.upcoming_schedule_action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($upcomingSchedules as $schedule)
                            @php
                                $realizationClass = $schedule->realization_type === 'cuti_roster'
                                    ? 'success'
                                    : ($schedule->realization_type === 'insentif' ? 'info' : 'warning text-dark');
                                $canApply = $schedule->realization_type === \App\Models\RosterSchedule::REALIZATION_PENDING
                                    && !$schedule->manual_submitted_at
                                    && !$schedule->has_active_application;
                            @endphp
                            <tr>
                                <td><span class="badge bg-primary">{{ $schedule->period_label }}</span></td>
                                <td>
                                    {{ optional($schedule->work_start)->format('d M Y') ?: '-' }}
                                    @if($schedule->work_end)
                                    <br><small class="text-muted">s.d. {{ $schedule->work_end->format('d M Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ optional($schedule->off_start)->format('d M Y') ?: '-' }}
                                    @if($schedule->off_end)
                                    <br><small class="text-muted">s.d. {{ $schedule->off_end->format('d M Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $realizationClass }}">{{ $schedule->realization_label }}</span>
                                    @if($schedule->has_active_application)
                                    <div><small class="text-muted">{{ __('self_service.roster.upcoming_schedule_in_process') }}</small></div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($canApply)
                                    <a href="{{ route('roster.create', ['roster_schedule' => $schedule->id]) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-paper-plane me-1"></i>
                                        {{ __('self_service.roster.upcoming_schedule_apply') }}
                                    </a>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    {{ __('self_service.roster.upcoming_schedule_empty') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('roster.history') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="history-year" class="form-label">{{ __('self_service.roster.schedule_history_filter_year') }}</label>
                        <select id="history-year" name="year" class="form-select">
                            <option value="">{{ __('self_service.roster.schedule_history_all_years') }}</option>
                            @foreach($yearOptions as $year)
                            <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="history-classification" class="form-label">{{ __('self_service.roster.schedule_history_filter_classification') }}</label>
                        <select id="history-classification" name="classification" class="form-select">
                            <option value="">{{ __('self_service.roster.schedule_history_all_classifications') }}</option>
                            @foreach($classificationOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['classification'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-filter me-1"></i> {{ __('self_service.roster.schedule_history_filter_action') }}
                        </button>
                        <a href="{{ route('roster.history') }}" class="btn btn-light border">{{ __('self_service.roster.schedule_history_reset_action') }}</a>
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
                                <th>{{ __('self_service.roster.schedule_history_period') }}</th>
                                <th>{{ __('self_service.roster.schedule_history_off_date') }}</th>
                                <th>{{ __('self_service.roster.schedule_history_classification') }}</th>
                                <th>{{ __('self_service.roster.schedule_history_note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($histories as $history)
                            @php
                                $badgeClass = match($history->classification) {
                                    'cuti_roster' => 'success',
                                    'insentif' => 'info',
                                    'not_applicable' => 'secondary',
                                    'need_review' => 'warning',
                                    default => 'primary',
                                };
                            @endphp
                            <tr>
                                <td><span class="badge bg-primary">{{ $history->period_label }}</span></td>
                                <td>
                                    {{ optional($history->scheduled_off_start)->format('d M Y') ?: '-' }}
                                    @if($history->scheduled_off_end)
                                    <br><small class="text-muted">s.d. {{ $history->scheduled_off_end->format('d M Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $badgeClass }}">{{ $history->classification_label }}</span>
                                    @if($history->review_status === 'pending')
                                    <div><span class="badge bg-warning text-dark mt-1">{{ __('self_service.roster.schedule_history_reviewing') }}</span></div>
                                    @elseif($history->review_status === 'confirmed')
                                    <div><span class="badge bg-success mt-1">{{ __('self_service.roster.schedule_history_confirmed') }}</span></div>
                                    @endif
                                </td>
                                <td style="min-width: 220px">{{ $history->remark_segment ?: '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fas fa-calendar-times fa-2x mb-2 d-block"></i>
                                    {{ __('self_service.roster.schedule_history_empty') }}
                                </td>
                            </tr>
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
