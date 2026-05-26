@extends('layouts.app')

@section('title', 'Perintah Lembur')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Perintah Lembur</h4>
                <small class="text-muted">Lihat, setujui, atau tolak perintah lembur yang dikirim kepada Anda.</small>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('tables.date') }}</th>
                                <th>{{ __('tables.type') }}</th>
                                <th>{{ __('tables.hour') }}</th>
                                <th>{{ __('tables.reason') }}</th>
                                <th>{{ __('tables.response') }}</th>
                                <th>{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overtimeOrders as $overtimeOrder)
                                <tr>
                                    <td>{{ $overtimeOrder->overtime_date->translatedFormat('d M Y') }}</td>
                                    <td>{{ $overtimeOrder->type_label }}</td>
                                    <td>{{ $overtimeOrder->time_range_text }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $overtimeOrder->reason }}</div>
                                        @if($overtimeOrder->instruction_notes)
                                            <div class="small text-muted mt-1">{{ $overtimeOrder->instruction_notes }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $overtimeOrder->response_badge_class }}">
                                            {{ $overtimeOrder->response_label }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($overtimeOrder->employee_response_status === \App\Models\OvertimeOrder::RESPONSE_PENDING && !$overtimeOrder->isPastDate())
                                            <form action="{{ route('lembur.respond', $overtimeOrder->id) }}" method="POST" class="d-flex flex-column gap-2">
                                                @csrf
                                                <textarea name="employee_response_notes" rows="2" class="form-control" placeholder="Catatan opsional untuk HOD/Admin Divisi"></textarea>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="response" value="{{ \App\Models\OvertimeOrder::RESPONSE_ACCEPTED }}" class="btn btn-success">
                                                        Setuju
                                                    </button>
                                                    <button type="submit" name="response" value="{{ \App\Models\OvertimeOrder::RESPONSE_REJECTED }}" class="btn btn-danger">
                                                        Tolak
                                                    </button>
                                                </div>
                                            </form>
                                        @else
                                            @if($overtimeOrder->employee_response_status === \App\Models\OvertimeOrder::RESPONSE_PENDING && $overtimeOrder->isPastDate())
                                                <div class="small text-muted mb-1">Perintah lembur sudah melewati tanggal pelaksanaan.</div>
                                            @endif
                                            <div class="small text-muted">
                                                Direspons {{ optional($overtimeOrder->employee_response_at)->translatedFormat('d M Y H:i') ?: '-' }}
                                            </div>
                                            <div class="small">{{ $overtimeOrder->employee_response_notes ?: 'Tanpa catatan tambahan.' }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada perintah lembur untuk Anda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
