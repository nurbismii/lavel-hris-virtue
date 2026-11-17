@extends('layouts.app')
@section('title', 'Pesangon & Kompensasi')
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/severance.css') }}">@endpush
@section('content')
<div class="container-fluid">
    <div class="page-inner salary-page severance-page">
        <header class="salary-header">
            <div><span class="salary-eyebrow">Hubungan industrial</span>
                <h1>Pesangon & Kompensasi</h1>
                <p>Ajukan sekali, verifikasi upah oleh Payroll, lalu persetujuan akhir oleh HI.</p>
            </div>
        </header>
        <ol class="severance-steps" aria-label="Alur pengajuan">
            <li><b>1</b> Staf HI mengajukan</li>
            <li><b>2</b> Supervisor Payroll memeriksa upah FULL</li>
            <li><b>3</b> Supervisor HI mengisi cuti dan menyetujui</li>
            <li><b>4</b> Staf HI mencetak / export</li>
        </ol>
        @if(!$ready)
        <div class="alert alert-warning">Penyimpanan pesangon belum tersedia. Minta admin menerapkan migrasi modul pesangon.</div>
        @else
        @if(auth()->user()->hasMenuAccess('severance_staff'))
        <section class="salary-card" aria-labelledby="upload-title">
            <div class="salary-card-header">
                <div>
                    <h2 id="upload-title">Pengajuan baru</h2>
                    <p>Gunakan file pengajuan lama atau template ini. Nominal gaji dan cuti dalam unggahan tidak digunakan sebagai persetujuan.</p>
                </div><a class="btn btn-light" href="{{ route('severance.template') }}">Unduh template</a>
            </div>
            <div class="salary-card-body">
                <form data-severance-form class="severance-form" action="{{ route('severance.store') }}" method="POST" enctype="multipart/form-data">@csrf
                    <div><label for="batch-title" class="form-label">Nama batch</label><input id="batch-title" name="title" class="form-control" maxlength="150" placeholder="Contoh: Pengajuan HI November 2026" required></div>
                    <div><label for="termination-date" class="form-label">Tanggal akhir kerja</label><input id="termination-date" name="termination_date" type="date" class="form-control" required aria-describedby="date-help"><small id="date-help">Berlaku untuk semua baris; dapat diperbaiki per karyawan sebelum dikirim.</small></div>
                    <div class="severance-wide"><label for="source-file" class="form-label">File pengajuan (.xlsx, maksimal 5 MB)</label><input id="source-file" name="file" type="file" class="form-control" accept=".xlsx" required><small>Header di baris 7. Isi NIK di B, alasan di L, ongkos pulang di M, pasal di R. Pasal/alasan yang kosong dapat dilengkapi pada draf.</small></div>
                    <div class="severance-wide"><button class="btn btn-primary" type="submit" data-loading="Mengunggah...">Unggah pengajuan</button>
                        <p class="severance-error" data-form-error role="alert"></p>
                    </div>
                </form>
            </div>
        </section>
        @endif
        <section class="salary-card">
            <div class="salary-card-header">
                <div>
                    <h2>Batch pengajuan</h2>
                    <p>Buka batch untuk melengkapi draf, memeriksa upah, atau memberikan persetujuan sesuai akses Anda.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table salary-table mb-0">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Status impor</th>
                            <th>Valid / dibaca</th>
                            <th>Gagal</th>
                            <th>Dibuat (WITA)</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)<tr>
                            <td data-label="Batch"><strong>{{ $batch->summary['title'] ?? 'Pengajuan pesangon' }}</strong><small>#{{ $batch->id }}</small></td>
                            <td data-label="Status impor"><span class="badge bg-{{ $batch->status_badge_class }}">{{ $batch->status_label }}</span></td>
                            <td data-label="Valid / dibaca">{{ $batch->success_count }} / {{ $batch->total_rows }}</td>
                            <td data-label="Gagal">{{ $batch->failed_count }}</td>
                            <td data-label="Dibuat (WITA)">{{ $batch->created_at->timezone('Asia/Makassar')->format('d M Y H:i') }}</td>
                            <td data-label="Aksi"><a class="btn btn-light btn-sm" href="{{ route('severance.batch', $batch) }}">Buka batch</a></td>
                        </tr>
                        @empty<tr>
                            <td colspan="6" class="salary-empty">Belum ada pengajuan untuk ditinjau. Pengajuan yang menjadi tugas Anda akan muncul di sini.</td>
                        </tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="salary-card-body">{{ $batches->links() }}</div>
        </section>
        @if(auth()->user()->hasMenuAccess('severance_staff'))
        <section class="salary-card" id="exports">
            <div class="salary-card-header">
                <div>
                    <h2>Riwayat Export</h2>
                    <p>File hasil berisi daftar pengajuan dengan upah yang disetujui dan rincian perhitungan. Nilainya bruto sebelum pajak.</p>
                </div>
            </div>
            <div class="salary-card-body">
                @forelse($exports as $process)
                <div class="severance-process" data-severance-process data-status-url="{{ route('severance.status', $process) }}" data-status="{{ $process->status }}">
                    <div><strong>Export #{{ $process->id }}</strong> <span data-process-label>{{ $process->status_label }}</span>
                        <p data-process-progress>{{ $process->success_count }} / {{ $process->total_rows }} pengajuan</p>
                        <p class="severance-error" data-process-error role="status">{{ $process->error_message }}</p>
                    </div>
                    <div class="salary-actions">
                        @if($process->status === 'completed')<a class="btn btn-primary btn-sm" href="{{ route('severance.download', $process) }}">Unduh Excel</a>@endif
                        @if(in_array($process->status, ['queued', 'processing']))<button type="button" class="btn btn-light btn-sm" data-process-refresh>Refresh status</button>@endif
                        @if($process->status === 'failed')<form data-severance-form action="{{ route('severance.retry', $process) }}" method="POST">@csrf<button class="btn btn-outline-primary btn-sm" type="submit">Coba lagi</button>
                            <p data-form-error class="severance-error" role="alert"></p>
                        </form>@endif
                    </div>
                </div>
                @empty<p class="salary-note">Belum ada export. Pilih pengajuan final di dalam batch, kemudian tekan Export Excel.</p>@endforelse
                {{ $exports->links() }}
            </div>
        </section>
        @endif
        @endif
    </div>
</div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/severance.js') }}"></script>@endpush