@extends('layouts.app')
@section('title', 'Siapkan Data Payroll')
@push('styles')<link rel="stylesheet" href="{{ versioned_asset('assets/css/payroll.css') }}">@endpush
@section('content')
@php($scope = ['employer_id'=>$company->id, 'period'=>$period])
<div class="container-fluid"><div class="page-inner salary-page payroll-page">
    <header class="salary-header"><div><span class="salary-eyebrow">{{ $company->nama_perusahaan }} · {{ $period }}</span><h1>Siapkan data karyawan</h1><p>Cut-off {{ $cutoff['start_date'] }} sampai {{ $cutoff['end_date'] }}</p></div><a class="btn btn-light" href="{{ route('payroll.index', $scope) }}">Kembali ke Proses Payroll</a></header>
    <section class="salary-card" aria-labelledby="employee-payroll-title">
        <div class="salary-card-header"><div><h2 id="employee-payroll-title">Karyawan periode ini</h2><p>Lengkapi profil yang belum dikonfirmasi atau buka isian untuk komponen yang berubah. Karyawan yang sudah final untuk periode ini dikeluarkan dari daftar.</p></div></div>
        <div class="salary-card-body">
            <form class="d-flex flex-wrap gap-2 align-items-end" method="GET" action="{{ route('payroll.inputs') }}">
                <input type="hidden" name="employer_id" value="{{ $company->id }}"><input type="hidden" name="period" value="{{ $period }}">
                <div><label for="payroll-search" class="form-label">Cari nama atau NIK</label><input class="form-control" id="payroll-search" name="q" value="{{ $search }}" maxlength="100"></div>
                <button type="submit" class="btn btn-outline-primary">Cari</button>
            </form>
        </div>
        <div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>Karyawan</th><th>Profil pajak dan BPJS</th><th>Isian periode</th><th>Aksi</th></tr></thead><tbody>
            @forelse($employees as $employee)
                @php($record = $inputs->forEmployee($records->get($employee->nik, collect()), $period))
                <tr><td data-label="Karyawan"><strong>{{ $employee->nama_karyawan }}</strong><small>{{ $employee->nik }}</small></td>
                    <td data-label="Profil pajak dan BPJS">{{ $record['profile'] ? 'Profil tersimpan' : 'Perlu konfirmasi' }}</td>
                    <td data-label="Isian periode">{{ !empty($record['current']?->data['separate_reason']) ? 'Diproses terpisah' : ($record['current'] ? 'Isian tersimpan' : 'Belum ada isian tambahan') }}</td>
                    <td data-label="Aksi"><a class="btn btn-light btn-sm" href="{{ route('payroll.inputs.edit', ['nik'=>$employee->nik] + $scope) }}">Buka isian<span class="visually-hidden"> {{ $employee->nama_karyawan }}</span></a></td></tr>
            @empty
                <tr><td colspan="4" class="salary-empty">Tidak ada karyawan pada pilihan ini.</td></tr>
            @endforelse
        </tbody></table></div><div class="salary-card-body">{{ $employees->links() }}</div>
    </section>
</div></div>
@endsection
