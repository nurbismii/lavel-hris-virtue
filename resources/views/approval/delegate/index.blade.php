@extends('layouts.app')

@section('content')
@php
    $routeModule = fn($key) => str_replace('_', '-', $key);
@endphp

<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="text-primary mb-1">Approval Delegasi</h3>
                <small class="text-muted">Verifikasi pengajuan karyawan sebelum diteruskan ke HOD.</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($modules as $key => $label)
                        <a href="{{ route('approval.delegate.index', ['module' => $routeModule($key)]) }}"
                           class="btn btn-sm {{ $module === $key ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $label }}
                            @if(($counts[$key] ?? 0) > 0)
                                <span class="badge bg-light text-dark ms-1">{{ $counts[$key] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="fw-semibold mb-1">{{ $moduleLabel }}</h5>
                        <p class="text-muted small mb-0">Satu dari delegasi aktif cukup melakukan approval agar pengajuan masuk ke antrean HOD.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>Detail Pengajuan</th>
                                <th>Periode/Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                @php
                                    $employee = optional($item->employee);
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $employee->nama_karyawan ?: '-' }}</strong>
                                        <small class="d-block text-muted">{{ $item->nik_karyawan }}</small>
                                        <small class="d-block text-muted">
                                            {{ optional($employee->departemen)->departemen ?? optional(optional($employee->divisi)->departemen)->departemen ?? '-' }}
                                            /
                                            {{ optional($employee->divisi)->nama_divisi ?? '-' }}
                                        </small>
                                    </td>
                                    <td>
                                        @if($module === \App\Models\ApprovalDelegation::MODULE_CUTI)
                                            <span class="fw-semibold">{{ $item->jumlah }} hari cuti</span>
                                            <small class="d-block text-muted">{{ $item->keterangan ?: '-' }}</small>
                                        @elseif($module === \App\Models\ApprovalDelegation::MODULE_IZIN)
                                            {!! $item->status_tipe_label !!}
                                            <small class="d-block text-muted mt-1">{{ $item->keterangan ?: '-' }}</small>
                                            @if($item->foto && $item->foto !== '-')
                                                <a href="{{ route('approval.delegate.izin.proof', $item->id) }}" target="_blank" class="small d-block mt-1">
                                                    <i class="fas fa-paperclip me-1"></i> Lihat bukti
                                                </a>
                                            @endif
                                        @elseif($module === \App\Models\ApprovalDelegation::MODULE_ROSTER)
                                            {!! $item->status_rencana_label !!}
                                            <small class="d-block text-muted mt-1">{{ optional($item->periodeKerjaRoster)->alasan ?: '-' }}</small>
                                            @if($item->file)
                                                <a href="{{ route('approval.delegate.roster.attachment', $item->id) }}" target="_blank" class="small d-block mt-1">
                                                    <i class="fas fa-paperclip me-1"></i> Lihat lampiran
                                                </a>
                                            @endif
                                        @elseif($module === \App\Models\ApprovalDelegation::MODULE_ROSTER_OFF)
                                            <span class="fw-semibold">OFF Roster</span>
                                            <small class="d-block text-muted">{{ $item->alasan ?: '-' }}</small>
                                        @elseif($module === \App\Models\ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION)
                                            @foreach($item->requestedChanges() as $label => $value)
                                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $label }}: {{ $value }}</span>
                                            @endforeach
                                            <small class="d-block text-muted mt-1">{{ \Illuminate\Support\Str::limit($item->reason, 100) }}</small>
                                            @if($item->attachment_path)
                                                <a href="{{ route('attendance-corrections.attachment', $item->id) }}" target="_blank" class="small d-block mt-1">
                                                    <i class="fas fa-paperclip me-1"></i> Lihat lampiran
                                                </a>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        @if(in_array($module, [\App\Models\ApprovalDelegation::MODULE_CUTI, \App\Models\ApprovalDelegation::MODULE_IZIN], true))
                                            {{ formatDateIndonesia($item->tanggal_mulai) }}
                                            <small class="d-block text-muted">s/d {{ formatDateIndonesia($item->tanggal_berakhir) }}</small>
                                        @elseif($module === \App\Models\ApprovalDelegation::MODULE_ROSTER)
                                            {{ formatDateIndonesia($item->tgl_mulai_cuti) }}
                                            <small class="d-block text-muted">s/d {{ formatDateIndonesia($item->tgl_mulai_cuti_berakhir) }}</small>
                                        @elseif($module === \App\Models\ApprovalDelegation::MODULE_ROSTER_OFF)
                                            {{ formatDateIndonesia($item->tanggal_off) }}
                                        @else
                                            {{ formatDateIndonesia($item->tanggal) }}
                                        @endif
                                    </td>
                                    <td>
                                        {!! $item->status_delegate_label ?? '<span class="badge bg-warning text-dark">Menunggu Delegasi</span>' !!}
                                    </td>
                                    <td>
                                        <form action="{{ route('approval.delegate.process', ['module' => $routeModule($module), 'id' => $item->id]) }}" method="POST" data-approval-confirm-message="Setujui pengajuan ini sebagai delegasi HOD?">
                                            @csrf
                                            <div class="d-flex gap-2">
                                                <button name="action" value="1" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check me-1"></i>
                                                    Approve
                                                </button>
                                                <button type="button" name="action" value="2" class="btn btn-danger btn-sm js-approval-reject" data-bs-toggle="modal" data-bs-target="#approvalRejectReasonModal">
                                                    <i class="fas fa-times me-1"></i>
                                                    Reject
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Tidak ada pengajuan menunggu delegasi untuk modul ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $items->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
