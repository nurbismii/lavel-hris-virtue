<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hasil Perhitungan Pesangon</title>
    <link rel="stylesheet" href="{{ versioned_asset('assets/css/severance-print.css') }}">
</head>

<body class="{{ $entries->count() > 1 ? 'batch-print' : 'single-print' }}">
    <nav class="print-toolbar" aria-label="Tindakan dokumen"><a href="{{ route('severance.batch', $history) }}">Kembali ke batch</a><button type="button" id="print-results">Cetak / simpan PDF</button><span>{{ $entries->count() }} pengajuan final @if($entries->count() > 1) · 4 per lembar A4 (2 × 2). Pilih 1 halaman per lembar pada dialog cetak.@endif</span></nav>
    <main>
    @foreach($entries->chunk(4) as $page)
    <section class="print-page" aria-label="Lembar {{ $loop->iteration }}">
    @foreach($page as $entry)
    @php($result = $entry->calculation)
    @php($salary = $entry->salary_snapshot)
    <article class="print-sheet">
        <div class="print-content">
        <header>
            <p>V-People · Hubungan Industrial</p>
            <h1>PERHITUNGAN PESANGON / KOMPENSASI</h1>
            <p>Pengajuan #{{ $entry->id }} · Batch #{{ $history->id }} · Pasal {{ $entry->article }}</p>
        </header>
        <div class="print-columns">
            <dl>
                <dt>Nama</dt>
                <dd>{{ $entry->employee_name }}</dd>
                <dt>NIK</dt>
                <dd>{{ $entry->employee_nik }}</dd>
                <dt>Tanggal masuk</dt>
                <dd>{{ $entry->entry_date->format('d M Y') }}</dd>
                <dt>Awal perhitungan</dt>
                <dd>{{ $entry->calculation_start->format('d M Y') }}</dd>
                <dt>Akhir kerja</dt>
                <dd>{{ $entry->termination_date->format('d M Y') }}</dd>
                <dt>Masa perhitungan</dt>
                <dd>{{ $result['years'] }} tahun {{ $result['months'] }} bulan {{ $result['days'] }} hari</dd>
            </dl>
            <dl>
                <dt>Gaji pokok</dt>
                <dd>Rp {{ number_format($salary['basic_salary'], 2, ',', '.') }}</dd>
                <dt>Tunjangan jabatan</dt>
                <dd>Rp {{ number_format($salary['position_allowance'], 2, ',', '.') }}</dd>
                <dt>Tunjangan masa kerja</dt>
                <dd>Rp {{ number_format($salary['tenure_allowance'], 2, ',', '.') }}</dd>
                <dt>Total upah</dt>
                <dd>Rp {{ number_format($result['wage'], 2, ',', '.') }}</dd>
                <dt>Sisa cuti disahkan HI</dt>
                <dd>{{ $entry->leave_days }} hari</dd>
                <dt>Sumber upah</dt>
                <dd>V Payslip FULL {{ $salary['period'] }}</dd>
            </dl>
        </div>
        <p><strong>Alasan:</strong> {{ $entry->reason }}</p>
        @include('admin.severance.result', ['result' => $result])
        <div class="print-approvals">@foreach(['payroll_approved' => 'Disetujui Supervisor Payroll', 'hi_approved' => 'Disetujui Supervisor HI'] as $action => $label)
            @php($decision = collect($entry->decisions)->where('action', $action)->last())
            <div><strong>{{ $label }}</strong>
                <p>{{ $decision['actor'] ?? '—' }}</p><small>{{ \Carbon\Carbon::parse($decision['at'])->timezone('Asia/Makassar')->format('d M Y H:i') }} WITA</small>
            </div>@endforeach
        </div>
        <footer>Nilai bruto sebelum pajak. Dasar penggantian cuti: gaji pokok ÷ 25 × sisa cuti. Versi aturan: {{ $result['rules_version'] }}.</footer>
        </div>
    </article>
    @endforeach
    </section>
    @endforeach
    </main>
    <script src="{{ versioned_asset('assets/js/severance-print.js') }}"></script>
</body>

</html>
