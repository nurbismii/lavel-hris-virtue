@extends('layouts.app')

@section('title', 'Perintah Lembur')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Perintah Lembur</h4>
                <small class="text-muted">Buat instruksi lembur untuk karyawan dan pantau responsnya.</small>
            </div>
            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('overtime-orders.create') }}" class="btn btn-primary">Buat Perintah</a>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Status Respons</label>
                        <select name="response_status" class="form-select">
                            <option value="">Semua Status</option>
                            @foreach ($responseOptions as $value => $label)
                                <option value="{{ $value }}" {{ request('response_status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('tables.date') }}</th>
                                <th>{{ __('tables.employee') }}</th>
                                <th>{{ __('tables.type') }}</th>
                                <th>{{ __('tables.hour') }}</th>
                                <th>{{ __('tables.response') }}</th>
                                <th>{{ __('tables.creator') }}</th>
                                <th>{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($overtimeOrders as $overtimeOrder)
                                <tr>
                                    <td>{{ $overtimeOrder->overtime_date->translatedFormat('d M Y') }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ optional($overtimeOrder->employee)->nama_karyawan ?? '-' }}</div>
                                        <div class="small text-muted">{{ $overtimeOrder->nik_karyawan }}</div>
                                    </td>
                                    <td>{{ $overtimeOrder->type_label }}</td>
                                    <td>{{ $overtimeOrder->time_range_text }}</td>
                                    <td>
                                        <span class="badge bg-{{ $overtimeOrder->response_badge_class }}">
                                            {{ $overtimeOrder->response_label }}
                                        </span>
                                    </td>
                                    <td>{{ optional($overtimeOrder->requester)->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('overtime-orders.show', $overtimeOrder->id) }}" class="btn btn-info">
                                            Detail
                                        </a>
                                        @if($overtimeOrder->employee_response_status === \App\Models\OvertimeOrder::RESPONSE_PENDING)
                                            <form action="{{ route('overtime-orders.destroy', $overtimeOrder->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">
                                                    Batalkan
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada perintah lembur.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($overtimeOrders, 'links'))
                    <div class="mt-3">
                        {{ $overtimeOrders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
