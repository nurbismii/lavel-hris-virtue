@extends('layouts.app')
@section('title', 'Proses Payroll')
@push('styles')<link rel="stylesheet" href="{{ versioned_asset('assets/css/payroll.css') }}">@endpush
@section('content')
<div class="container-fluid"><div class="page-inner salary-page payroll-page">
    <header class="salary-header"><div><span class="salary-eyebrow">Administrasi penggajian</span><h1>Proses Payroll</h1><p>Hitung payroll langsung dari data HRIS, lalu tinjau dan simpan arsip final.</p></div><div class="salary-actions"><a class="btn btn-light" href="{{ route('slip-gaji.index') }}">Slip gaji</a><a class="btn btn-outline-primary" href="{{ route('payroll.costs') }}">Laporan beban gaji</a></div></header>
    <section class="salary-card" aria-labelledby="native-payroll-title">
        <div class="salary-card-header"><div><h2 id="native-payroll-title">Hitung payroll dari HRIS</h2><p>Daftar karyawan, gaji, presensi, dan insentif roster diambil otomatis. Cut-off mengikuti tanggal 16 bulan lalu sampai 15 bulan payroll.</p></div><span class="badge bg-info">Gross-up</span></div>
        <div class="salary-card-body">
            @if($nativeReady)
            <form class="payroll-form" data-payroll-submit action="{{ route('payroll.calculate') }}" method="POST">@csrf
                @php($defaultPeriod = max($latestClosedPeriod, config('payroll.valid_from')))
                <div><label for="native-employer" class="form-label">Perusahaan</label><select id="native-employer" name="employer_id" class="form-select" required><option value="">Pilih perusahaan</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(request()->query('employer_id') == $company->id || $companies->count() === 1)>{{ $company->nama_perusahaan }}</option>@endforeach</select></div>
                <div>
                    <label for="native-period" class="form-label">Periode payroll</label>
                    <input id="native-period" name="period" class="form-control" type="month" min="{{ config('payroll.valid_from') }}" max="{{ config('payroll.valid_through') }}" value="{{ is_string(request()->query('period')) ? request()->query('period') : $defaultPeriod }}" aria-describedby="native-period-help" required>
                    <small id="native-period-help">@if($latestClosedPeriod >= config('payroll.valid_from')) Cut-off sudah selesai hingga {{ \Carbon\Carbon::parse($latestClosedPeriod.'-01')->locale('id')->translatedFormat('F Y') }}. @else Belum ada cut-off yang selesai pada tahun payroll yang tersedia. @endif</small>
                </div>
                <div><label for="native-pay-date" class="form-label">Tanggal gajian</label><input id="native-pay-date" name="pay_date" class="form-control" type="date" required></div>
                <div><label for="native-tax-period" class="form-label">Masa pajak terutang</label><input id="native-tax-period" name="tax_period" class="form-control" type="month" min="{{ config('payroll.valid_from') }}" max="{{ config('payroll.valid_through') }}" value="{{ now()->format('Y-m') }}" required><small>Sesuaikan dengan masa saat penghasilan dibayar atau terutang.</small></div>
                <div class="payroll-form-wide d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" data-loading="Menyiapkan perhitungan...">Hitung payroll</button>
                    <button type="button" class="btn btn-outline-primary" data-payroll-prepare="{{ route('payroll.inputs') }}">Siapkan data karyawan</button>
                </div>
            </form>
            <p class="salary-note mt-3 mb-0">Profil pajak dan BPJS yang sudah dikonfirmasi bisa dipakai lagi. Buka isian karyawan bila ada profil baru, lembur, bonus, potongan, atau perubahan data.</p>
            @else
            <p class="mb-0">Penyimpanan payroll HRIS belum disiapkan. Minta admin menerapkan pembaruan database.</p>
            @endif
        </div>
    </section>
    <details class="salary-card" aria-labelledby="payroll-input-title"><summary class="salary-card-header"><strong id="payroll-input-title">Impor Excel untuk data lama atau kasus khusus</strong></summary>
        <div class="salary-card-body">
            <div class="salary-guidance">Gunakan template jika perlu memasukkan nominal yang sudah diverifikasi. Maksimal 10.000 karyawan dan 5 MB. <a href="{{ route('payroll.template') }}">Unduh template Excel</a></div>
            <form class="payroll-form mt-4" data-payroll-submit action="{{ route('payroll.store') }}" method="POST" enctype="multipart/form-data">@csrf
                <div><label for="employer-id" class="form-label">Perusahaan / pemberi kerja</label><select class="form-select" id="employer-id" name="employer_id" required><option value="">Pilih perusahaan</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(old('employer_id') == $company->id)>{{ $company->nama_perusahaan }}</option>@endforeach</select></div>
                <div><label for="period" class="form-label">Periode payroll</label><input class="form-control" id="period" name="period" type="month" min="2026-01" max="2026-12" value="{{ old('period', now()->format('Y-m')) }}" required></div>
                <div><label for="tax-period" class="form-label">Masa pajak terutang</label><input class="form-control" id="tax-period" name="tax_period" type="month" min="2026-01" max="2026-12" value="{{ old('tax_period', now()->format('Y-m')) }}" required><small>Pilih masa pajak sesuai saat penghasilan dibayar/terutang menurut pembukuan.</small></div>
                <div><label for="start-date" class="form-label">Mulai cut-off</label><input class="form-control" id="start-date" name="start_date" type="date" value="{{ old('start_date') }}" required></div>
                <div><label for="end-date" class="form-label">Akhir cut-off</label><input class="form-control" id="end-date" name="end_date" type="date" value="{{ old('end_date') }}" required></div>
                <div><label for="pay-date" class="form-label">Tanggal gajian</label><input class="form-control" id="pay-date" name="pay_date" type="date" value="{{ old('pay_date') }}" required></div>
                <div><label for="payroll-income-source" class="form-label">Sumber penghasilan</label><select class="form-select" id="payroll-income-source" name="income_source" aria-describedby="payroll-source-help"><option value="excel" @selected(old('income_source', 'excel') === 'excel')>Nominal dari Excel</option><option value="hris" @selected(old('income_source') === 'hris')>Hitung komponen dari HRIS</option></select></div>
                <div class="payroll-form-wide" id="payroll-source-help"><p class="salary-note mb-0">Sumber HRIS memakai cut-off 16–15 yang sudah berakhir. Kosongkan H, J, K, N, P, dan X. Tunjangan masa kerja dipisahkan otomatis dari tunjangan tetap. Isi sheet LEMBUR dengan menit yang disahkan HR, dan isi bonus serta komponen lain yang berlaku pada periode ini. Lihat sheet PANDUAN.</p></div>
                <div class="payroll-form-wide"><label for="payroll-file" class="form-label">File komponen dan parameter (.xlsx)</label><input class="form-control" id="payroll-file" name="file" type="file" accept=".xlsx" required><small>Gunakan template. Isi PARAMETER dengan NIK yang sama. File disimpan privat.</small></div>
                <div class="payroll-form-wide"><button class="btn btn-primary" type="submit" data-loading="Mengunggah...">Upload dan hitung payroll</button><p class="salary-note mt-3">Aturan terverifikasi untuk tahun 2026. Pegawai tetap domestik dengan kewajiban subjektif sepanjang tahun; fasilitas DTP/relaksasi, pesangon, dan kasus pajak khusus memerlukan review terpisah.</p></div>
            </form>
        </div>
    </details>
    <section class="salary-card" aria-labelledby="payroll-history-title"><div class="salary-card-header"><div><h2 id="payroll-history-title">Riwayat perhitungan dan arsip final</h2><p>Payroll belum final sampai dikonfirmasi HR. Hasil final disimpan per karyawan dan tidak dapat ditimpa.</p></div></div>
        <div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>Periode / perusahaan</th><th>Status</th><th>Valid / dibaca</th><th>Gagal</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>
            @forelse($imports as $history)
            <tr><td data-label="Periode / perusahaan"><strong>{{ $history->summary['settings']['period'] ?? '-' }}</strong><small>{{ $history->summary['settings']['employer_name'] ?? '-' }}</small></td><td data-label="Status"><span class="badge bg-{{ $history->status_badge_class }}">{{ $history->status_label }}</span></td><td data-label="Valid / dibaca">{{ $history->success_count }} / {{ $history->total_rows }}</td><td data-label="Gagal">{{ $history->failed_count }}</td><td data-label="Dibuat">{{ $history->created_at->format('d M Y H:i') }}</td><td data-label="Aksi"><a class="btn btn-light btn-sm" href="{{ route('payroll.show', $history) }}">{{ $history->status === 'finalized' ? 'Buka arsip' : 'Tinjau hasil' }}</a></td></tr>
            @empty
            <tr><td colspan="6" class="salary-empty">Belum ada perhitungan payroll untuk ditampilkan.</td></tr>
            @endforelse
        </tbody></table></div><div class="salary-card-body">{{ $imports->links() }}</div>
    </section>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/payroll.js') }}"></script>@endpush
