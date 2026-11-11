@extends('layouts.app')
@section('title', 'Riwayat Karier Karyawan')
@section('content')
<div class="container-fluid"><div class="page-inner salary-page">
    <header class="salary-header">
        <div><span class="salary-eyebrow">Data karyawan</span><h1>Riwayat Karier</h1><p>{{ $employee->nama_karyawan }} · {{ $employee->nik }}</p></div>
        <a class="btn btn-light" href="{{ route('karyawan.edit', $employee->nik) }}">Kembali ke profil</a>
    </header>
    <section class="salary-card" aria-labelledby="pergerakan">
        <div class="salary-card-header"><div><h2 id="pergerakan">Promosi, demosi, dan mutasi</h2><p>Perubahan yang sudah diterapkan pada data karyawan.</p></div></div>
        <div class="table-responsive"><table class="table salary-table mb-0">
            <thead><tr><th scope="col">Efektif</th><th scope="col">Perubahan</th><th scope="col">Sebelumnya</th><th scope="col">Sesudahnya</th><th scope="col">Referensi</th></tr></thead>
            <tbody>
                @forelse($movements as $movement)
                    <tr>
                        <td data-label="Efektif">{{ $movement->effective_date->locale('id')->translatedFormat('d M Y') }}</td>
                        <td data-label="Perubahan"><span class="badge bg-{{ $movement->type_badge_class }}">{{ $movement->type_label }}</span></td>
                        <td data-label="Sebelumnya"><strong>{{ collect([$movement->old_jabatan, $movement->old_posisi])->filter()->unique()->join(' · ') ?: '—' }}</strong><small>{{ collect([$movement->oldDepartemen?->departemen, $movement->oldDivisi?->nama_divisi])->filter()->join(' / ') ?: '—' }}</small></td>
                        <td data-label="Sesudahnya"><strong>{{ collect([$movement->new_jabatan, $movement->new_posisi])->filter()->unique()->join(' · ') ?: '—' }}</strong><small>{{ collect([$movement->newDepartemen?->departemen, $movement->newDivisi?->nama_divisi])->filter()->join(' / ') ?: '—' }}</small></td>
                        <td data-label="Referensi">{{ $movement->reference_number ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="salary-empty">Belum ada perubahan karier yang sudah diterapkan.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($movements->hasPages())<div class="salary-card-body">{{ $movements->links() }}</div>@endif
    </section>
    <section class="salary-card" aria-labelledby="penempatan">
        <div class="salary-card-header"><div><h2 id="penempatan">Riwayat penempatan</h2><p>Periode penempatan dan nama posisi dari master struktur organisasi.</p></div></div>
        <div class="table-responsive"><table class="table salary-table mb-0">
            <thead><tr><th scope="col">Mulai</th><th scope="col">Sampai</th><th scope="col">Posisi</th><th scope="col">Perusahaan dan unit</th><th scope="col">Referensi</th></tr></thead>
            <tbody>
                @forelse($placements as $placement)
                    @php($position = $placement->organizationPosition)
                    <tr>
                        <td data-label="Mulai">{{ $placement->effective_from->locale('id')->translatedFormat('d M Y') }}</td>
                        <td data-label="Sampai">{{ $placement->effective_until?->locale('id')->translatedFormat('d M Y') ?? ($placement->status === \App\Models\EmployeePositionAssignment::STATUS_ACTIVE ? 'Berjalan' : '—') }}</td>
                        <td data-label="Posisi"><strong>{{ $position?->display_name ?? '—' }}</strong><small>{{ $position?->code }}</small></td>
                        <td data-label="Perusahaan dan unit"><strong>{{ $position?->perusahaan?->nama_perusahaan ?? '—' }}</strong><small>{{ collect([$position?->departemen?->departemen, $position?->divisi?->nama_divisi])->filter()->join(' / ') ?: '—' }}</small></td>
                        <td data-label="Referensi">{{ $placement->reference_number ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="salary-empty">Belum ada penempatan yang tercatat pada struktur organisasi.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($placements->hasPages())<div class="salary-card-body">{{ $placements->links() }}</div>@endif
    </section>
</div></div>
@endsection
