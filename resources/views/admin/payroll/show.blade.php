@extends('layouts.app')
@section('title', 'Tinjau Payroll')
@push('styles')<link rel="stylesheet" href="{{ versioned_asset('assets/css/payroll.css') }}">@endpush
@section('content')
@php($settings = $history->summary['settings'] ?? [])
@php($native = $history->source === \App\Models\ImportHistory::SOURCE_HRIS)
@php($scope = array_intersect_key($settings, array_flip(['employer_id','period'])))
<div class="container-fluid"><div class="page-inner salary-page payroll-page">
    <header class="salary-header"><div><span class="salary-eyebrow">Payroll {{ $settings['period'] ?? '' }}</span><h1>{{ $history->status === 'finalized' ? 'Arsip Payroll Final' : 'Tinjau Payroll' }}</h1><p>{{ $settings['employer_name'] ?? '' }} · Masa pajak {{ $settings['tax_period'] ?? '' }} · Gross-up</p></div><a class="btn btn-light" href="{{ route('payroll.index') }}">Kembali ke payroll</a></header>
    <section class="salary-card" id="payroll-process" data-initial-status="{{ $history->status }}" data-status-url="{{ route('payroll.status', $history) }}" aria-labelledby="process-title"><div class="salary-card-header"><div><h2 id="process-title">Status perhitungan</h2><p>Cut-off {{ $settings['start_date'] ?? '-' }} sampai {{ $settings['end_date'] ?? '-' }} · Gajian {{ $settings['pay_date'] ?? '-' }}</p></div><span id="payroll-status" class="badge bg-{{ $history->status_badge_class }}">{{ $history->status_label }}</span></div><div class="salary-card-body">
        <p id="payroll-progress" role="status" aria-live="polite">{{ $history->total_rows }} baris dibaca · {{ $history->success_count }} valid · {{ $history->failed_count }} gagal.</p><p id="payroll-error" class="text-danger">{{ $history->error_message }}</p><button id="payroll-refresh" type="button" class="btn btn-light">Refresh status</button>
        <p class="salary-note mt-3">Dibuat {{ $history->created_at->format('d M Y H:i') }} @if($history->started_at) · Mulai {{ $history->started_at->format('d M Y H:i') }} @endif @if($history->finished_at) · Selesai {{ $history->finished_at->format('d M Y H:i') }} @endif</p>
        @if(!empty($history->summary['separate_count']))<p>{{ $history->summary['separate_count'] }} karyawan ditandai untuk diproses terpisah.</p>@endif
        @if($native && !in_array($history->status, ['queued','processing','finalized'], true))
        <form class="d-flex flex-wrap gap-2" data-payroll-submit method="POST" action="{{ route('payroll.calculate') }}">@csrf
            @foreach(array_intersect_key($settings, array_flip(['employer_id','period','pay_date','tax_period'])) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <a class="btn btn-light" href="{{ route('payroll.inputs', $scope) }}">Siapkan data karyawan</a><button type="submit" class="btn btn-outline-primary" data-loading="Menyiapkan perhitungan...">Hitung ulang</button>
        </form>
        @endif
        @if($history->status === 'finalized')<p class="mt-3 mb-2">Difinalisasi {{ $history->confirmed_at->format('d M Y H:i') }} · Penyetuju {{ $history->confirmed_by }}</p><a class="btn btn-primary" href="{{ route('payroll.download', $history) }}">Unduh Excel final</a>@endif
    </div></section>
    @if(isset($history->summary['totals']))
    <div class="payroll-totals">
        @foreach(['basic_salary'=>'Gaji pokok','other_fixed_allowance'=>'Tunjangan tetap lain','meal'=>'Uang makan','tenure'=>'Tunjangan masa kerja','roster_incentive'=>'Insentif roster','bonus'=>'Bonus','cash_income'=>'Penghasilan kas setelah unpaid/alpa','overtime'=>'Total lembur','tax_allowance'=>'Tunjangan pajak','pph21'=>'PPh 21','employee_bpjs'=>'BPJS karyawan','employer_bpjs'=>'BPJS perusahaan','take_home'=>'Total diterima','employer_cost'=>'Kas payroll + BPJS perusahaan'] as $key => $label)
        @continue(!array_key_exists($key, $history->summary['totals']))
        <div class="salary-card"><div class="salary-card-body"><span class="salary-note">{{ $label }}</span><strong class="payroll-amount">Rp {{ number_format($history->summary['totals'][$key], 0, ',', '.') }}</strong></div></div>
        @endforeach
    </div>
    @if($history->failed_count)<p class="text-danger">Ringkasan hanya mencakup baris valid. Payroll belum dapat difinalisasi.</p>@endif
    @endif
    @if($history->status === 'awaiting_confirmation')
    <section class="salary-card"><div class="salary-card-body">
        <form data-payroll-submit data-confirm="true" method="POST" action="{{ route('payroll.finalize', $history) }}">@csrf
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="payroll-reviewed" name="reviewed" value="1" required><label class="form-check-label" for="payroll-reviewed">Saya telah memeriksa seluruh karyawan, realisasi lembur, komponen upah, status PTKP, kepesertaan BPJS, fasilitas pajak, serta saldo YTD bila masa terakhir. Hasil ini disetujui sebagai arsip final.</label></div>
            <button class="btn btn-primary" type="submit" data-loading="Memfinalisasi...">Finalisasi {{ $history->total_rows }} karyawan</button><p class="salary-note mt-3">Pratinjau berlaku sampai {{ $history->expires_at->format('d M Y H:i') }}. Finalisasi mengunci arsip; pembayaran bank dan pelaporan pajak/BPJS dilakukan melalui proses perusahaan.</p>
        </form>
    </div></section>
    @endif
    <section class="salary-card"><div class="salary-card-header"><div><h2>Rincian per karyawan</h2><p>50 baris per halaman. Buka rincian untuk melihat dasar upah dan perhitungan pajak.</p></div></div><div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>Baris / karyawan</th><th>Tunjangan pajak</th><th>PPh 21</th><th>BPJS karyawan</th><th>Diterima</th><th>Hasil / rincian</th></tr></thead><tbody>
        @forelse($items as $item)
        @php($p = $item->payload ?: []) @php($r = $p['result'] ?? null)
        <tr><td data-label="Baris / karyawan"><strong>#{{ $item->row_number }} · {{ $item->nik }}</strong><small>{{ $item->employee_name }}</small></td>
            @foreach(['tax_allowance','pph21','employee_bpjs','take_home'] as $key)<td data-label="{{ ['tax_allowance'=>'Tunjangan pajak','pph21'=>'PPh 21','employee_bpjs'=>'BPJS karyawan','take_home'=>'Diterima'][$key] }}">{{ $r ? number_format($r[$key],0,',','.') : 'Belum dihitung' }}</td>@endforeach
            <td data-label="Hasil / rincian"><span class="{{ $item->category === 'failed' ? 'text-danger' : 'text-muted' }}">{{ $item->message }}</span>
                @if($native && $item->category === 'failed')<a class="d-block mt-2" href="{{ route('payroll.inputs.edit', ['nik'=>$item->nik] + $scope) }}">Buka isian {{ $item->employee_name ?? $item->nik }}</a>@endif
                @if(!empty($p['custom_components']))
                <details class="payroll-detail"><summary>Rincian komponen master</summary><dl>
                    @foreach($p['custom_components'] as $component)<dt>{{ $component['code'] }} · {{ $component['name'] }}</dt><dd>{{ !empty($component['included_in_wage']) ? 'Sudah termasuk tunjangan tetap dalam upah' : ($component['type'] === 'income' ? 'Pemasukan tambahan' : 'Potongan') }} · Rp {{ number_format($component['amount'], 0, ',', '.') }}</dd>@endforeach
                </dl></details>
                @endif
                @if(isset($p['hris']))
                <details class="payroll-detail"><summary>Komponen dari HRIS</summary><dl>
                    <dt>Gaji pokok</dt><dd>Rp {{ number_format($p['hris']['basic_salary'], 0, ',', '.') }}</dd>
                    <dt>Tunjangan tetap lain</dt><dd>Rp {{ number_format($p['hris']['other_fixed_allowance'], 0, ',', '.') }}</dd>
                    <dt>Masa kerja</dt><dd>{{ $p['hris']['tenure_years'] }} tahun penuh · Rp {{ number_format($p['columns']['N'], 0, ',', '.') }}</dd>
                    <dt>Uang makan</dt><dd>{{ count($p['hris']['meal_attendance']) }} hari hadir · Rp {{ number_format($p['columns']['K'], 0, ',', '.') }}</dd>
                    <dt>Insentif roster</dt><dd>{{ count($p['hris']['roster_incentive']) }} hari · Rp {{ number_format($p['columns']['X'], 0, ',', '.') }}</dd>
                    @foreach($p['hris']['recurring_components'] ?? [] as $key=>$amount)
                        <dt>{{ \App\Services\Payroll\PayrollInputs::PAY_FIELDS[$key] }} berulang</dt><dd>Rp {{ number_format($amount, 0, ',', '.') }}</dd>
                    @endforeach
                    <dt>Lembur</dt><dd>@if(isset($p['hris']['overtime_note'])) Nominal khusus · {{ $p['hris']['overtime_note'] }} @else {{ array_sum(array_column($p['hris']['overtime'], 'minutes')) }} menit disahkan @endif · Rp {{ number_format($p['columns']['P'], 0, ',', '.') }}</dd>
                    @foreach($p['hris']['overtime'] as $order)
                        @foreach($order['warnings'] ?? [] as $warning)<dt>Perintah #{{ $order['order_id'] }}</dt><dd>{{ $warning }}</dd>@endforeach
                    @endforeach
                </dl></details>
                @endif
                @if($r)<details class="payroll-detail"><summary>Dasar perhitungan</summary><dl><dt>PTKP / metode</dt><dd>{{ $p['input']['ptkp'] }} · {{ $r['tax_detail']['method'] }} @if(isset($r['tax_detail']['rate_bps'])) ({{ $r['tax_detail']['rate_bps'] / 100 }}%) @endif</dd><dt>Bruto pajak termasuk gross-up</dt><dd>Rp {{ number_format($r['tax_gross'],0,',','.') }}</dd><dt>Dasar BPJS TK / Kes / JP</dt><dd>{{ number_format($r['bpjs']['tk_basis'],0,',','.') }} / {{ number_format($r['bpjs']['health_basis'],0,',','.') }} / {{ number_format($r['bpjs']['jp_basis'],0,',','.') }}</dd><dt>JHT / JP / Kes / keluarga (karyawan)</dt><dd>{{ number_format($r['bpjs']['employee_jht'],0,',','.') }} / {{ number_format($r['bpjs']['employee_jp'],0,',','.') }} / {{ number_format($r['bpjs']['employee_health'],0,',','.') }} / {{ number_format($r['bpjs']['extra_family'],0,',','.') }}</dd><dt>JKK / JKM / total BPJS perusahaan</dt><dd>{{ number_format($r['bpjs']['employer_jkk'],0,',','.') }} / {{ number_format($r['bpjs']['employer_jkm'],0,',','.') }} / {{ number_format($r['employer_bpjs'],0,',','.') }}</dd>@if($r['last_period'])<dt>Bruto setahun / biaya jabatan / PKP</dt><dd>{{ number_format($r['tax_detail']['annual_gross'],0,',','.') }} / {{ number_format($r['tax_detail']['job_expense'],0,',','.') }} / {{ number_format($r['tax_detail']['pkp'],0,',','.') }}</dd><dt>PPh setahun / sudah dipotong</dt><dd>{{ number_format($r['tax_detail']['tax'],0,',','.') }} / {{ number_format($r['tax_detail']['previous_tax'],0,',','.') }}</dd>@endif</dl><small>Aturan {{ $r['rules_version'] }}. PPh negatif dikembalikan kepada karyawan.</small></details>@endif
            </td></tr>
        @empty
        <tr><td colspan="6" class="salary-empty">Belum ada rincian perhitungan untuk ditampilkan.</td></tr>
        @endforelse
    </tbody></table></div><div class="salary-card-body">{{ $items->links() }}</div></section>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/payroll.js') }}"></script>@endpush
