@extends('layouts.app')
@section('title', 'Isian Payroll Karyawan')
@push('styles')<link rel="stylesheet" href="{{ versioned_asset('assets/css/payroll.css') }}">@endpush
@section('content')
@php
    $scope = ['employer_id'=>$company->id, 'period'=>$period];
    $monthly = $record['current']?->data ?? [];
    $profile = $record['profile'];
    $recurring = $record['recurring'];
    $verify = !$profile || (int) $profile['salary_history_id'] !== (int) $salary?->id;
    $wage = $salary ? round($salary->basic_salary + $salary->fixed_allowance) : null;
    $payFields = \App\Services\Payroll\PayrollInputs::PAY_FIELDS;
    $common = array_intersect_key($payFields, array_flip(['W','AA','AB','AC']));
    $ptkp = strtoupper(trim((string) $employee->status_pajak));
@endphp
<div class="container-fluid"><div class="page-inner salary-page payroll-page">
    <header class="salary-header"><div><span class="salary-eyebrow">{{ $company->nama_perusahaan }} · {{ $period }}</span><h1>Isian payroll karyawan</h1><p>{{ $employee->nama_karyawan }} · {{ $employee->nik }}</p></div><a class="btn btn-light" href="{{ route('payroll.inputs', $scope) }}">Kembali ke daftar</a></header>
    <form data-payroll-submit method="POST" action="{{ route('payroll.inputs.save', ['nik'=>$employee->nik]) }}">
        @csrf
        <input type="hidden" name="employer_id" value="{{ $company->id }}"><input type="hidden" name="period" value="{{ $period }}"><input type="hidden" name="revision" value="{{ $record['current']?->id ?? 0 }}">

        <section class="salary-card"><div class="salary-card-header"><h2>Upah yang sudah tercatat</h2><a href="{{ route('employee-salaries.index') }}">Riwayat Upah</a></div><div class="salary-card-body">
            @if($salary)
            <div class="payroll-form"><div><strong>Gaji pokok</strong><p>Rp {{ number_format($salary->basic_salary, 0, ',', '.') }}</p></div><div><strong>Total tunjangan tetap</strong><p>Rp {{ number_format($salary->fixed_allowance, 0, ',', '.') }}</p></div>
                @if($fixedParts)<div><strong>Batas rincian tetap dari master</strong><p>Rp {{ number_format($fixedParts['other_fixed_allowance'], 0, ',', '.') }}</p><small>Setelah tunjangan masa kerja Rp {{ number_format($fixedParts['tenure_allowance'], 0, ',', '.') }} yang dihitung otomatis.</small></div>@endif
            </div>
            @endif
            @if($fixedError)<p class="text-danger">{{ $fixedError }}</p>@elseif(!$fixedParts)<p class="text-warning">Lengkapi Riwayat Upah dan tanggal masuk sebelum merinci tunjangan tetap.</p>@endif
            <p class="salary-note">Nominal tetap dari master hanya merinci total di atas dan tidak menambah pembayaran. Sisa tunjangan tetap tetap dibayar dari Riwayat Upah.</p>
        </div></section>

        <details class="salary-card" @if($verify) open @endif><summary class="salary-card-header"><strong>Profil pajak dan BPJS</strong><span class="badge bg-{{ $verify ? 'warning' : 'success' }}">{{ $verify ? 'Perlu konfirmasi' : 'Tersimpan' }}</span></summary>
            <div class="salary-card-body">
                @if(!$salary)<p class="text-danger">Riwayat Upah yang berlaku pada awal bulan belum tersedia.</p>@endif
                <p>Konfirmasi saat pertama kali menyiapkan profil atau saat data berubah. Profil dapat dipakai untuk periode berikutnya pada tahun yang sama.</p>
                <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="verify-profile" name="verify_profile" value="1" @checked($verify)><label for="verify-profile" class="form-check-label">Simpan dan konfirmasi profil untuk periode ini</label></div>
                <fieldset class="payroll-form" data-payroll-profile @disabled(!$verify)>
                    <div><label class="form-label" for="profile-ptkp">Status PTKP awal tahun</label><select class="form-select" id="profile-ptkp" name="profile[ptkp]"><option value="">Pilih PTKP</option>@foreach(array_keys(config('payroll.ptkp')) as $option)<option value="{{ $option }}" @selected(($profile['ptkp'] ?? $ptkp) === $option)>{{ $option }}</option>@endforeach</select></div>
                    <div><label class="form-label" for="profile-start">Bulan mulai pajak pada pemberi kerja ini</label><select class="form-select" id="profile-start" name="profile[bulan_mulai_pajak]"><option value="">Pilih bulan</option>@foreach(range(1, 12) as $month)<option value="{{ $month }}" @selected(($profile['bulan_mulai_pajak'] ?? ($employee->entry_date?->year < (int) substr($period, 0, 4) && $employee->entry_date ? 1 : $employee->entry_date?->month)) == $month)>{{ \Carbon\Carbon::create(null, $month, 1)->translatedFormat('F') }}</option>@endforeach</select></div>
                    <div><label class="form-label" for="profile-jkk">Tarif JKK terdaftar</label><select class="form-select" id="profile-jkk" name="profile[jkk_persen]"><option value="">Pilih tarif</option>@foreach(config('payroll.jkk_rates') as $rate)<option value="{{ $rate / 100 }}" @selected(isset($profile['jkk_persen']) && $profile['jkk_persen'] == $rate / 100)>{{ number_format($rate / 100, 2, ',', '.') }}%</option>@endforeach</select></div>
                    @foreach(\App\Services\Payroll\PayrollInputs::BPJS_AMOUNTS as $key=>$label)
                        <div><label class="form-label" for="profile-{{ $key }}">{{ $label }} (Rp)</label><input class="form-control" id="profile-{{ $key }}" name="profile[{{ $key }}]" type="number" min="0" max="100000000000" value="{{ $profile[$key] ?? ($key === 'upah_minimum' ? '' : $wage) }}"></div>
                    @endforeach
                    @foreach(\App\Services\Payroll\PayrollInputs::BPJS_PROGRAMS as $key=>$label)
                        <div><label class="form-label" for="profile-{{ $key }}">{{ $label }}</label><select class="form-select" id="profile-{{ $key }}" name="profile[{{ $key }}]"><option value="">Pilih status</option><option value="1" @selected((string) ($profile[$key] ?? '') === '1')>Aktif</option><option value="0" @selected((string) ($profile[$key] ?? '') === '0')>Tidak berlaku</option></select></div>
                    @endforeach
                    <div><label class="form-label" for="profile-family">Keluarga tambahan BPJS Kesehatan</label><input class="form-control" id="profile-family" name="profile[keluarga_tambahan]" type="number" min="0" max="10" value="{{ $profile['keluarga_tambahan'] ?? 0 }}"></div>
                    <div class="payroll-form-wide form-check"><input class="form-check-input" id="profile-verified" name="profile[nik_npwp_valid]" type="checkbox" value="1"><label class="form-check-label" for="profile-verified">Saya telah memeriksa NIK/NPWP, status pegawai tetap dalam negeri sepanjang tahun, fasilitas pajak normal, serta kepesertaan dan upah yang dilaporkan ke BPJS.</label></div>
                </fieldset>
            </div>
        </details>

        <section class="salary-card" aria-labelledby="adjustments-title"><div class="salary-card-header"><h2 id="adjustments-title">Bonus dan potongan periode ini</h2></div><div class="salary-card-body">
            <p>Pemasukan di bagian ini khusus tambahan tidak tetap di luar upah. Rincian tunjangan tetap dicatat pada komponen master bertipe Tetap. Isian periode ini menggantikan nominal berulang, termasuk jika diisi 0.</p>
            <div class="payroll-form">
                @foreach($common as $key=>$label)
                    <div>
                        <label class="form-label" for="component-{{ $key }}">{{ $label }} (Rp)</label>
                        <input class="form-control" id="component-{{ $key }}" name="components[{{ $key }}]" type="number" min="0" max="100000000000" value="{{ $monthly['components'][$key] ?? '' }}" @if($key === 'W') aria-describedby="bonus-help" @endif>
                        @if($key === 'W')
                            <small id="bonus-help">HRD mengisi nominal dari rekap departemen untuk karyawan dan periode ini. Kosongkan bila tidak ada bonus.</small>
                        @endif
                    </div>
                @endforeach
            </div>
            <details class="mt-3"><summary class="fw-semibold">Tunjangan dan pembayaran lain</summary><div class="payroll-form mt-3">
                @foreach(array_diff_key($payFields, $common) as $key=>$label)<div><label class="form-label" for="component-{{ $key }}">{{ $label }} (Rp)</label><input class="form-control" id="component-{{ $key }}" name="components[{{ $key }}]" type="number" min="0" max="100000000000" value="{{ $monthly['components'][$key] ?? '' }}"></div>@endforeach
            </div></details>
        </div></section>

        <section class="salary-card"><div class="salary-card-header"><h2>Rincian komponen dari master</h2><a href="{{ route('salary-components.index') }}">Kelola master</a></div><div class="salary-card-body">
            <p><strong>Pemasukan tetap sudah termasuk Riwayat Upah dan tidak menambah gaji lagi.</strong> Rincian tetap tidak boleh melebihi total tunjangan tetap setelah masa kerja. Pemasukan tidak tetap dan potongan merupakan tambahan periode ini. Isian periode ini menggantikan nilai berulang, termasuk 0.</p>
            <div class="payroll-form">
                @forelse($customComponents as $component)
                <div><label class="form-label" for="custom-{{ $component->id }}">{{ $component->code }} · {{ $component->name }} (Rp)</label><input class="form-control" id="custom-{{ $component->id }}" name="custom[{{ $component->id }}]" type="number" min="0" max="100000000000" value="{{ $monthly['custom'][$component->id]['amount'] ?? '' }}"><small>{{ $component->type === 'deduction' ? 'Potongan kas setelah pajak' : ($component->fixed ? 'Rincian tunjangan tetap; sudah termasuk upah' : 'Pemasukan tambahan tidak tetap') }}@if($component->fixed) · Berulang saat ini: Rp {{ number_format($record['custom_recurring'][$component->id]['amount'] ?? 0, 0, ',', '.') }}@endif</small></div>
                @empty<p>Belum ada komponen tambahan aktif.</p>@endforelse
            </div>
            @if($customComponents->where('fixed', true)->isNotEmpty())
            <details class="mt-3"><summary>Pengaturan komponen berulang</summary><p>Rincian pemasukan tetap tetap menjadi bagian dari Riwayat Upah; potongan tetap mengurangi kas. Berlaku dalam tahun yang sama. Kosongkan atau isi 0 untuk menghentikan rincian/potongan berulang.</p>
                <div class="form-check"><input class="form-check-input" id="save-custom-recurring" name="save_custom_recurring" type="checkbox" value="1"><label class="form-check-label" for="save-custom-recurring">Ubah nilai berulang komponen tambahan</label></div>
                <fieldset class="payroll-form mt-3" data-custom-recurring disabled>
                    @foreach($customComponents->where('fixed', true) as $component)<div><label class="form-label" for="custom-recurring-{{ $component->id }}">{{ $component->name }} berulang (Rp)</label><input class="form-control" id="custom-recurring-{{ $component->id }}" name="custom_recurring[{{ $component->id }}]" type="number" min="0" max="100000000000" value="{{ $record['custom_recurring'][$component->id]['amount'] ?? '' }}"></div>@endforeach
                </fieldset>
            </details>
            @endif
        </div></section>
        <details class="salary-card"><summary class="salary-card-header"><strong>Tunjangan dan potongan berulang</strong></summary><div class="salary-card-body">
            <p>Nominal berlaku mulai periode ini sampai diperbarui dalam tahun payroll yang sama. Masukkan hanya komponen di luar tunjangan tetap yang sudah tercatat di Riwayat Upah.</p>
            <div class="form-check mb-3"><input class="form-check-input" id="save-recurring" name="save_recurring" type="checkbox" value="1"><label class="form-check-label" for="save-recurring">Ubah pengaturan berulang mulai periode ini</label></div>
            <fieldset class="payroll-form" data-payroll-recurring disabled>
                @foreach(\App\Services\Payroll\PayrollInputs::RECURRING_KEYS as $key)
                    <div><label class="form-label" for="recurring-{{ $key }}">{{ $payFields[$key] }} (Rp)</label><input class="form-control" id="recurring-{{ $key }}" name="recurring[{{ $key }}]" type="number" min="0" max="100000000000" value="{{ $recurring[$key] ?? '' }}"></div>
                @endforeach
            </fieldset>
            <p class="salary-note mt-2 mb-0">Saat menyimpan pengaturan ini, kosongkan komponen yang ingin dihentikan.</p>
        </div></details>

        <details class="salary-card" @if($orders->isNotEmpty()) open @endif><summary class="salary-card-header"><strong>Realisasi lembur</strong><span>{{ $orders->count() }} perintah diterima</span></summary><div class="salary-card-body">
            @if($orders->isEmpty())<p>Tidak ada perintah yang diterima dalam cut-off ini.</p>@else
            <p>Isi menit kerja yang disahkan setelah memeriksa realisasi. Isi 0 bila nihil.</p>
            <div class="payroll-form">
                @foreach($orders as $order)<div><label class="form-label" for="overtime-{{ $order->id }}">{{ $order->overtime_date->format('d/m/Y') }} · #{{ $order->id }}</label><input class="form-control" id="overtime-{{ $order->id }}" name="minutes[{{ $order->id }}]" type="number" min="0" max="{{ $order->required_minutes ?? 1440 }}" value="{{ $monthly['minutes'][$order->id] ?? '' }}" aria-describedby="order-help-{{ $order->id }}"><small id="order-help-{{ $order->id }}">{{ $order->time_range_text }} · Perintah {{ $order->required_minutes }} menit</small></div>@endforeach
            </div>
            @endif
            <details class="mt-3" @if(isset($monthly['manual_overtime'])) open @endif><summary class="fw-semibold">Nominal lembur khusus</summary><div class="payroll-form mt-3">
                <div><label class="form-label" for="manual-overtime">Total lembur yang disahkan (Rp)</label><input class="form-control" id="manual-overtime" name="manual_overtime" type="number" min="0" max="100000000000" value="{{ $monthly['manual_overtime'] ?? '' }}"><small>Kosongkan menit per perintah jika memakai nominal khusus.</small></div>
                <div><label class="form-label" for="overtime-note">Alasan lembur khusus</label><input class="form-control" id="overtime-note" name="overtime_note" maxlength="255" value="{{ $monthly['overtime_note'] ?? '' }}"></div>
            </div></details>
        </div></details>

        <details class="salary-card"><summary class="salary-card-header"><strong>Pajak masa terakhir dan data tambahan</strong></summary><div class="salary-card-body">
            <div class="form-check mb-3"><input class="form-check-input" id="last-tax" name="tax[masa_terakhir]" type="checkbox" value="1" @checked(!empty($monthly['tax']['masa_terakhir']))><label class="form-check-label" for="last-tax">Ini masa pajak terakhir pada pemberi kerja ini</label></div>
            <div class="payroll-form">
                @foreach(\App\Services\Payroll\PayrollInputs::MONTHLY_TAX as $key=>$label)<div><label class="form-label" for="tax-{{ $key }}">{{ $label }} (Rp)</label><input class="form-control" id="tax-{{ $key }}" name="tax[{{ $key }}]" type="number" min="0" max="100000000000" value="{{ $monthly['tax'][$key] ?? '' }}"></div>@endforeach
            </div>
            <p class="mt-3">Untuk masa terakhir atau Desember, isi saldo aktual sebelum masa pajak ini jika arsip payroll HRIS belum lengkap.</p>
            <div class="payroll-form">
                @foreach(\App\Services\Payroll\PayrollInputs::YTD_FIELDS as $key=>$label)<div><label class="form-label" for="tax-{{ $key }}">{{ $label }} sebelum masa ini (Rp)</label><input class="form-control" id="tax-{{ $key }}" name="tax[{{ $key }}]" type="number" min="0" max="1000000000000" value="{{ $monthly['tax'][$key] ?? '' }}"></div>@endforeach
            </div>
        </div></details>

        <details class="salary-card" @if(!empty($monthly['separate_reason'])) open @endif><summary class="salary-card-header"><strong>Proses karyawan secara terpisah</strong></summary><div class="salary-card-body">
            <label class="form-label" for="separate-reason">Alasan pemrosesan terpisah</label><input class="form-control" id="separate-reason" name="separate_reason" maxlength="255" value="{{ $monthly['separate_reason'] ?? '' }}"><p class="salary-note mt-2 mb-0">Isi hanya untuk kasus yang ditangani terpisah, misalnya prorata. Kosongkan agar karyawan ikut dihitung pada periode ini.</p>
        </div></details>
        <button class="btn btn-primary" type="submit" data-loading="Menyimpan...">Simpan data payroll</button>
    </form>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/payroll.js') }}"></script>@endpush
