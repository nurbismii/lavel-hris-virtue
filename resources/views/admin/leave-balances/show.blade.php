@extends('layouts.app')

@section('title', 'Ledger Saldo Cuti')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="fas fa-calendar-check text-primary me-2"></i>
                    Ledger Saldo Cuti
                </h4>
                <small class="text-muted">{{ $employee->nik }} - {{ $employee->nama_karyawan }} - {{ $employee->area_kerja }}</small>
            </div>
            <div class="ms-md-auto">
                <a href="{{ route('leave-balances.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        @if(!$isTableReady)
            <div class="alert alert-warning">
                Fitur ledger saldo cuti belum aktif karena tabel <code>leave_balance_ledgers</code> belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
            </div>
        @else
            <div class="row g-3 mb-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Saldo Cuti Saat Ini</div>
                            <div class="display-6 fw-bold text-primary">{{ number_format((float) $currentBalance, 0) }}</div>
                            <div class="text-muted">hari</div>
                            <hr>
                            <div class="small text-muted">Perusahaan</div>
                            <div class="fw-semibold mb-2">{{ $employee->area_kerja ?: '-' }}</div>
                            <div class="small text-muted">Departemen</div>
                            <div class="fw-semibold mb-2">{{ optional($employee->departemen)->departemen ?? '-' }}</div>
                            <div class="small text-muted">Divisi</div>
                            <div class="fw-semibold">{{ optional($employee->divisi)->nama_divisi ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h5 class="mb-1">Input Transaksi HR</h5>
                            <small class="text-muted d-block mb-3">Gunakan form ini hanya untuk koreksi saldo oleh HR. Pemakaian cuti tetap tercatat otomatis saat approval HRD.</small>

                            <form action="{{ route('leave-balances.store', $employee->nik) }}" method="POST" class="row g-3">
                                @csrf
                                <div class="col-md-3">
                                    <label class="form-label">Arah Adjustment</label>
                                    <select name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                                        <option value="">Pilih arah</option>
                                        <option value="credit" {{ old('direction') === 'credit' ? 'selected' : '' }}>Tambah saldo</option>
                                        <option value="debit" {{ old('direction') === 'debit' ? 'selected' : '' }}>Kurangi saldo</option>
                                    </select>
                                    @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Jumlah Hari</label>
                                    <input type="number" step="1" min="1" max="365" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Tahun Periode</label>
                                    <input type="number" min="2000" max="2100" name="period_year" class="form-control @error('period_year') is-invalid @enderror" value="{{ old('period_year', now()->year) }}">
                                    @error('period_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Transaksi</label>
                                    <input type="date" name="transaction_date" class="form-control @error('transaction_date') is-invalid @enderror" value="{{ old('transaction_date', now()->toDateString()) }}" required>
                                    @error('transaction_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Catatan HR</label>
                                    <textarea name="note" rows="3" class="form-control @error('note') is-invalid @enderror" maxlength="500" placeholder="Contoh: Koreksi saldo cuti berdasarkan verifikasi HR tanggal 12 Mei 2026" required>{{ old('note') }}</textarea>
                                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Simpan Transaksi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Histori Ledger</h5>
                            <small class="text-muted">Semua perubahan saldo cuti karyawan ini tercatat berurutan.</small>
                        </div>
                        <div class="text-muted small">Total: {{ number_format($ledgers->total()) }} transaksi</div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 120px;">{{ __('tables.date') }}</th>
                                    <th style="width: 150px;">{{ __('tables.type_kind') }}</th>
                                    <th style="width: 110px;">{{ __('tables.debit') }}</th>
                                    <th style="width: 110px;">{{ __('tables.credit') }}</th>
                                    <th style="width: 120px;">{{ __('tables.balance') }}</th>
                                    <th style="width: 110px;">{{ __('tables.period') }}</th>
                                    <th>{{ __('tables.note') }}</th>
                                    <th style="width: 150px;">{{ __('tables.recorded_by') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ledgers as $ledger)
                                    <tr>
                                        <td>{{ optional($ledger->transaction_date)->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge bg-{{ $ledger->direction === 'debit' ? 'warning text-dark' : 'success' }}">
                                                {{ $ledger->type_label }}
                                            </span>
                                            @if($ledger->reference_type)
                                                <div class="small text-muted mt-1">{{ $ledger->reference_type }} #{{ $ledger->reference_id }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $ledger->direction === 'debit' ? number_format((float) $ledger->amount, 2) : '-' }}</td>
                                        <td>{{ $ledger->direction === 'credit' ? number_format((float) $ledger->amount, 2) : '-' }}</td>
                                        <td>
                                            <div>{{ number_format((float) $ledger->balance_after, 2) }}</div>
                                            <small class="text-muted">dari {{ number_format((float) $ledger->balance_before, 2) }}</small>
                                        </td>
                                        <td>
                                            {{ $ledger->period_year ?: '-' }}
                                            @if($ledger->expires_at)
                                                <div class="small text-muted">Exp {{ $ledger->expires_at->format('d M Y') }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $ledger->note ?: '-' }}</td>
                                        <td>
                                            {{ optional($ledger->actor)->name ?? '-' }}
                                            <div class="small text-muted">{{ optional($ledger->created_at)->format('d M Y H:i') }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Belum ada ledger saldo cuti untuk karyawan ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $ledgers->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
