@extends('layouts.app')
@section('title', 'Riwayat Upah')
@section('content')
<div class="container-fluid"><div class="page-inner salary-page">
    <header class="salary-header">
        <div><span class="salary-eyebrow">Administrasi upah</span><h1>Riwayat Upah</h1><p>Sumber gaji pokok dan total tunjangan menurut tanggal berlaku. Master komponen hanya merinci tunjangan tetap tersebut atau mencatat tambahan tidak tetap dan potongan.</p></div>
        <div class="salary-actions"><a class="btn btn-light" href="{{ route('slip-gaji.index') }}">Slip gaji</a><a class="btn btn-primary" href="{{ route('employee-salaries.template') }}">Unduh template XLSX</a></div>
    </header>
    <section class="salary-card" aria-labelledby="upload-title">
        <div class="salary-card-header"><div><h2 id="upload-title">Upload perubahan upah</h2><p>File divalidasi lebih dahulu. Perubahan baru berlaku setelah konfirmasi HR.</p></div><span class="badge bg-info">HR / Super Admin</span></div>
        <div class="salary-card-body">
            <div class="salary-guidance">Gunakan satu sheet, maksimal 10.000 baris / 5 MB. NIK harus berupa teks. Nominal berupa angka tanpa pemisah ribuan; tanggal <strong>YYYY-MM-DD</strong>. Isi <strong>0</strong> untuk tunjangan yang tidak ada. Referensi SK/keputusan wajib diisi.</div>
            <form id="salary-upload" action="{{ route('employee-salaries.store') }}" method="POST" enctype="multipart/form-data" class="salary-upload-form">
                @csrf
                <div><label class="form-label" for="salary-file">File pembaruan gaji (.xlsx)</label><input id="salary-file" name="file" type="file" accept=".xlsx" required class="form-control" aria-describedby="salary-file-help"><small id="salary-file-help">Data awal dari e-payslip juga dapat dimasukkan melalui template setelah komponen upah diperiksa HR.</small></div>
                <button class="btn btn-primary" type="submit">Upload dan validasi</button>
            </form>
            <p class="salary-note mt-3 mb-0">Upload mencatat gaji bulanan, tunjangan tetap, dan tunjangan tidak tetap. Perubahan yang menjangkau periode presensi terkunci harus melalui proses buka ulang periode terlebih dahulu.</p>
        </div>
    </section>
    <section class="salary-card" aria-labelledby="imports-title">
        <div class="salary-card-header"><div><h2 id="imports-title">Riwayat upload</h2><p>Buka hasil validasi untuk meninjau dan mengonfirmasi perubahan.</p></div></div>
        <div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>Diunggah</th><th>Status</th><th>Baris</th><th>Diterapkan</th><th>Gagal</th><th>Aksi</th></tr></thead><tbody>
            @forelse($imports as $import)
            <tr><td data-label="Diunggah">{{ $import->created_at->format('d M Y H:i') }}</td><td data-label="Status"><span class="badge bg-{{ $import->status_badge_class }}">{{ $import->status_label }}</span></td><td data-label="Baris">{{ $import->total_rows }}</td><td data-label="Diterapkan">{{ $import->success_count }}</td><td data-label="Gagal">{{ $import->failed_count }}</td><td data-label="Aksi"><a class="btn btn-sm btn-light" href="{{ route('employee-salaries.show', $import) }}">Tinjau upload</a></td></tr>
            @empty
            <tr><td colspan="6" class="salary-empty">Belum ada upload. Unduh template untuk mulai menambahkan riwayat upah.</td></tr>
            @endforelse
        </tbody></table></div><div class="salary-card-body">{{ $imports->links() }}</div>
    </section>
    <section class="salary-card" aria-labelledby="salaries-title">
        <div class="salary-card-header"><div><h2 id="salaries-title">Riwayat upah disetujui</h2><p>Versi lama tetap tersimpan. Tanggal efektif dan versi persetujuan menentukan upah yang digunakan.</p></div></div>
        <div class="salary-card-body"><form method="GET" class="salary-filter"><div><label class="form-label" for="salary-nik">Filter NIK karyawan</label><input class="form-control" id="salary-nik" name="nik" value="{{ request('nik') }}" maxlength="100" placeholder="Masukkan NIK lengkap"></div><button class="btn btn-light" type="submit">Tampilkan</button><a href="{{ route('employee-salaries.index') }}" class="btn btn-link">Reset</a></form></div>
        <div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>Karyawan</th><th>Berlaku mulai</th><th>Gaji pokok</th><th>Tunjangan tetap</th><th>Tidak tetap</th><th>Alasan / referensi</th></tr></thead><tbody>
            @forelse($salaries as $salary)
            <tr><td data-label="Karyawan"><strong>{{ optional($salary->employee)->nama_karyawan ?: $salary->employee_nik }}</strong><small>{{ $salary->employee_nik }} · Versi #{{ $salary->id }}</small></td><td data-label="Berlaku mulai">{{ $salary->effective_from->format('d M Y') }}</td><td data-label="Gaji pokok">Rp {{ number_format((float) $salary->basic_salary, 0, ',', '.') }}</td><td data-label="Tunjangan tetap">Rp {{ number_format((float) $salary->fixed_allowance, 0, ',', '.') }}</td><td data-label="Tidak tetap">Rp {{ number_format((float) $salary->non_fixed_allowance, 0, ',', '.') }}</td><td data-label="Alasan / referensi">{{ $salary->reason }}<small>{{ $salary->reference }} · Disetujui {{ $salary->approved_at->format('d M Y H:i') }}</small></td></tr>
            @empty
            <tr><td colspan="6" class="salary-empty">Belum ada riwayat upah untuk filter ini. Upload dan konfirmasi data awal atau perubahan gaji.</td></tr>
            @endforelse
        </tbody></table></div><div class="salary-card-body">{{ $salaries->links() }}</div>
    </section>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/employee-salaries.js') }}"></script>@endpush
