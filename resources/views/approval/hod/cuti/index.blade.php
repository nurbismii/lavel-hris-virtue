@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="ui-page-title ">{{ __('Approval Cuti Tahunan') }}</h3>
                <small class="text-muted">
                    {{ __('Persetujuan HOD untuk karyawan pengajuan cuti tahunan') }}
                </small>
            </div>
        </div>

        @include('approval.partials.list-filter')

        <div class="card">
            <div class="card-body">

                <div class="ui-table-wrap {{ $cutis->isEmpty() ? 'd-none' : '' }}" role="region" tabindex="0" aria-label="{{ __('Daftar pengajuan') }}">
                <table id="table-approval-cuti" class="approval-table table table-bordered">
                    <thead>
                        <tr>
                            <th>{{ __('tables.name') }}</th>
                            <th>{{ __('tables.submission') }}</th>
                            <th>{{ __('tables.start') }}</th>
                            <th>{{ __('tables.end') }}</th>
                            <th>{{ __('tables.amount') }}</th>
                            <th>{{ __('tables.delegation') }}</th>
                            <th>{{ __('tables.status') }}</th>
                            <th>{{ __('tables.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cutis as $cuti)
                        @php
                            $hodStatus = (int) $cuti->status_hod;
                            $hrdStatus = (int) $cuti->status_hrd;
                        @endphp
                        <tr>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_mulai) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_berakhir)}}</td>
                            <td>{{ $cuti->jumlah }} Hari</td>
                            <td>{!! $cuti->status_delegate_label !!}</td>
                            <td>{!! $cuti->status_hod_label !!}</td>
                            <td>
                                @if($hodStatus === 0)
                                <form action="{{ route('approval.cuti.hod.process', $cuti->id) }}" method="POST">
                                    @csrf
                                    <button name="action" value="1" class="btn btn-success btn-sm">
                                        {{ __('Approve') }}
                                    </button>
                                    <button type="button" name="action" value="2" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                        {{ __('Reject') }}
                                    </button>
                                </form>
                                @elseif($hodStatus === 1 && $hrdStatus === 0)
                                    <span class="badge bg-info">{{ __('Menunggu HR') }}</span>
                                    <small class="d-block text-muted mt-1">{{ __('Disetujui HOD') }}</small>
                                @elseif($hodStatus === 1 && $hrdStatus === 1)
                                    <span class="badge bg-success">{{ __('Disetujui HR') }}</span>
                                    <small class="d-block text-muted mt-1">{{ __('Proses selesai') }}</small>
                                @elseif($hodStatus === 1 && $hrdStatus === 2)
                                    <span class="badge bg-danger">{{ __('Ditolak HR') }}</span>
                                @else
                                    <span class="badge bg-danger">{{ __('Ditolak HOD') }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8"><div class="ui-empty-state"><i class="fas fa-inbox" aria-hidden="true"></i><span>{{ __('Tidak ada pengajuan yang sesuai dengan pencarian.') }}</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                @include('approval.partials.list-footer', ['items' => $cutis])
            </div>
        </div>
    </div>
</div>


@endsection
