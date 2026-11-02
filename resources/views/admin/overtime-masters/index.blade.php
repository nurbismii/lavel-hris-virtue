@extends('layouts.app')

@section('title', __('Master Lembur'))

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner salary-page overtime-calculator-page">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold mb-1">{{ __('Master Lembur') }}</h4>
                <small class="text-muted">{{ __('Rumus upah lembur PP 35/2021 untuk pola 5:2, 6:1, hari kerja, hari off, dan tanggal merah.') }}</small>
            </div>
            <div class="ms-md-auto py-2 py-md-0">
                @if(auth()->user()->hasMenuAccess('slip_gaji_admin'))
                <a href="{{ route('employee-salaries.index') }}" class="btn btn-light">Pembaruan gaji</a>
                @endif
                <a href="{{ route('overtime-masters.create') }}" class="btn btn-primary">{{ __('Tambah Rule') }}</a>
                <a href="{{ route('overtime-orders.index') }}" class="btn btn-light">{{ __('Perintah Lembur') }}</a>
            </div>
        </div>

        @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Perhitungan belum dapat dilakukan.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">{{ __('Kalkulator Upah Lembur') }}</h5>
                        <form action="{{ route('overtime-masters.calculate') }}" method="POST" class="row g-3" data-submit-lock id="overtime-calculator">
                            @csrf
                            <div class="col-12">
                                <label class="form-label" for="wage-source">Sumber upah</label>
                                <select class="form-select" name="wage_source" id="wage-source">
                                    @if(auth()->user()->hasMenuAccess('slip_gaji_admin'))
                                    <option value="history" {{ old('wage_source', request('wage_source', 'history')) === 'history' ? 'selected' : '' }}>Riwayat upah yang disetujui HR</option>
                                    @endif
                                    <option value="manual" {{ old('wage_source', request('wage_source')) === 'manual' ? 'selected' : '' }}>Simulasi komponen upah manual</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="overtime-date">Tanggal pelaksanaan lembur</label>
                                <input class="form-control" id="overtime-date" name="overtime_date" type="date" value="{{ old('overtime_date', request('overtime_date', now()->toDateString())) }}">
                                <small class="text-muted">Riwayat upah dipilih sesuai tanggal ini, termasuk perubahan di tengah periode payroll.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="overtime-employee">{{ __('Karyawan') }}</label>
                                <select
                                    id="overtime-employee"
                                    name="nik_karyawan"
                                    class="form-select js-overtime-employee-select @error('nik_karyawan') is-invalid @enderror"
                                    data-search-url="{{ route('overtime-orders.employees.search') }}"
                                    data-placeholder="{{ __('Ketik minimal 2 karakter nama atau NIK karyawan') }}">
                                    @if($selectedEmployee)
                                        @php
                                            $selectedDetails = collect([
                                                optional(optional($selectedEmployee->departemen)->perusahaan)->kode_perusahaan,
                                                optional($selectedEmployee->departemen)->departemen,
                                                optional($selectedEmployee->divisi)->nama_divisi,
                                                optional($selectedEmployee->workPattern)->code ? 'Pola ' . optional($selectedEmployee->workPattern)->code : null,
                                            ])->filter()->implode(' | ');
                                        @endphp
                                        <option value="{{ $selectedEmployee->nik }}" selected>
                                            {{ $selectedEmployee->nama_karyawan }} - {{ $selectedEmployee->nik }}{{ $selectedDetails ? ' | ' . $selectedDetails : '' }}
                                        </option>
                                    @endif
                                </select>
                                <small class="text-muted">{{ __('Jika dipilih, pola 5:2 atau 6:1 dibaca dari master jadwal karyawan.') }}</small>
                                @error('nik_karyawan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="overtime-schedule">{{ __('Pola Jadwal') }}</label>
                                <select id="overtime-schedule" name="schedule_type" class="form-select @error('schedule_type') is-invalid @enderror">
                                    @foreach($scheduleTypeOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('schedule_type', request('schedule_type')) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('schedule_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="overtime-day-type">{{ __('Jenis Hari Lembur') }}</label>
                                <select id="overtime-day-type" name="day_type" class="form-select @error('day_type') is-invalid @enderror">
                                    @foreach($dayTypeOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('day_type', request('day_type')) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('day_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 js-overtime-manual">
                                @foreach(['basic_salary' => 'Gaji pokok bulanan', 'fixed_allowance' => 'Tunjangan tetap bulanan', 'non_fixed_allowance' => 'Tunjangan tidak tetap bulanan'] as $field => $label)
                                <label class="form-label mt-2" for="{{ $field }}">{{ $label }}</label>
                                <input type="number" id="{{ $field }}" name="{{ $field }}" min="{{ $field === 'basic_salary' ? '0.01' : '0' }}" max="999999999" step="0.01" class="form-control" value="{{ old($field, request($field, $field === 'basic_salary' ? '' : 0)) }}">
                                @endforeach
                                <small class="text-muted">Dasar lembur adalah nilai tertinggi antara gaji pokok + tunjangan tetap dan 75% jumlah ketiga komponen (Pasal 32). Isi 0 jika tunjangan tidak ada.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="overtime-hours">{{ __('Durasi Lembur (Jam)') }}</label>
                                <input
                                    id="overtime-hours"
                                    type="number"
                                    name="overtime_hours"
                                    min="0.01"
                                    max="24"
                                    step="0.01"
                                    class="form-control @error('overtime_hours') is-invalid @enderror"
                                    value="{{ old('overtime_hours', request('overtime_hours')) }}"
                                    placeholder="{{ __('Contoh: 3.5') }}">
                                @error('overtime_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100" data-loading-text="Menghitung...">{{ __('Hitung Lembur') }}</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">{{ __('Acuan Perhitungan') }}</h6>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ __('Upah sejam') }}</span>
                            <strong>{{ __('1/173 x upah sebulan') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ __('Hari kerja') }}</span>
                            <strong>{{ __('1,5x lalu 2x') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span>{{ __('5:2 off/libur') }}</span>
                            <strong>{{ __('2x, 3x, 4x') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span>{{ __('6:1 off/libur') }}</span>
                            <strong>{{ __('2x, 3x, 4x') }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                @if($calculationResult)
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                <div>
                                    <h5 class="fw-bold mb-1">{{ __('Hasil Perhitungan') }}</h5>
                                    @if(!empty($calculationResult['salary_history']))
                                    <p class="salary-note">Sumber upah: versi #{{ $calculationResult['salary_history']->id }}, berlaku {{ $calculationResult['salary_history']->effective_from->format('d M Y') }} · {{ $calculationResult['salary_history']->reference }} · Tanggal lembur {{ $calculationResult['overtime_date'] }}.</p>
                                    @else
                                    <p class="salary-note">Simulasi manual; belum menjadi hasil payroll yang disetujui.</p>
                                    @endif
                                    <small class="text-muted">
                                        {{ $calculationResult['schedule_type_label'] }} | {{ $calculationResult['day_type_label'] }}
                                    </small>
                                    @if(!empty($calculationResult['employee']))
                                        <div class="small text-muted">
                                            {{ $calculationResult['employee']['name'] }} - {{ $calculationResult['employee']['nik'] }}
                                            @if($calculationResult['employee']['work_pattern'])
                                                | Pola {{ $calculationResult['employee']['work_pattern'] }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="text-md-end">
                                    <div class="small text-muted">{{ __('Total Upah Lembur') }}</div>
                                    <div class="fs-4 fw-bold text-success">
                                        Rp {{ number_format($calculationResult['amount'], 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>

                            @if(!empty($calculationResult['warnings']))
                                <div class="alert alert-warning mt-3 mb-0">
                                    @foreach($calculationResult['warnings'] as $warning)
                                        <div>{{ $warning }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="row g-3 mt-1">
                                <div class="col-md-3 col-6">
                                    <div class="small text-muted">{{ __('Durasi') }}</div>
                                    <div class="fw-semibold">{{ number_format($calculationResult['overtime_hours'], 2, ',', '.') }} jam</div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="small text-muted">{{ __('Dasar Upah') }}</div>
                                    <div class="fw-semibold">Rp {{ number_format($calculationResult['monthly_wage'], 0, ',', '.') }}</div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="small text-muted">{{ __('Upah Sejam') }}</div>
                                    <div class="fw-semibold">Rp {{ number_format($calculationResult['hourly_wage'], 2, ',', '.') }}</div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="small text-muted">{{ __('Satuan Pengali') }}</div>
                                    <div class="fw-semibold">{{ number_format($calculationResult['multiplier_units'], 4, ',', '.') }}</div>
                                </div>
                            </div>

                            <div class="table-responsive mt-4">
                                <table class="table table-bordered table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('tables.range') }}</th>
                                            <th>{{ __('tables.duration') }}</th>
                                            <th>{{ __('tables.multiplier') }}</th>
                                            <th>{{ __('tables.legal_basis') }}</th>
                                            <th class="text-end">{{ __('tables.nominal') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($calculationResult['items'] as $item)
                                            <tr>
                                                <td>{{ $item['hour_range_label'] }}</td>
                                                <td>{{ number_format($item['hours'], 2, ',', '.') }} jam</td>
                                                <td>{{ number_format($item['multiplier'], 2, ',', '.') }}x</td>
                                                <td>{{ $item['legal_basis'] }}</td>
                                                <td class="text-end">Rp {{ number_format($item['amount'], 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">{{ __('Master Rule PP 35/2021') }}</h5>
                        <p class="salary-note mb-3">Pengali master yang lebih rendah tidak mengurangi batas minimum PP 35/2021. Rentang yang tumpang tindih memakai pengali tertinggi satu kali. Ketentuan perusahaan yang lebih baik tetap dipertahankan (Pasal 34).</p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('tables.code') }}</th>
                                        <th>{{ __('tables.pattern') }}</th>
                                        <th>{{ __('tables.day_type') }}</th>
                                        <th>{{ __('tables.hour') }}</th>
                                        <th>{{ __('tables.multiplier') }}</th>
                                        <th>{{ __('tables.status') }}</th>
                                        <th>{{ __('tables.legal_basis') }}</th>
                                        <th>{{ __('tables.action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($rules as $rule)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $rule->code }}</div>
                                                <div class="small text-muted">{{ $rule->name }}</div>
                                            </td>
                                            <td>{{ $ruleScheduleTypeOptions[$rule->schedule_type] ?? $rule->schedule_type }}</td>
                                            <td>{{ $dayTypeOptions[$rule->day_type] ?? $rule->day_type }}</td>
                                            <td>{{ $rule->hour_range_label }}</td>
                                            <td>{{ number_format((float) $rule->multiplier, 2, ',', '.') }}x</td>
                                            <td>
                                                <span class="badge bg-{{ $rule->is_active ? 'success' : 'secondary' }}">
                                                    {{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </td>
                                            <td>{{ $rule->legal_basis }}</td>
                                            <td class="text-nowrap">
                                                <a href="{{ route('overtime-masters.edit', $rule->id) }}" class="btn btn-sm btn-info">
                                                    {{ __('Edit') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">{{ __('Master rule lembur belum tersedia. Jalankan migration terbaru.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ versioned_asset('assets/js/plugin/select2/select2.full.min.js') }}"></script>
<script src="{{ versioned_asset('assets/js/overtime-order-form.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('wage-source');
    function updateSource() {
        const manual = source.value === 'manual';
        document.querySelectorAll('.js-overtime-manual').forEach(function (group) {
            group.hidden = !manual;
            group.querySelectorAll('input').forEach(function (input) { input.disabled = !manual; input.required = manual; });
        });
        document.getElementById('overtime-date').required = !manual;
        document.getElementById('overtime-employee').required = !manual;
    }
    source.addEventListener('change', updateSource);
    updateSource();
});
</script>
@endpush
