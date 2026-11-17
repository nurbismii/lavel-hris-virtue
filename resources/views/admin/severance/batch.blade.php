@extends('layouts.app')
@section('title', 'Batch Pengajuan Pesangon')
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/severance.css') }}">@endpush
@section('content')
<div class="container-fluid">
    <div class="page-inner salary-page severance-page">
        <header class="salary-header">
            <div><span class="salary-eyebrow">Pengajuan #{{ $history->id }}</span>
                <h1>{{ $history->summary['title'] ?? 'Pengajuan pesangon' }}</h1>
                <p>Hasil perhitungan staf dibuka setelah persetujuan akhir Supervisor HI.</p>
            </div><a class="btn btn-light" href="{{ route('severance.index') }}">Semua batch</a>
        </header>
        <section class="salary-card">
            <div class="salary-card-body">
                <div class="severance-process" data-severance-process data-status-url="{{ route('severance.status', $history) }}" data-status="{{ $history->status }}">
                    <div><strong>Impor: <span data-process-label>{{ $history->status_label }}</span></strong>
                        <p data-process-progress>{{ $history->total_rows }} baris dibaca; {{ $history->success_count }} valid, {{ $history->failed_count }} gagal.</p>
                        <p class="severance-error" data-process-error role="status">{{ $history->error_message }}</p>
                    </div>
                    @if(in_array($history->status, ['queued', 'processing']))<button type="button" class="btn btn-light" data-process-refresh>Refresh status</button>@endif
                    @if($canManage && $history->status === 'failed')<form data-severance-form action="{{ route('severance.retry', $history) }}" method="POST">@csrf<button type="submit" class="btn btn-outline-primary">Coba lagi</button>
                        <p data-form-error class="severance-error" role="alert"></p>
                    </form>@endif
                </div>
                <div class="severance-counts">@foreach(\App\Models\SeveranceRequest::statuses() as $status => $label)<a href="{{ route('severance.batch', ['history' => $history, 'status' => $status]) }}"><strong>{{ $counts[$status] ?? 0 }}</strong><span>{{ $label }}</span></a>@endforeach</div>
                @if($canManage && ($counts['draft'] ?? 0) && in_array($history->status, ['completed', 'completed_with_errors']))
                <form data-severance-form data-confirm="Kirim seluruh draf yang valid ke Supervisor Payroll? Pasal, tanggal, alasan, dan ongkos akan dikunci sampai dikembalikan untuk revisi." action="{{ route('severance.submit', $history) }}" method="POST">@csrf<button class="btn btn-primary" type="submit">Kirim {{ $counts['draft'] }} draf ke Supervisor Payroll</button>
                    <p data-form-error class="severance-error" role="alert"></p>
                </form>
                @endif
            </div>
        </section>
        <section class="salary-card">
            <div class="salary-card-header">
                <div>
                    <h2>Daftar pengajuan</h2>
                    <p>Upah terisi saat Payroll menyetujui. Sisa cuti diisi Supervisor HI; nilai 0 tetap harus dikonfirmasi.</p>
                </div><a class="btn btn-light btn-sm" href="{{ route('severance.batch', $history) }}">Semua status</a>
            </div>
            <form data-severance-form action="{{ route('severance.export', $history) }}" method="POST">@csrf
                @if($canManage && ($counts['approved'] ?? 0))<div class="salary-card-body salary-actions severance-export-actions"><button class="btn btn-primary" type="submit" name="all" value="0">Export pilihan</button><button class="btn btn-outline-primary" type="submit" name="all" value="1">Export semua final</button><button class="btn btn-light" type="submit" data-print-url="{{ route('severance.print', $history) }}" name="all" value="0">Cetak pilihan</button><small>Cetak batch: 4 pengajuan per A4 (2 × 2). Pilihan hanya di halaman ini. Cetak maksimal 50, export maksimal {{ config('severance.max_export_rows') }} pengajuan.</small>
                    <p data-form-error class="severance-error" role="alert"></p>
                </div>@endif
                <div class="table-responsive">
                    <table class="table salary-table mb-0">
                        <thead>
                            <tr>@if($canManage)<th>Pilih</th>@endif<th>Karyawan / tanggal</th>
                                <th>Pasal</th>
                                <th>Status</th>
                                <th>Upah disetujui</th>
                                <th>Hasil bruto</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entries as $entry)<tr>
                            @if($canManage)<td data-label="Pilih">@if($entry->status === 'approved')<input class="form-check-input" type="checkbox" name="ids[]" value="{{ $entry->id }}" aria-label="Pilih {{ $entry->employee_name }} untuk cetak atau export">@else<span aria-label="Belum dapat dipilih">—</span>@endif</td>@endif
                                <td data-label="Karyawan / tanggal"><strong>{{ $entry->employee_name }}</strong><small>{{ $entry->employee_nik }}</small><small>Akhir kerja {{ $entry->termination_date->format('d M Y') }}</small></td>
                                <td data-label="Pasal">{{ $entry->article ?? 'Belum diisi' }}</td>
                                <td data-label="Status"><span class="severance-badge severance-badge--{{ $entry->status }}">{{ \App\Models\SeveranceRequest::statuses()[$entry->status] }}</span></td>
                                <td data-label="Upah disetujui">@if($entry->salary_snapshot)Rp {{ number_format($entry->salary_snapshot['basic_salary'] + $entry->salary_snapshot['tenure_allowance'] + $entry->salary_snapshot['position_allowance'], 2, ',', '.') }}<small>V Payslip FULL {{ $entry->salary_snapshot['period'] }}</small>@else<span class="salary-note">Menunggu Comben</span>@endif</td>
                                <td data-label="Hasil bruto">@if($entry->status === 'approved')<strong>Rp {{ number_format($entry->calculation['total'], 2, ',', '.') }}</strong>@else<span class="salary-note">Menunggu Supervisor HI</span>@endif</td>
                                <td data-label="Aksi"><a class="btn btn-light btn-sm" href="{{ route('severance.show', $entry) }}">Buka pengajuan</a></td>
                            </tr>@empty<tr>
                            <td colspan="{{ $canManage ? 7 : 6 }}" class="salary-empty">Belum ada pengajuan pada status ini. Tunggu impor selesai atau pilih status lain.</td>
                            </tr>@endforelse
                        </tbody>
                    </table>
                </div>
            </form>
            <div class="salary-card-body">{{ $entries->links() }}</div>
        </section>
        @if($failures->total())<section class="salary-card">
            <div class="salary-card-header">
                <div>
                    <h2>{{ $failures->total() }} baris perlu diperbaiki</h2>
                    <p>Baris berikut tidak menjadi pengajuan. Perbaiki sumbernya dan unggah hanya baris yang gagal pada batch baru.</p>
                </div>
            </div>
            <div class="salary-card-body">
                <ul class="severance-failures">@foreach($failures as $failure)<li><strong>Baris {{ $failure->row_number }}</strong> {{ $failure->message }}</li>@endforeach</ul>{{ $failures->links() }}
            </div>
        </section>@endif
    </div>
</div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/severance.js') }}"></script>@endpush
