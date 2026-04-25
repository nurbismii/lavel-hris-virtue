@extends('layouts.app')

@section('title', 'Master Shift')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">Master Shift</h4>
                <small class="text-muted">Kelola sumber jam kerja untuk Reguler, Shift 1, Shift 2, Shift 3, atau shift custom lain.</small>
            </div>
            <div class="ms-md-auto py-2 py-md-0">
                <a href="{{ route('shifts.create') }}" class="btn btn-primary">
                    Tambah Shift
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Tipe</th>
                                <th>Jam Kerja</th>
                                <th>Status</th>
                                <th>Dipakai</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shifts as $shift)
                                <tr>
                                    <td>{{ $shift->code }}</td>
                                    <td>{{ $shift->name }}</td>
                                    <td>{{ $shift->type_label }}</td>
                                    <td>
                                        <div>{{ $shift->work_time_range_text }}</div>
                                        <small class="text-muted d-block">Istirahat: {{ $shift->break_time_range_text }}</small>
                                        <small class="text-muted d-block">Efektif: {{ $shift->expected_work_duration_text }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $shift->is_active ? 'success' : 'secondary' }}">
                                            {{ $shift->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>{{ $shift->assignments_count }}</td>
                                    <td>{{ $shift->description ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('shifts.edit', $shift->id) }}" class="btn btn-warning">
                                            Edit
                                        </a>
                                        <form action="{{ route('shifts.destroy', $shift->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada master shift.</td>
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
