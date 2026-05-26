@extends('layouts.app')

@section('title', 'Template Kontrak Elektronik')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Template Kontrak Elektronik</h4>
                <small class="text-muted">Kelola isi kontrak, KOP, gambar, dan placeholder yang akan dirender ke PDF.</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('electronic-contracts.templates.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Tambah Template
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('tables.name') }}</th>
                                <th>{{ __('tables.type') }}</th>
                                <th>{{ __('tables.status') }}</th>
                                <th>{{ __('tables.last_update') }}</th>
                                <th style="width: 190px;">{{ __('tables.action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $template->name }}</div>
                                        <small class="text-muted">ID: {{ $template->id }}</small>
                                    </td>
                                    <td>{{ $typeOptions[$template->contract_type] ?? $template->contract_type }}</td>
                                    <td>
                                        <span class="badge bg-{{ $template->is_active ? 'success' : 'secondary' }}">
                                            {{ $template->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($template->updated_at)->format('d M Y H:i') }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('electronic-contracts.templates.edit', $template) }}" class="btn btn-sm btn-warning">
                                            Edit
                                        </a>
                                        <form
                                            action="{{ route('electronic-contracts.templates.destroy', $template) }}"
                                            method="POST"
                                            class="d-inline"
                                            data-swal-confirm="Template yang dihapus tidak bisa dikembalikan."
                                            data-swal-title="Hapus template?"
                                            data-swal-confirm-button="Ya, hapus"
                                            data-swal-danger="1">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Belum ada template kontrak.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $templates->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
