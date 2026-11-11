@extends('layouts.app')
@section('title', 'Laporan Beban Gaji')
@push('styles')<link rel="stylesheet" href="{{ versioned_asset('assets/css/payroll.css') }}">@endpush
@section('content')
<div class="container-fluid"><div class="page-inner salary-page payroll-page">
    <header class="salary-header">
        <div><span class="salary-eyebrow">Arsip payroll</span><h1>Laporan Beban Gaji</h1><p>Rekap penghasilan dan biaya perusahaan dari payroll yang sudah difinalisasi.</p></div>
        <a class="btn btn-light" href="{{ route('payroll.index') }}">Kembali ke Proses Payroll</a>
    </header>
    <section class="salary-card" aria-labelledby="cost-filter-title">
        <div class="salary-card-header"><h2 id="cost-filter-title">Periode dan perusahaan</h2></div>
        <div class="salary-card-body">
            <form class="payroll-form" method="GET" action="{{ route('payroll.costs') }}">
                <div><label for="cost-period" class="form-label">Periode payroll</label><input type="month" class="form-control" id="cost-period" name="period" value="{{ $period }}" required></div>
                <div><label for="cost-employer" class="form-label">Perusahaan</label><select class="form-select" id="cost-employer" name="employer_id"><option value="">Semua perusahaan</option>@foreach($companies as $id=>$name)<option value="{{ $id }}" @selected(($filters['employer_id'] ?? null) == $id)>{{ $name }}</option>@endforeach</select></div>
                <div><label for="cost-nik" class="form-label">NIK karyawan (opsional)</label><input type="text" class="form-control" id="cost-nik" name="nik" value="{{ $filters['nik'] ?? '' }}" maxlength="100"></div>
                <div class="payroll-form-wide"><button type="submit" class="btn btn-primary">Tampilkan laporan</button></div>
            </form>
        </div>
    </section>
    @if($entries === null)
        <p class="alert alert-info" role="status">Penyimpanan arsip payroll belum disiapkan pada database.</p>
    @else
        <div class="payroll-totals" aria-label="Ringkasan beban gaji">
            @foreach($components as $path=>$label)
                <div class="salary-card"><div class="salary-card-body"><span class="salary-note">{{ $label }}</span><strong class="payroll-amount">Rp {{ number_format($totals[$path], 0, ',', '.') }}</strong></div></div>
            @endforeach
        </div>
        <p class="salary-note mb-3">Total beban gaji mencakup penghasilan kas setelah potongan unpaid leave dan alpa, tunjangan pajak, dan BPJS yang ditanggung perusahaan.</p>
        <section class="salary-card" aria-labelledby="cost-detail-title">
            <div class="salary-card-header"><div><h2 id="cost-detail-title">Rincian payroll final</h2><p>{{ number_format($count, 0, ',', '.') }} baris payroll untuk periode {{ $period }}.</p></div></div>
            <div class="table-responsive"><table class="table salary-table mb-0">
                <thead><tr><th scope="col">Karyawan</th><th scope="col">Perusahaan</th>@foreach($components as $label)<th scope="col">{{ $label }}</th>@endforeach<th scope="col">Arsip</th></tr></thead>
                <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td data-label="Karyawan"><strong>{{ $entry->snapshot['employee_name'] ?? $entry->employee_nik }}</strong><small>{{ $entry->employee_nik }}</small></td>
                        <td data-label="Perusahaan">{{ $companies[$entry->employer_id] ?? ('Perusahaan #'.$entry->employer_id) }}</td>
                        @foreach($components as $path=>$label)<td data-label="{{ $label }}">Rp {{ number_format(data_get($entry->snapshot, $path, 0), 0, ',', '.') }}</td>@endforeach
                        <td data-label="Arsip"><a class="btn btn-light btn-sm" href="{{ route('payroll.show', $entry->import_history_id) }}">Buka arsip</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="salary-empty">Belum ada payroll final untuk filter ini.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            @if($entries->hasPages())<div class="salary-card-body">{{ $entries->appends(['period'=>$period])->links() }}</div>@endif
        </section>
    @endif
</div></div>
@endsection
