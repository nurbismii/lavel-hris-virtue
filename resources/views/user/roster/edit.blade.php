@extends('layouts.app')

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />

<style>
    .select2 {
        width: 100% !important;
        overflow: hidden !important;
        height: auto !important;
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="page-inner">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-primary">
                    Formulir Pengajuan Cuti Roster
                </h3>
                <small class="text-muted">
                    Rencana pada masa periode istirahat roster
                </small>
            </div>

            <a href="{{ route('roster.index') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>

        </div>
        <div class="row justify-content-center">

            <form action="{{ route('roster.update', $roster->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                {{-- DATA KARYAWAN --}}
                <div class="card border mb-4">
                    <div class="card-header bg-light fw-bold">
                        Informasi Karyawan
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control"
                                    value="{{ Auth::user()->employee->nama_karyawan }}" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">NIK</label>
                                <input type="text" name="nik_karyawan" class="form-control"
                                    value="{{ Auth::user()->employee->nik }}" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Departemen</label>
                                <input type="text" class="form-control"
                                    value="{{ Auth::user()->employee->divisi->departemen->departemen }}"
                                    readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Posisi</label>
                                <input type="text" class="form-control"
                                    value="{{ Auth::user()->employee->posisi }}" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email', $roster->email) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">No HP</label>
                                <input type="text" name="no_telp" class="form-control" value="{{ old('no_telp', $roster->no_telp) }}" required>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- PERIODE ROSTER --}}
                <div class="card border mb-4">
                    <div class="card-header bg-light fw-bold">
                        Periode roster saat ini
                    </div>
                    <div class="card-body">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Periode Awal</label>
                                <input type="date" name="periode_awal" class="form-control" value="{{ old('periode_awal', $roster->periodeKerjaRoster->periode_awal) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Periode Akhir</label>
                                <input type="date" name="periode_akhir" class="form-control" value="{{ old('periode_akhir', $roster->periodeKerjaRoster->periode_akhir) }}" required>
                            </div>
                        </div>

                        {{-- Roster I - V --}}
                        @php
                        $minggu = [
                        1 => 'satu',
                        2 => 'dua',
                        3 => 'tiga',
                        4 => 'empat',
                        5 => 'lima',
                        ];
                        @endphp

                        @foreach ($minggu as $no => $field)
                        @php
                        $hariField = $field;
                        $tanggalField = 'tanggal_' . $field;
                        @endphp

                        <div class="row g-3 mb-2 align-items-center">
                            <div class="col-md-2 fw-semibold">
                                MINGGU KE-{{ $no }}
                            </div>

                            <div class="col-md-5">
                                <select name="{{ $hariField }}" class="form-select">
                                    <option value="OFF"
                                        {{ old($hariField, $roster->periodeKerjaRoster->$hariField ?? '') == 'OFF' ? 'selected' : '' }}>
                                        OFF
                                    </option>

                                    <option value="BEKERJA"
                                        {{ old($hariField, $roster->periodeKerjaRoster->$hariField ?? '') == 'BEKERJA' ? 'selected' : '' }}>
                                        BEKERJA
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-5">
                                <input type="date" name="{{ $tanggalField }}" class="form-control" value="{{ old($tanggalField, $roster->periodeKerjaRoster->$tanggalField ?? '') }}" required>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- PERIODE ROSTER --}}
                <div class="card border mb-4">
                    <div class="card-header bg-info fw-bold">
                        Rencana cuti roster
                    </div>
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe_rencana" value="1" id="roster" {{ old('tipe_rencana', $roster->periodeKerjaRoster->tipe_rencana) == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="roster">
                                Cuti roster
                            </label>
                        </div>
                    </div>
                </div>

                {{-- RENCANA CUTI ROSTER --}}
                <div class="card border mb-4">
                    <div class="card-header bg-info fw-bold">
                        Jadwal cuti roster
                    </div>
                    <div class="card-body">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal mulai cuti roster</label>
                                <input type="date" id="mulai_cuti_roster" name="tgl_mulai_cuti_roster" class="form-control" value="{{ old('tgl_mulai_cuti', $roster->tgl_mulai_cuti) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tanggal akhir cuti roster</label>
                                <input type="date" id="akhir_cuti_roster" name="tgl_berakhir_cuti_roster" class="form-control" value="{{ old('tgl_mulai_cuti_berakhir', $roster->tgl_mulai_cuti_berakhir) }}">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-info mb-0">
                                Total Hari Cuti Roster:
                                <strong id="total_cuti_roster">0</strong> hari
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RENCANA CUTI TAHUNAN --}}
                <div class="card border mb-4">
                    <div class="card-header bg-info fw-bold">
                        Jadwal cuti tahunan
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">

                            <div class="col-md-6">
                                <label class="form-label">Tanggal mulai cuti tahunan</label>
                                <input type="date" id="mulai_cuti_tahunan" name="tgl_mulai_cuti_tahunan" class="form-control" value="{{ old('tgl_mulai_cuti_tahunan', $roster->tgl_mulai_cuti_tahunan) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal akhir cuti tahunan</label>
                                <input type="date" id="akhir_cuti_tahunan" name="tgl_berakhir_cuti_tahunan" class="form-control" value="{{ old('tgl_mulai_cuti_tahunan_berakhir', $roster->tgl_mulai_cuti_tahunan_berakhir) }}">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-info mb-0">
                                Total Hari Cuti Tahunan:
                                <strong id="total_cuti_tahunan">0</strong> hari
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RENCANA OFF --}}
                <div class="card border mb-4">
                    <div class="card-header bg-info fw-bold">
                        Jadwal off
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Tanggal mulai off</label>
                                <input type="date" id="mulai_off" name="tgl_mulai_off" class="form-control" value="{{ old('tgl_mulai_off', $roster->tgl_mulai_off) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal akhir off</label>
                                <input type="date" id="akhir_off" name="tgl_berakhir_off" class="form-control" value="{{ old('tgl_mulai_off_berakhir', $roster->tgl_mulai_off_berakhir) }}">
                            </div>
                        </div>
                        <div class="alert alert-info mb-0">
                            Total Hari OFF:
                            <strong id="total_off">0</strong> hari
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center">
                        <h5 class="mb-0">
                            Total keseluruhan roster:
                            <span class="badge bg-primary fs-6" id="grand_total">
                                0 Hari
                            </span>
                        </h5>
                    </div>
                </div>

                {{-- PERIODE ROSTER --}}
                <div class="card border mb-4">
                    <div class="card-header bg-success fw-bold">
                        Rencana insentif
                    </div>
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipe_rencana" value="2" id="insentif" {{ old('tipe_rencana', $roster->periodeKerjaRoster->tipe_rencana) == 2 ? 'checked' : '' }}>
                            <label class="form-check-label" for="insentif">
                                Insentif
                            </label>
                        </div>
                    </div>
                </div>

                {{-- RENCANA BEKERJA --}}
                <div class="card border mb-4">
                    <div class="card-header bg-success fw-bold">
                        Jadwal insentif
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">

                            <div class="col-md-6">
                                <label class="form-label">Tanggal mulai insentif</label>
                                <input type="date" id="tgl_awal_kerja" name="tgl_awal_kerja" class="form-control" value="{{ old('tgl_awal_kerja', $roster->tgl_awal_kerja) }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal akhir insetif</label>
                                <input type="date" id="tgl_akhir_kerja" name="tgl_akhir_kerja" class="form-control" value="{{ old('tgl_akhir_kerja', $roster->tgl_akhir_kerja) }}">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-success mb-0">
                                Total Hari Insentif:
                                <strong id="total_insentif">0</strong> hari
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center">
                        <h5 class="mb-0">
                            Total keseluruhan insentif:
                            <span class="badge bg-success fs-6" id="grand_total_insentif">
                                0 Hari
                            </span>
                        </h5>
                    </div>
                </div>

                <div class="col-md-12">
                    {{-- KEBERANGKATAN --}}
                    <div class="card border mb-4">
                        <div class="card-header bg-light fw-bold">
                            Detail keberangkatan
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Keberangkatan</label>
                                    <input type="date" name="tanggal_keberangkatan" class="form-control" value="{{ old('tgl_keberangkatan', $roster->tgl_keberangkatan) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Jam Keberangkatan</label>
                                    <input type="text" name="jam_keberangkatan" class="form-control" value="{{ old('jam_keberangkatan', $roster->jam_keberangkatan) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Dari</label>
                                    <select name="kota_awal_keberangkatan"
                                        class="form-select search-airport">

                                        @if(isset($roster) && $roster->kota_awal_keberangkatan)
                                        <option value="{{ $roster->kota_awal_keberangkatan }}" selected>
                                            {{ $roster->kota_awal_keberangkatan }}
                                        </option>
                                        @endif

                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Tujuan</label>
                                    <select name="kota_tujuan_keberangkatan"
                                        class="form-select search-airport">

                                        @if(isset($roster) && $roster->kota_tujuan_keberangkatan)
                                        <option value="{{ $roster->kota_awal_keberangkatan }}" selected>
                                            {{ $roster->kota_tujuan_keberangkatan }}
                                        </option>
                                        @endif

                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Catatan Penting</label>
                                    <textarea name="catatan_penting_keberangkatan" class="form-control" rows="4">{{ old('catatan_penting_keberangkatan', $roster->catatan_penting_keberangkatan) }}</textarea>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- KEPULANGAN --}}
                    <div class="card border mb-4">
                        <div class="card-header bg-light fw-bold">
                            Detail kepulangan
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Kepulangan</label>
                                    <input type="date" name="tanggal_kepulangan" class="form-control" value="{{ old('tgl_kepulangan', $roster->tgl_kepulangan) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Jam Kepulangan</label>
                                    <input type="text" name="jam_kepulangan" class="form-control" value="{{ old('jam_kepulangan', $roster->jam_kepulangan) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Dari</label>
                                    <select name="kota_awal_kepulangan"
                                        class="form-select search-airport">

                                        @if(isset($roster) && $roster->kota_awal_kepulangan)
                                        <option value="{{ $roster->kota_awal_kepulangan }}" selected>
                                            {{ $roster->kota_awal_kepulangan }}
                                        </option>
                                        @endif

                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Tujuan</label>
                                    <select name="kota_tujuan_kepulangan"
                                        class="form-select search-airport">

                                        @if(isset($roster) && $roster->kota_tujuan_kepulangan)
                                        <option value="{{ $roster->kota_tujuan_kepulangan }}" selected>
                                            {{ $roster->kota_tujuan_kepulangan }}
                                        </option>
                                        @endif

                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Catatan Penting</label>
                                    <textarea name="catatan_penting_kepulangan" class="form-control" rows="4">{{ old('catatan_penting_kepulangan', $roster->catatan_penting_kepulangan) }}</textarea>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- UPLOAD --}}

                    <div class="mb-2">
                        <label class="form-label fw-semibold">Berkas Pendukung</label>
                        <input type="file" name="berkas_cuti" class="form-control">
                    </div>

                    @if($roster->file)
                    <div class="mb-4">
                        <a href="{{ asset('cuti-roster/'.$roster->nik_karyawan.'/'.$roster->file) }}"
                            target="_blank"
                            class="btn btn-sm btn-outline-primary">
                            Lihat File Lama
                        </a>
                    </div>
                    @endif

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Simpan Pengajuan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>

