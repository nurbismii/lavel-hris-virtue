@extends('layouts.app')

@section('title', __('self_service.contract.index_title'))

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">{{ __('self_service.contract.index_title') }}</h4>
                <small class="text-muted">{{ __('self_service.contract.index_subtitle') }}</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('tables.type') }}</th>
                                <th>{{ __('tables.number') }}</th>
                                <th>{{ __('tables.period') }}</th>
                                <th>{{ __('tables.status') }}</th>
                                <th>{{ __('tables.created') }}</th>
                                <th style="width: 120px;">{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contracts as $contract)
                                <tr>
                                    <td>{{ $contract->type_label }}</td>
                                    <td>
                                        <div>{{ $contract->display_number }}</div>
                                        <small class="text-muted">PKWT: {{ $contract->pkwt_number }}</small>
                                    </td>
                                    <td>
                                        {{ optional($contract->contract_start_date)->format('d M Y') ?: '-' }}
                                        {{ __('self_service.common.range_separator') }}
                                        {{ optional($contract->contract_end_date)->format('d M Y') ?: '-' }}
                                    </td>
                                    <td>
                                        @php
                                            $badge = [
                                                'ready' => 'warning',
                                                'signed' => 'success',
                                                'cancelled' => 'secondary',
                                            ][$contract->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $badge }}">{{ $contract->status_label }}</span>
                                    </td>
                                    <td>{{ optional($contract->created_at)->format('d M Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('user-electronic-contracts.show', $contract) }}" class="btn btn-sm btn-primary">
                                            {{ __('self_service.actions.open') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        {{ __('self_service.contract.no_contracts') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $contracts->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
