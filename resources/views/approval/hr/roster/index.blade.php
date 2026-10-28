@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner ui-page">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="ui-page-title ">{{ __('Approval Roster') }}</h3>
                <small class="text-muted">
                    {{ __('Persetujuan HR untuk karyawan pengajuan cuti/insentif') }}
                </small>
            </div>
        </div>

        @include('approval.partials.list-filter')

        <div class="card">
            <div class="card-body">

                <div class="ui-table-wrap {{ $cutis->isEmpty() ? 'd-none' : '' }}" role="region" tabindex="0" aria-label="{{ __('Daftar pengajuan') }}">
                <table id="table-approval-roster" class="approval-table table table-bordered">
                    <thead>
                        <tr>
                            <th>{{ __('tables.nik') }}</th>
                            <th>{{ __('tables.name') }}</th>
                            <th>{{ __('tables.start') }}</th>
                            <th>{{ __('tables.end') }}</th>
                            <th>{{ __('tables.category') }}</th>
                            <th>{{ __('tables.status') }}</th>
                            <th>{{ __('tables.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cutis as $cuti)
                        @php
                            $hrdStatus = (int) $cuti->status_pengajuan_hrd;
                        @endphp
                        <tr>
                            <td>{{ $cuti->employee->nik }}</td>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti_berakhir) }}</td>
                            <td>{!! $cuti->status_rencana_label !!}</td>
                            <td>{!! $cuti->status_hrd_label !!}</td>
                            <td>
                                <a href="{{ route('approval.roster.hrd.show', $cuti->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i> {{ __('Detail') }}
                                </a>

                                @if($hrdStatus === 0)
                                <form action="{{ route('approval.roster.hrd.process', $cuti->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="1">
                                    <button class="btn btn-success btn-sm">{{ __('Approve') }}</button>
                                </form>

                                <form action="{{ route('approval.roster.hrd.process', $cuti->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="2">
                                    <button type="button" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">{{ __('Reject') }}</button>
                                </form>
                                @elseif($hrdStatus === 1)
                                    <span class="badge bg-success ms-1">{{ __('Disetujui HR') }}</span>
                                @else
                                    <span class="badge bg-danger ms-1">{{ __('Ditolak HR') }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7"><div class="ui-empty-state"><i class="fas fa-inbox" aria-hidden="true"></i><span>{{ __('Tidak ada pengajuan yang sesuai dengan pencarian.') }}</span></div></td></tr>
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