<script>
    $(function() {

        if ($('.search-airport').length) {
            $('.search-airport').select2({
                width: '100%',
                placeholder: 'Cari bandara...',
                allowClear: true,
                ajax: {
                    url: '/api/airports',
                    dataType: 'json',
                    delay: 250,
                    processResults: function(data) {
                        return {
                            results: $.map(data, function(item) {
                                return {
                                    id: item.name,
                                    text: item.name + ' | ' + item.iata_code
                                }
                            })
                        };
                    },
                    cache: true
                }
            });
        }
    });
</script>

<script>
    function parseDate(id) {
        let el = document.getElementById(id);
        if (!el || !el.value) return null;

        let date = new Date(el.value);
        date.setHours(0, 0, 0, 0); // hindari bug timezone
        return date;
    }

    function hitungHari(mulaiId, akhirId) {
        let mulai = parseDate(mulaiId);
        let akhir = parseDate(akhirId);

        if (mulai && akhir && akhir >= mulai) {
            return ((akhir - mulai) / (1000 * 60 * 60 * 24)) + 1;
        }

        return 0;
    }

    function isOverlap(start1, end1, start2, end2) {
        return start1 <= end2 && end1 >= start2;
    }

    function resetRange(startId, endId) {
        document.getElementById(startId).value = '';
        document.getElementById(endId).value = '';
    }

    function cekTumpangTindih() {

        let rStart = parseDate('mulai_cuti_roster');
        let rEnd = parseDate('akhir_cuti_roster');

        let tStart = parseDate('mulai_cuti_tahunan');
        let tEnd = parseDate('akhir_cuti_tahunan');

        let oStart = parseDate('mulai_off');
        let oEnd = parseDate('akhir_off');

        // Validasi ROSTER vs TAHUNAN
        if (rStart && rEnd && tStart && tEnd) {
            if (isOverlap(rStart, rEnd, tStart, tEnd)) {
                Swal.fire('Perhatian!',
                    'Cuti Tahunan tidak boleh tumpang tindih dengan Cuti Roster!',
                    'warning'
                );
                resetRange('mulai_cuti_tahunan', 'akhir_cuti_tahunan');
                return true;
            }
        }

        // Validasi ROSTER vs OFF
        if (rStart && rEnd && oStart && oEnd) {
            if (isOverlap(rStart, rEnd, oStart, oEnd)) {
                Swal.fire('Perhatian!',
                    'OFF tidak boleh tumpang tindih dengan Cuti Roster!',
                    'warning'
                );
                resetRange('mulai_off', 'akhir_off');
                return true;
            }
        }

        // Validasi TAHUNAN vs OFF
        if (tStart && tEnd && oStart && oEnd) {
            if (isOverlap(tStart, tEnd, oStart, oEnd)) {
                Swal.fire('Perhatian!',
                    'OFF tidak boleh tumpang tindih dengan Cuti Tahunan!',
                    'warning'
                );
                resetRange('mulai_off', 'akhir_off');
                return true;
            }
        }

        return false;
    }

    function updateTotal() {

        if (cekTumpangTindih()) {
            return; // hentikan kalau ada konflik
        }

        let totalRoster = hitungHari('mulai_cuti_roster', 'akhir_cuti_roster');
        let totalTahunan = hitungHari('mulai_cuti_tahunan', 'akhir_cuti_tahunan');
        let totalOff = hitungHari('mulai_off', 'akhir_off');

        document.getElementById('total_cuti_roster').innerText = totalRoster;
        document.getElementById('total_cuti_tahunan').innerText = totalTahunan;
        document.getElementById('total_off').innerText = totalOff;

        let grandTotal = totalRoster + totalTahunan + totalOff;
        document.getElementById('grand_total').innerText = grandTotal + " Hari";
    }

    document.querySelectorAll('input[type="date"]').forEach(function(el) {
        el.addEventListener('change', updateTotal);
    });

    // ROSTER INSENTIF
    function updateInsentifRoster() {

        let totalInsentif = 0;
        let jumlahBekerja = 0;

        // ✅ Field baru: satu, dua, tiga, empat, lima
        let mingguFields = ['satu', 'dua', 'tiga', 'empat', 'lima'];

        mingguFields.forEach(function(field) {
            let el = document.querySelector(`[name="${field}"]`);
            if (el && el.value === "BEKERJA") {
                jumlahBekerja++;
            }
        });

        let selisihHari = hitungHari('tgl_awal_kerja', 'tgl_akhir_kerja');

        totalInsentif = selisihHari;

        let grandTotal = jumlahBekerja + totalInsentif;

        document.getElementById('total_insentif').innerText = totalInsentif;
        document.getElementById('grand_total_insentif').innerText =
            grandTotal + " Hari";
    }

    document.addEventListener('DOMContentLoaded', function() {

        // trigger semua input date
        document.querySelectorAll('input[type="date"]').forEach(function(el) {
            el.addEventListener('change', updateTotal);
        });

        // trigger tanggal insentif
        document.querySelectorAll('#tgl_awal_kerja, #tgl_akhir_kerja')
            .forEach(el => el.addEventListener('change', updateInsentifRoster));

        // trigger select minggu
        document.querySelectorAll('[name="satu"], [name="dua"], [name="tiga"], [name="empat"], [name="lima"]')
            .forEach(el => el.addEventListener('change', updateInsentifRoster));

        // hitung saat pertama load
        updateTotal();
        updateInsentifRoster();
    });
</script>
@endpush
@endsection