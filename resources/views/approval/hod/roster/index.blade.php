@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner ui-page">

        {{-- HEADER --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="ui-page-title ">{{ __('Approval Roster') }}</h3>
                <small class="text-muted">
                    {{ __('Persetujuan HOD untuk karyawan pengajuan cuti/insentif') }}
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
                            <th>{{ __('tables.submission') }}</th>
                            <th>{{ __('tables.start') }}</th>
                            <th>{{ __('tables.end') }}</th>
                            <th>{{ __('tables.category') }}</th>
                            <th>{{ __('tables.delegation') }}</th>
                            <th>{{ __('tables.status') }}</th>
                            <th>{{ __('tables.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cutis as $cuti)
                        @php
                            $hodStatus = (int) $cuti->status_pengajuan;
                            $hrdStatus = (int) $cuti->status_pengajuan_hrd;
                        @endphp
                        <tr>
                            <td>{{ $cuti->employee->nik }}</td>
                            <td>{{ $cuti->employee->nama_karyawan }}</td>
                            <td>{{ formatDateIndonesia($cuti->tanggal_pengajuan) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti) }}</td>
                            <td>{{ formatDateIndonesia($cuti->tgl_mulai_cuti_berakhir) }}</td>
                            <td>{!! $cuti->status_rencana_label !!}</td>
                            <td>{!! $cuti->status_delegate_label !!}</td>
                            <td>{!! $cuti->status_hod_label !!}</td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">

                                    <a href="{{ route('approval.roster.hod.show', $cuti->id) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> {{ __('Detail') }}
                                    </a>

                                    @if($hodStatus === 0)
                                    <form action="{{ route('approval.roster.hod.process', $cuti->id) }}"
                                        method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="1">
                                        <button class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i> {{ __('Approve') }}
                                        </button>
                                    </form>

                                    <form action="{{ route('approval.roster.hod.process', $cuti->id) }}"
                                        method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="2">
                                        <button type="button" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                            <i class="fas fa-times me-1"></i> {{ __('Reject') }}
                                        </button>
                                    </form>
                                    @elseif($hodStatus === 1 && $hrdStatus === 0)
                                        <span class="badge bg-info align-self-center">{{ __('Menunggu HR') }}</span>
                                    @elseif($hodStatus === 1 && $hrdStatus === 1)
                                        <span class="badge bg-success align-self-center">{{ __('Disetujui HR') }}</span>
                                    @elseif($hodStatus === 1 && $hrdStatus === 2)
                                        <span class="badge bg-danger align-self-center">{{ __('Ditolak HR') }}</span>
                                    @else
                                        <span class="badge bg-danger align-self-center">{{ __('Ditolak HOD') }}</span>
                                    @endif

                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9"><div class="ui-empty-state"><i class="fas fa-inbox" aria-hidden="true"></i><span>{{ __('Tidak ada pengajuan yang sesuai dengan pencarian.') }}</span></div></td></tr>
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
