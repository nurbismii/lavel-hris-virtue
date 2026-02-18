@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold">
                Detail Pengajuan Roster
            </h4>

            <a href="{{ route('approval.roster.hod') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>

        {{-- INFORMASI KARYAWAN --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-light fw-bold">
                Informasi Karyawan
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="text-muted">Nama</label>
                        <div class="fw-semibold">
                            {{ $roster->employee->nama_karyawan }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted">NIK</label>
                        <div class="fw-semibold">
                            {{ $roster->nik_karyawan }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted">Departemen</label>
                        <div class="fw-semibold">
                            {{ $roster->employee->divisi->departemen->departemen ?? '-' }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted">Divisi</label>
                        <div class="fw-semibold">
                            {{ $roster->employee->divisi->nama_divisi ?? '-' }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted">Kategori</label>
                        <div>
                            {!! $roster->status_rencana_label !!}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted">Status HOD</label>
                        <div>
                            {!! $roster->status_hod_label !!}
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- PERIODE ROSTER --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-light fw-bold text-dark">
                Periode Roster
            </div>
            <div class="card-body">

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="text-muted">Periode Awal</label>
                        <div class="fw-semibold">
                            {{ $roster->periodeKerjaRoster->periode_awal }}
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="text-muted">Periode Akhir</label>
                        <div class="fw-semibold">
                            {{ $roster->periodeKerjaRoster->periode_akhir }}
                        </div>
                    </div>
                </div>

                @php
                $minggu = [
                ['label' => 'Minggu ke-1', 'hari' => 'satu', 'tanggal' => 'tanggal_satu'],
                ['label' => 'Minggu ke-2', 'hari' => 'dua', 'tanggal' => 'tanggal_dua'],
                ['label' => 'Minggu ke-3', 'hari' => 'tiga', 'tanggal' => 'tanggal_tiga'],
                ['label' => 'Minggu ke-4', 'hari' => 'empat', 'tanggal' => 'tanggal_empat'],
                ['label' => 'Minggu ke-5', 'hari' => 'lima', 'tanggal' => 'tanggal_lima'],
                ];
                @endphp

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Minggu</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>

                        @php
                        use Carbon\Carbon;

                        $totalInsentif = 0;

                        if(optional($roster->periodeKerjaRoster)->tipe_rencana == 2) { // 2 = Insentif

                        foreach($minggu as $item) {
                        $status = strtolower($roster->periodeKerjaRoster->{$item['hari']} ?? '');

                        if($status == 'bekerja') {
                        $totalInsentif++;
                        }
                        }
                        }

                        @endphp
                        <tbody>
                            @foreach($minggu as $item)
                            <tr>
                                <td>{{ $item['label'] }}</td>
                                <td>
                                    {{ $roster->periodeKerjaRoster->{$item['hari']} ?? '-' }}
                                </td>
                                <td>
                                    {{ formatDateIndonesia($roster->periodeKerjaRoster->{$item['tanggal']} ?? null) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if(optional($roster->periodeKerjaRoster)->tipe_rencana == 2)
                    <tr class="table-success fw-bold">
                        <td colspan="2" class="text-end">Total Hari Insentif</td>
                        <td>{{ $totalInsentif }} Hari</td>
                    </tr>
                    @endif
                </div>

            </div>
        </div>

        @php
        function selisihHari($start, $end) {

        if(!$start || !$end) return 0;
        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
        }

        $cutiRosterHari = selisihHari($roster->tgl_mulai_cuti, $roster->tgl_mulai_cuti_berakhir);
        $cutiTahunanHari = selisihHari($roster->tgl_mulai_cuti_tahunan, $roster->tgl_mulai_cuti_tahunan_berakhir);

        $insentifHari = 0;
        $jumlahBekerja = 0;
        $extraOffHari = 0;

        $periode = optional($roster->periodeKerjaRoster);

        $minggu = [
        ['status' => 'satu', 'tanggal' => 'tanggal_satu'],
        ['status' => 'dua', 'tanggal' => 'tanggal_dua'],
        ['status' => 'tiga', 'tanggal' => 'tanggal_tiga'],
        ['status' => 'empat', 'tanggal' => 'tanggal_empat'],
        ['status' => 'lima', 'tanggal' => 'tanggal_lima'],
        ];

        if($periode->tipe_rencana == 2) { // INSENTIF

        $insentifHari = selisihHari($roster->tgl_awal_kerja, $roster->tgl_akhir_kerja);

        foreach($minggu as $item) {
        $status = strtoupper($periode->{$item['status']} ?? '');

        if($status == 'BEKERJA') {
        $jumlahBekerja++;
        }
        }

        $insentifHari += $jumlahBekerja;
        }

        if($periode->tipe_rencana == 1) { // ROSTER

        foreach($minggu as $item) {

        $status = strtoupper($periode->{$item['status']} ?? '');
        $tanggal = $periode->{$item['tanggal']} ?? null;

        if($status == 'OFF' && $tanggal && $roster->tgl_mulai_cuti_berakhir) {

        $tanggalOff = Carbon::parse($tanggal);
        $tanggalAkhir = Carbon::parse($roster->tgl_mulai_cuti_berakhir);

        if($tanggalOff->greaterThan($tanggalAkhir)) {
        $extraOffHari += $tanggalAkhir->diffInDays($tanggalOff);
        }
        }
        }
        }

        $totalKeseluruhan = $cutiRosterHari + $cutiTahunanHari + $insentifHari;
        @endphp


        {{-- CUTI / OFF / INSENTIF --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-success fw-bold text-white">
                Rencana Cuti / Insentif
            </div>
            <div class="card-body">

                <div class="row g-4">

                    {{-- CUTI ROSTER --}}
                    <div class="col-md-4">
                        <div class="p-3 border rounded text-center h-100">
                            <div class="text-muted">Cuti Roster</div>
                            <div class="fw-bold fs-5">
                                {{ formatDateIndonesia($roster->tgl_mulai_cuti) }}
                                <br> s/d <br>
                                {{ formatDateIndonesia($roster->tgl_mulai_cuti_berakhir) }}
                            </div>
                            <div class="mt-2 badge bg-primary">
                                {{ $cutiRosterHari }} Hari
                            </div>
                        </div>
                    </div>

                    {{-- CUTI TAHUNAN --}}
                    <div class="col-md-4">
                        <div class="p-3 border rounded text-center h-100">
                            <div class="text-muted">Cuti Tahunan</div>
                            <div class="fw-bold fs-5">
                                {{ formatDateIndonesia($roster->tgl_mulai_cuti_tahunan) }}
                                <br> s/d <br>
                                {{ formatDateIndonesia($roster->tgl_mulai_cuti_tahunan_berakhir) }}
                            </div>
                            <div class="mt-2 badge bg-info">
                                {{ $cutiTahunanHari }} Hari
                            </div>
                        </div>
                    </div>

                    {{-- INSENTIF --}}
                    <div class="col-md-4">
                        <div class="p-3 border rounded text-center h-100">
                            <div class="text-muted">Insentif</div>
                            <div class="fw-bold fs-5">
                                {{ formatDateIndonesia($roster->tgl_awal_kerja) }}
                                <br> s/d <br>
                                {{ formatDateIndonesia($roster->tgl_akhir_kerja) }}
                            </div>

                            @if(optional($roster->periodeKerjaRoster)->tipe_rencana == 2)
                            <div class="mt-2 badge bg-success">
                                {{ $insentifHari }} Hari
                            </div>
                            <div class="small text-muted mt-1">
                                (Termasuk {{ $jumlahBekerja }} Hari BEKERJA)
                            </div>
                            @else
                            <div class="mt-2 badge bg-secondary">
                                Tidak dihitung
                            </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="card-body text-center">
                <h5 class="fw-bold">
                    Total Keseluruhan Hari :
                    <span class="badge bg-success fs-5">
                        {{ $totalKeseluruhan }} Hari
                    </span>
                </h5>
            </div>
        </div>

        {{-- APPROVAL BUTTON --}}
        @if($roster->status_hod == 0)
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">

                <form action="{{ route('approval.roster.hod.process', $roster->id) }}"
                    method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="1">
                    <button class="btn btn-success px-4">
                        <i class="fas fa-check me-2"></i>Setujui
                    </button>
                </form>

                <form action="{{ route('approval.roster.hod.process', $roster->id) }}"
                    method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="2">
                    <button class="btn btn-danger px-4">
                        <i class="fas fa-times me-2"></i>Tolak
                    </button>
                </form>

            </div>
        </div>
        @endif

    </div>
</div>
@endsection