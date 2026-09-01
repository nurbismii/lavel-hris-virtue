@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-plane-departure text-primary me-2"></i>
                    {{ __('self_service.roster.index_title') }}
                </h4>
                <small class="text-muted">
                    {{ __('self_service.roster.index_subtitle') }}
                </small>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('roster.history') }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-history me-1"></i> {{ __('self_service.roster.schedule_history_action') }}
                </a>
                <a href="{{ route('roster.create') }}" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus me-1"></i> {{ __('self_service.actions.apply_roster') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body table-responsive">
                <table id="table-approval-roster" class="table table-bordered table-striped mb-0 table-sm small text-sm nowrap">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('tables.submission') }}</th>
                            <th>{{ __('tables.nik') }}</th>
                            <th>{{ __('tables.name') }}</th>
                            <th>{{ __('tables.start') }}</th>
                            <th>{{ __('tables.end') }}</th>
                            <th>{{ __('tables.category') }}</th>
                            <th>{{ __('tables.delegate_status') }}</th>
                            <th>{{ __('tables.hod_status') }}</th>
                            <th>{{ __('tables.hr_status') }}</th>
                            <th>{{ __('tables.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cutis as $cuti)
                        <tr>
                            <td>{{ formatDateIndonesia($cuti->tanggal_pengajuan) }}</td>
                            <td>{{ $cuti->employee->nik }}</td>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti_berakhir) }}</td>
                            <td>{!! $cuti->status_rencana_label !!}</td>
                            <td>{!! $cuti->status_delegate_label !!}</td>
                            <td>{!! $cuti->status_hod_label !!}</td>
                            <td>{!! $cuti->status_hrd_label !!}</td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">

                                    <a href="{{ route('roster.edit', $cuti->id) }}" class="btn btn-sm btn-primary btn-sm btn-icon-split">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-edit"></i>
                                        </span>
                                        <span class="text">{{ __('self_service.actions.edit') }}</span>
                                    </a>
                                    <a href="{{ route('roster.destroy', $cuti->id) }}" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                        <span class="text">{{ __('self_service.actions.delete') }}</span>
                                    </a>

                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-approval-roster").DataTable({
            responsive: true,

            order: [
                [1, 'desc']
            ] // kolom index 1, urut terbaru dulu
        });
    });
</script>
@endpush


@endsection
