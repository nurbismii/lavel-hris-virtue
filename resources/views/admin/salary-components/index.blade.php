@extends('layouts.app')
@section('title', 'Master Komponen Gaji')
@push('styles')<link rel="stylesheet" href="{{ versioned_asset('assets/css/payroll.css') }}">@endpush
@section('content')
<div class="container-fluid"><div class="page-inner salary-page payroll-page">
    <header class="salary-header"><div><h1>Master Komponen Gaji</h1><p>Atur jenis dan rincian komponen. Gaji pokok dan total tunjangan tetap berasal dari Riwayat Upah.</p></div><a class="btn btn-outline-primary" href="{{ route('deleted-data.index', ['type'=>'komponen-gaji']) }}">Komponen terhapus</a></header>
    <div class="alert alert-info"><strong>Pemasukan tetap hanya merinci tunjangan dalam Riwayat Upah dan tidak dibayar lagi.</strong> Jumlah rincian dibatasi oleh tunjangan tetap lain setelah masa kerja. Pemasukan tidak tetap menambah penghasilan kas kena pajak; potongan mengurangi kas setelah pajak.</div>
    <section class="salary-card"><div class="salary-card-header"><h2>Tambah komponen</h2></div><div class="salary-card-body">
        <form method="post" action="{{ route('salary-components.store') }}" data-admin-submit>@csrf
            <div class="payroll-form">
                <div><label class="form-label" for="new-code">Kode</label><input class="form-control" id="new-code" name="code" maxlength="40" pattern="[A-Z][A-Z0-9_]*" required aria-describedby="code-help"><small id="code-help">Contoh: TUNJANGAN_SHIFT. Kode harus unik, termasuk data terhapus.</small></div>
                <div><label class="form-label" for="new-name">Nama komponen</label><input class="form-control" id="new-name" name="name" maxlength="100" required></div>
                <div><label class="form-label" for="new-type">Jenis</label><select class="form-select" id="new-type" name="type"><option value="income">Pemasukan kena pajak</option><option value="deduction">Potongan kas setelah pajak</option></select></div>
                <div><label class="form-label" for="new-fixed">Sifat komponen</label><select class="form-select" id="new-fixed" name="fixed" aria-describedby="fixed-help"><option value="0">Tidak tetap / per periode</option><option value="1">Tetap / dapat berulang</option></select><small id="fixed-help">Pemasukan tetap: rincian upah yang sudah tercatat. Potongan tetap: potongan berulang.</small></div>
            </div><p class="salary-note mt-3">Kode, jenis, dan sifat dikunci setelah dibuat untuk menjaga arti riwayat payroll. Buat kode baru bila ketentuannya berubah.</p>
            <p class="text-danger" role="alert" data-form-error></p><button class="btn btn-primary" type="submit">Tambah komponen</button>
        </form>
    </div></section>
    <section class="salary-card"><div class="salary-card-header"><h2>Komponen aktif</h2><form method="get" class="d-flex gap-2"><label class="visually-hidden" for="component-search">Cari kode atau awal nama</label><input class="form-control" id="component-search" name="q" maxlength="100" value="{{ $search }}" placeholder="Kode / awal nama"><button class="btn btn-outline-primary">Cari</button></form></div><div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>Kode</th><th>Nama / perubahan</th><th>Jenis / sifat</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($components as $component)
        <tr><td data-label="Kode">{{ $component->code }}</td><td data-label="Nama / perubahan"><form method="post" action="{{ route('salary-components.update', $component) }}" data-admin-submit class="d-flex flex-wrap gap-2">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $component->revision }}"><label class="visually-hidden" for="name-{{ $component->id }}">Nama {{ $component->code }}</label><input class="form-control" id="name-{{ $component->id }}" name="name" maxlength="100" value="{{ $component->name }}" required><button class="btn btn-sm btn-outline-primary" type="submit">Simpan nama</button></form></td><td data-label="Jenis / sifat">{{ $component->type === 'income' ? 'Pemasukan' : 'Potongan' }}<small>{{ $component->type === 'income' && $component->fixed ? 'Rincian tunjangan tetap dalam upah' : ($component->fixed ? 'Potongan berulang' : 'Tambahan per periode') }}</small></td><td data-label="Tindakan"><form method="post" action="{{ route('salary-components.destroy', $component) }}" data-admin-submit data-confirm-text="Pindahkan komponen ini ke Tempat Sampah? Komponen tidak dipakai pada perhitungan payroll baru. Arsip final tetap tersimpan.">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus<span class="visually-hidden"> {{ $component->code }}</span></button></form></td></tr>
        @empty<tr><td colspan="4" class="p-4 text-center">Belum ada komponen yang sesuai. Tambahkan komponen melalui formulir di atas.</td></tr>@endforelse
    </tbody></table></div><div class="salary-card-body">{{ $components->links() }}</div></section>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/admin-data-actions.js') }}"></script>@endpush
