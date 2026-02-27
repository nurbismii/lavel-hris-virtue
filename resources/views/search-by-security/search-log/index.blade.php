@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-search text-primary me-2"></i>
                    Logs
                </h4>
                <small class="text-muted">
                    Riwayat pencarian data karyawan tidak aktif
                </small>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body table-responsive">

                <table id="table-logs" class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th>User</th>
                            <th>Keyword</th>
                            <th>IP Address</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $index => $log)
                        <tr>
                            <td>{{ ++$index }}</td>
                            <td>{{ $log->user->name }}</td>
                            <td>{{ $log->keyword }}</td>
                            <td>{{ $log->ip_address }}</td>
                            <td>
                                {{ $log->created_at->format('d M Y H:i') }}
                                <br>
                                <small class="text-muted">
                                    {{ $log->created_at->diffForHumans() }}
                                </small>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $("#table-logs").DataTable({
            responsive: true,
        });
    });
</script>
@endpush

@endsection