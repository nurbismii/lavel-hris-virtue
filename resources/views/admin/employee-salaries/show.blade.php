@extends('layouts.app')
@section('title', 'Tinjau Riwayat Upah')
@section('content')
<div class="container-fluid"><div class="page-inner salary-page" id="salary-process" data-status-url="{{ route('employee-salaries.status', $history) }}" data-initial-status="{{ $history->status }}">
    <header class="salary-header"><div><span class="salary-eyebrow">Upload #{{ $history->id }}</span><h1>Tinjau Riwayat Upah</h1><p>Periksa nominal, tanggal efektif, dan referensi keputusan pada setiap baris.</p></div><div class="salary-actions"><a href="{{ route('employee-salaries.index') }}" class="btn btn-light">Kembali ke riwayat</a><button type="button" class="btn btn-light" id="salary-refresh">Refresh status</button></div></header>
    <section class="salary-card" aria-labelledby="status-title">
        <div class="salary-card-header"><h2 id="status-title">Status proses</h2><span id="salary-status" class="badge bg-{{ $history->status_badge_class }}">{{ $history->status_label }}</span></div>
        <div class="salary-card-body" aria-live="polite">
            <p id="salary-progress">{{ ($history->summary['phase'] ?? 'preview') === 'commit' ? 'Penerapan perubahan' : 'Validasi file' }} · {{ $history->total_rows }} baris · {{ $history->success_count }} diterapkan · {{ $history->failed_count }} gagal.</p>
            <p id="salary-error" class="text-danger mb-0">{{ $history->error_message }}</p>
            <div class="salary-note mt-3">Diunggah {{ $history->created_at->format('d M Y H:i') }}@if($history->started_at) · Mulai {{ $history->started_at->format('d M Y H:i') }}@endif @if($history->finished_at) · Selesai {{ $history->finished_at->format('d M Y H:i') }}@endif</div>
            @if($history->failed_count > 0)<a class="btn btn-light mt-3" href="{{ route('employee-salaries.failures', $history) }}">Unduh detail baris gagal</a>@endif
        </div>
    </section>
    @if($history->status === \App\Models\ImportHistory::STATUS_AWAITING_CONFIRMATION)
    <section class="salary-card"><div class="salary-card-body">
        <form id="salary-confirm" method="POST" action="{{ route('employee-salaries.confirm', $history) }}">@csrf
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="reviewed" id="salary-reviewed" value="1" required><label class="form-check-label" for="salary-reviewed">Saya telah memeriksa seluruh baris dan referensi keputusan. Revisi pada tanggal efektif yang sama menambahkan versi persetujuan baru.</label></div>
            <button class="btn btn-primary" type="submit">Konfirmasi dan terapkan {{ $history->total_rows }} perubahan</button>
            <p class="salary-note mt-3 mb-0">Konfirmasi berlaku sampai {{ $history->expires_at->format('d M Y H:i') }}. Perubahan tidak mengirim pembayaran atau mengubah slip yang sudah terbit.</p>
        </form>
    </div></section>
    @endif
    <section class="salary-card"><div class="salary-card-header"><div><h2>Detail perubahan</h2><p>Menampilkan maksimal 50 baris per halaman. Tinjau semua halaman sebelum konfirmasi.</p></div></div>
        <div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>Baris / karyawan</th><th>Gaji sebelumnya</th><th>Gaji baru</th><th>Tunjangan tetap / tidak tetap</th><th>Berlaku mulai</th><th>Keputusan / hasil</th></tr></thead><tbody>
            @forelse($items as $item)
            @php($row = $item->payload ?: [])
            <tr><td data-label="Baris / karyawan"><strong>#{{ $item->row_number }} · {{ $item->nik }}</strong><small>{{ $item->employee_name }}</small></td>
                <td data-label="Gaji sebelumnya">{{ isset($row['previous_basic_salary']) ? 'Rp ' . number_format((float) $row['previous_basic_salary'], 0, ',', '.') : 'Belum tersedia' }}</td>
                <td data-label="Gaji baru">{{ is_numeric($row['gaji_pokok'] ?? null) ? 'Rp ' . number_format((float) $row['gaji_pokok'], 0, ',', '.') : 'Tidak valid' }}</td>
                <td data-label="Tunjangan tetap / tidak tetap">{{ is_numeric($row['tunjangan_tetap'] ?? null) ? number_format((float) $row['tunjangan_tetap'], 0, ',', '.') : 'Tidak valid' }} / {{ is_numeric($row['tunjangan_tidak_tetap'] ?? null) ? number_format((float) $row['tunjangan_tidak_tetap'], 0, ',', '.') : 'Tidak valid' }}</td>
                <td data-label="Berlaku mulai">{{ $row['berlaku_mulai'] ?? '-' }}</td>
                <td data-label="Keputusan / hasil">{{ $row['alasan'] ?? '-' }}<small>{{ $row['referensi'] ?? '-' }}</small><small class="{{ $item->category === 'failed' ? 'text-danger' : 'text-muted' }}">{{ $item->message }}</small></td></tr>
            @empty
            <tr><td colspan="6" class="salary-empty">Detail akan tersedia setelah validasi file selesai. Status diperbarui otomatis selama maksimal 10 menit.</td></tr>
            @endforelse
        </tbody></table></div><div class="salary-card-body">{{ $items->links() }}</div>
    </section>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/employee-salaries.js') }}"></script>@endpush
