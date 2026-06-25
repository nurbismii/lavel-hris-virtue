@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-sign-out-alt text-primary me-2"></i>
                    {{ __('self_service.leave.index_title') }}
                </h4>
                <small class="text-muted">
                    {{ __('self_service.leave.index_subtitle') }}
                </small>
            </div>

            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('cuti.create') }}" class="btn btn-sm btn-secondary">
                    <span class="btn-label">
                        <i class="fa fa-plus"></i>
                    </span>
                    {{ __('self_service.actions.apply_leave') }}
                </a>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table-cuti" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('tables.leave_submission') }}</th>
                                    <th>{{ __('tables.nik') }}</th>
                                    <th>{{ __('tables.name') }}</th>
                                    <th>{{ __('tables.leave_start') }}</th>
                                    <th>{{ __('tables.leave_end') }}</th>
                                    <th>{{ __('tables.leave_amount') }}</th>
                                    <th>{{ __('tables.delegate_status') }}</th>
                                    <th>{{ __('tables.hod_status') }}</th>
                                    <th>{{ __('tables.hr_status') }}</th>
                                    <th>{{ __('tables.action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cuti as $c)
                                <tr>
                                    <td>{{ formatDateIndonesia($c->tanggal) }}</td>
                                    <td>{{ $c->nik_karyawan }}</td>
                                    <td>{{ $c->employee->nama_karyawan }}</td>
                                    <td>{{ formatDateIndonesia($c->tanggal_mulai) }}</td>
                                    <td>{{ formatDateIndonesia($c->tanggal_berakhir) }}</td>
                                    <td>{{ $c->jumlah }} {{ __('self_service.common.day') }}</td>
                                    <td>{!! $c->status_delegate_label !!}</td>
                                    <td>{!! $c->status_hod_label !!}</td>
                                    <td>{!! $c->status_hrd_label !!}</td>
                                    <td>
                                        <a href="{{ route('cuti.edit', $c->id) }}" class="btn btn-sm btn-primary btn-sm btn-icon-split">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-edit"></i>
                                            </span>
                                            <span class="text">{{ __('self_service.actions.edit') }}</span>
                                        </a>
                                        <a href="{{ route('cuti.destroy', $c->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-trash"></i>
                                            </span>
                                            <span class="text">{{ __('self_service.actions.delete') }}</span>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-cuti").DataTable({
            responsive: true,
        });
    });
</script>
@endpush

@endsection
