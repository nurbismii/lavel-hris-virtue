@extends('layouts.app')
@section('title', 'Tempat Sampah')
@push('styles')<link rel="stylesheet" href="{{ versioned_asset('assets/css/payroll.css') }}">@endpush
@section('content')
<div class="container-fluid"><div class="page-inner salary-page payroll-page">
    <header class="salary-header"><div><h1>Tempat Sampah</h1><p>Pulihkan data yang dipindahkan ke sini. Dokumen dan riwayat terkait tetap disimpan.</p></div></header>
    <div class="alert alert-info">Data yang telah dihapus permanen sebelum fitur ini diterapkan memerlukan pemulihan dari backup. Halaman ini tidak menyediakan hapus permanen.</div>
    <section class="salary-card"><div class="salary-card-body">
        <form method="get" class="payroll-form">
            <div><label class="form-label" for="recovery-type">Jenis data</label><select class="form-select" id="recovery-type" name="type">@foreach($types as $key=>$option)<option value="{{ $key }}" @selected($key === $type)>{{ $option[1] }}</option>@endforeach</select></div>
            <div><label class="form-label" for="recovery-search">ID/NIK atau awal nama</label><input class="form-control" id="recovery-search" name="q" maxlength="100" value="{{ $search }}"></div>
            <div class="align-self-end"><button class="btn btn-primary">Tampilkan</button></div>
        </form>
    </div></section>
    <section class="salary-card"><div class="salary-card-header"><h2>{{ $meta[1] }} terhapus</h2></div><div class="table-responsive"><table class="table salary-table mb-0"><thead><tr><th>ID / NIK</th><th>Nama</th><th>Dihapus pada</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($items as $item)
        <tr><td data-label="ID / NIK">{{ $item->getKey() }}</td><td data-label="Nama">{{ $item->{$meta[2]} }}</td><td data-label="Dihapus pada">{{ $item->deleted_at->timezone('Asia/Makassar')->format('d/m/Y H:i') }} WITA</td><td data-label="Tindakan">
            <form method="post" action="{{ route('deleted-data.restore', ['type'=>$type, 'id'=>$item->getKey()]) }}" data-admin-submit data-confirm-text="Pulihkan data ini agar tersedia kembali di modul asal?">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Pulihkan<span class="visually-hidden"> {{ $item->{$meta[2]} }}</span></button></form>
        </td></tr>
        @empty<tr><td colspan="4"><div class="p-4 text-center">Tidak ada data terhapus yang sesuai filter.</div></td></tr>@endforelse
    </tbody></table></div><div class="salary-card-body">{{ $items->links() }}</div></section>
</div></div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/admin-data-actions.js') }}"></script>@endpush
