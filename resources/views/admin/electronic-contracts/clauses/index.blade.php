@extends('layouts.app')

@section('title', 'Klausul Adendum')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Klausul Adendum</h4>
                <small class="text-muted">Klausul 1 hanya untuk adendum pertama. Adendum kedua dan seterusnya otomatis memakai Klausul 2.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('electronic-contracts.clauses.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Tambah Klausul
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Jenis</th>
                                <th>Nama</th>
                                <th>Status</th>
                                <th>Update Terakhir</th>
                                <th style="width: 190px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clauses as $clause)
                                <tr>
                                    <td>{{ $keyOptions[$clause->clause_key] ?? $clause->clause_key }}</td>
                                    <td>{{ $clause->name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $clause->is_active ? 'success' : 'secondary' }}">
                                            {{ $clause->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($clause->updated_at)->format('d M Y H:i') }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('electronic-contracts.clauses.edit', $clause) }}" class="btn btn-sm btn-warning">Edit</a>
                                        <form action="{{ route('electronic-contracts.clauses.destroy', $clause) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus klausul ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada klausul adendum.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
