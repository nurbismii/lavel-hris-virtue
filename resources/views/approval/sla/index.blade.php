@extends('layouts.app')

@section('title', __('navigation.approval_sla'))

@php
    $summaryCards = [
        'total' => ['label' => 'Total pending', 'class' => 'border-primary'],
        \App\Services\Approvals\ApprovalSlaService::STATUS_ON_TRACK => ['label' => 'Dalam SLA', 'class' => 'border-success'],
        \App\Services\Approvals\ApprovalSlaService::STATUS_WARNING => ['label' => 'Mendekati SLA', 'class' => 'border-info'],
        \App\Services\Approvals\ApprovalSlaService::STATUS_BREACHED => ['label' => 'Lewat SLA', 'class' => 'border-warning'],
        \App\Services\Approvals\ApprovalSlaService::STATUS_CRITICAL => ['label' => 'Kritis', 'class' => 'border-danger'],
        'escalated' => ['label' => 'Eskalasi terkirim', 'class' => 'border-secondary'],
    ];
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-stopwatch text-primary me-2"></i>
                    {{ __('navigation.approval_sla') }}
                </h4>
                <small class="text-muted">
                    Pantau approval yang mendekati atau melewati SLA tanpa mengubah status approval secara otomatis.
                </small>
            </div>
            <div class="ms-md-auto">
                <form method="POST" action="{{ route('approval-sla.escalate', $filters) }}" class="js-sla-escalate-form">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm" {{ !$isTableReady ? 'disabled' : '' }} data-loading-text="Mengirim eskalasi...">
                        <i class="fas fa-bell me-1"></i>
                        Jalankan Eskalasi
                    </button>
                </form>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur eskalasi SLA belum aktif karena tabel <code>approval_sla_escalation_logs</code> belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
            </div>
        @endif

        @if(!config('approval_sla.enabled', true))
            <div class="alert alert-secondary">
                SLA approval sedang nonaktif melalui konfigurasi <code>APPROVAL_SLA_ENABLED</code>.
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Filter tidak valid.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Modul</label>
                        <select name="module" class="form-select form-control">
                            <option value="all" {{ $filters['module'] === 'all' ? 'selected' : '' }}>Semua modul</option>
                            @foreach($modules as $key => $label)
                                <option value="{{ $key }}" {{ $filters['module'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tahap</label>
                        <select name="stage" class="form-select form-control">
                            <option value="all" {{ $filters['stage'] === 'all' ? 'selected' : '' }}>Semua tahap</option>
                            @foreach($stages as $key => $label)
                                <option value="{{ $key }}" {{ $filters['stage'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status SLA</label>
                        <select name="status" class="form-select form-control">
                            <option value="all" {{ $filters['status'] === 'all' ? 'selected' : '' }}>Semua status</option>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>
                            Tampilkan
                        </button>
                        <a href="{{ route('approval-sla.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach($summaryCards as $key => $card)
                <div class="col-6 col-md-3 col-xl-2">
                    <div class="border {{ $card['class'] }} rounded p-3 h-100 bg-light">
                        <div class="small text-muted">{{ $card['label'] }}</div>
                        <div class="fs-5 fw-bold">{{ number_format((int) ($summary[$key] ?? 0)) }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Antrean Approval Berdasarkan SLA</h5>
                        <small class="text-muted">Maksimal {{ number_format((int) config('approval_sla.dashboard_limit', 500)) }} data ditampilkan per filter.</small>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 150px;">Modul</th>
                                <th>Karyawan</th>
                                <th>Organisasi</th>
                                <th style="width: 170px;">Periode</th>
                                <th style="width: 95px;">Tahap</th>
                                <th style="width: 150px;">Mulai SLA</th>
                                <th style="width: 150px;">Jatuh Tempo</th>
                                <th style="width: 110px;">Umur</th>
                                <th style="width: 130px;">Status</th>
                                <th style="width: 90px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                <tr>
                                    <td>{{ $item['module_label'] }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $item['employee_name'] }}</div>
                                        <small class="text-muted">{{ $item['nik_karyawan'] }}</small>
                                    </td>
                                    <td>
                                        <div>{{ $item['departemen'] }}</div>
                                        <small class="text-muted">{{ $item['divisi'] }} - {{ $item['area_kerja'] ?: '-' }}</small>
                                    </td>
                                    <td>{{ $item['request_period'] }}</td>
                                    <td>{{ $item['stage_label'] }}</td>
                                    <td>{{ optional($item['sla_started_at'])->format('d M Y H:i') }}</td>
                                    <td>{{ optional($item['due_at'])->format('d M Y H:i') }}</td>
                                    <td>
                                        <div>{{ number_format($item['age_hours'], 1) }} jam</div>
                                        @if($item['remaining_hours'] > 0)
                                            <small class="text-muted">Sisa {{ number_format($item['remaining_hours'], 1) }} jam</small>
                                        @else
                                            <small class="text-danger">Lewat SLA</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $item['sla_status_badge'] }}">{{ $item['sla_status_label'] }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ $item['approval_url'] }}" class="btn btn-sm btn-outline-primary">
                                            Buka
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        Tidak ada approval pending sesuai filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-3">
                    <h5 class="mb-1">Histori Eskalasi Terbaru</h5>
                    <small class="text-muted">Log ini mencegah pengiriman eskalasi yang sama berulang kali.</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 150px;">Waktu</th>
                                <th style="width: 110px;">Level</th>
                                <th>Pesan</th>
                                <th style="width: 110px;">Penerima</th>
                                <th style="width: 160px;">Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td>{{ optional($log->escalated_at)->format('d M Y H:i') ?: '-' }}</td>
                                    <td>Level {{ $log->escalation_level }}</td>
                                    <td>
                                        <div>{{ $log->message ?: '-' }}</div>
                                        <small class="text-muted">{{ $modules[$log->module] ?? $log->module }} - {{ $stages[$log->stage] ?? strtoupper($log->stage) }}</small>
                                    </td>
                                    <td>{{ number_format((int) $log->recipient_count) }}</td>
                                    <td>{{ optional($log->escalator)->name ?: 'System' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Belum ada eskalasi SLA approval.
                                    </td>
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

@push('scripts')
<script>
    $(document).on('submit', '.js-sla-escalate-form', function (event) {
        const $form = $(this);

        if ($form.data('submitting') === 1) {
            event.preventDefault();
            return;
        }

        event.preventDefault();

        const $button = $form.find('button[type="submit"]');
        const loadingText = $button.data('loading-text') || 'Memproses...';

        window.AppDialog.confirm({
            title: 'Kirim Eskalasi SLA?',
            text: 'Kirim eskalasi untuk semua approval yang sudah melewati SLA?',
            icon: 'warning',
            confirmButtonText: 'Ya, kirim',
            cancelButtonText: 'Batal'
        }).then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            $form.data('submitting', 1);
            $button.prop('disabled', true).data('original-html', $button.html()).html(loadingText);
            $form[0].submit();
        });
    });
</script>
@endpush
