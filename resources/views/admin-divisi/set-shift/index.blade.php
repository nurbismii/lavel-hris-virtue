@extends('layouts.app')

@section('title', 'Pengaturan Shift')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
            <div>
                <h4 class="fw-bold">
                    <i class="fas fa-business-time text-primary me-2"></i>
                    Pengaturan Shift
                </h4>
                <small class="text-muted d-block">
                    Hari kerja dan hari off tetap mengikuti master pola kerja.
                    Shift hanya mengatur jam kerja pada tanggal tertentu.
                    (Cut Off {{ formatDateIndonesia($start) }} - {{ formatDateIndonesia($end) }})
                </small>
                <small class="text-muted d-block">Pilih <strong>AUTO</strong> jika jam kerja harus kembali mengikuti master pola kerja.</small>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Periode</label>
                <input type="month" name="periode" value="{{ $periode }}" class="form-control">
            </div>

            @if($isDepartmentReadonly)
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <input type="text" class="form-control" value="{{ optional($departemens->first())->departemen ?? '-' }}" readonly>
            </div>
            @else
            <div class="col-md-3">
                <label class="form-label">Departemen</label>
                <select id="filter_departemen" name="departemen" class="form-select">
                    <option value="">Pilih Departemen</option>
                    @php
                        $groupedDepts = [];
                        foreach ($departemens as $dept) {
                            $groupedDepts[optional($dept->perusahaan)->nama_perusahaan ?? 'Lainnya'][] = $dept;
                        }
                    @endphp

                    @foreach($groupedDepts as $perusahaan => $deptItems)
                        <optgroup label="{{ $perusahaan }}">
                            @foreach($deptItems as $dept)
                                <option value="{{ $dept->id }}" {{ (string) $selectedDepartemenId === (string) $dept->id ? 'selected' : '' }}>
                                    {{ $dept->departemen }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            @endif

            @if($isDivisionReadonly)
            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <input type="text" class="form-control" value="{{ optional($divisis->first())->nama_divisi ?? '-' }}" readonly>
                <input type="hidden" id="filter_divisi" name="divisi" value="{{ $selectedDivisiId }}">
            </div>
            @else
            <div class="col-md-4">
                <label class="form-label">Divisi</label>
                <select id="filter_divisi" name="divisi" class="form-select" {{ !$selectedDepartemenId ? 'disabled' : '' }}>
                    <option value="">Semua Divisi</option>
                    @foreach ($divisis as $v)
                        <option value="{{ $v->id }}" {{ (string) $selectedDivisiId === (string) $v->id ? 'selected' : '' }}>
                            {{ $v->nama_divisi }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">
                    Tampilkan
                </button>
            </div>
        </form>

        @if($isDivisionScoped && !$isDivisionReadonly)
        <div class="alert alert-light border small">
            Akun Admin Divisi ini memiliki akses ke beberapa divisi. Pilih divisi yang ingin diatur pada periode ini.
        </div>
        @endif

        @if($shifts->isEmpty())
        <div class="alert alert-warning">
            Master shift belum tersedia. Buat dulu di
            <a href="{{ route('shifts.index') }}">Master Shift</a>.
        </div>
        @endif

        @if(!$selectedDepartemenId)
        <div class="alert alert-info">
            Pilih departemen terlebih dahulu untuk menampilkan pengaturan shift.
        </div>
        @endif

        <div class="alert alert-light border small">
            <div class="fw-semibold mb-1">Cara kerja pengaturan shift</div>
            <div>AUTO = target jam kerja mengikuti master pola kerja karyawan.</div>
            <div>Pilih shift tertentu jika tanggal itu harus memakai jam kerja Reguler, Shift 1, Shift 2, Shift 3, atau shift custom yang sudah dibuat.</div>
        </div>

        <div class="card border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>NIK</th>
                                <th>Divisi</th>
                                <th>Departemen</th>
                                <th>Pola Kerja</th>
                                @foreach($dates as $date)
                                    <th class="text-center">
                                        <div>{{ $date->format('d') }}</div>
                                        <small class="text-muted">{{ $date->translatedFormat('D') }}</small>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $index => $employee)
                                <tr>
                                    <td>{{ ++$index }}</td>
                                    <td>{{ $employee->nama_karyawan }}</td>
                                    <td>{{ $employee->nik }}</td>
                                    <td>{{ optional($employee->divisi)->nama_divisi ?? '-' }}</td>
                                    <td>{{ optional($employee->departemen)->departemen ?? '-' }}</td>
                                    <td>
                                        @if($employee->workPattern)
                                            <div class="fw-semibold">{{ $employee->workPattern->code }}</div>
                                            <div class="small text-muted">{{ $employee->workPattern->work_time_range_text }}</div>
                                        @else
                                            <span class="text-muted">Belum ada pola</span>
                                        @endif
                                    </td>
                                    @foreach($dates as $date)
                                        @php
                                            $assignment = $shiftAssignmentMap[$employee->nik][$date->toDateString()] ?? [
                                                'shift_id' => null,
                                                'shift' => null,
                                            ];
                                            $selectedShift = $assignment['shift'] ?? null;
                                        @endphp
                                        <td>
                                            <select
                                                class="form-select form-select-sm shift-assignment-select"
                                                data-employee="{{ $employee->nik }}"
                                                data-date="{{ $date->toDateString() }}"
                                                data-shift-id="{{ $assignment['shift_id'] ?? '' }}"
                                                {{ $shifts->isEmpty() ? 'disabled' : '' }}>
                                                <option value="">AUTO</option>
                                                @foreach($shifts as $shift)
                                                    <option value="{{ $shift->id }}" {{ (string) ($assignment['shift_id'] ?? '') === (string) $shift->id ? 'selected' : '' }}>
                                                        {{ $shift->code }}{{ $shift->is_active ? '' : ' [Nonaktif]' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted d-block mt-1">
                                                {{ $selectedShift ? $selectedShift->type_label : 'Pola Kerja' }}
                                            </small>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 6 + count($dates) }}" class="text-center text-muted py-4">
                                        {{ $selectedDepartemenId ? 'Tidak ada data karyawan untuk filter yang dipilih.' : 'Pilih departemen untuk mulai menampilkan data.' }}
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
    $(document).ready(function() {
        const filterDepartemen = $('#filter_departemen');
        const filterDivisi = $('#filter_divisi');

        if (filterDepartemen.length) {
            filterDepartemen.on('change', function() {
                const departemen = $(this).val();

                filterDivisi.prop('disabled', true).html('<option value="">Loading...</option>');

                if (!departemen) {
                    filterDivisi.html('<option value="">Semua Divisi</option>').prop('disabled', true);
                    return;
                }

                $.get("{{ route('ajax.divisi.by.departemen') }}", {
                    departemen: departemen
                }).done(function(response) {
                    let options = '<option value="">Semua Divisi</option>';

                    response.forEach(function(item) {
                        options += `<option value="${item.id}">${item.nama_divisi}</option>`;
                    });

                    filterDivisi.html(options).prop('disabled', false);
                }).fail(function() {
                    filterDivisi.html('<option value="">Gagal memuat divisi</option>').prop('disabled', true);
                });
            });
        }
    });
</script>

<script>
    let dirtyShiftCells = new Map();
    let shiftDebounceTimer = null;

    $(document).on('change', '.shift-assignment-select', function() {
        const select = $(this);
        const employee = select.data('employee');
        const date = select.data('date');
        const oldShiftId = String(select.data('shift-id') || '');
        const newShiftId = String(select.val() || '');

        if (newShiftId === oldShiftId) {
            return;
        }

        const key = employee + '_' + date;

        dirtyShiftCells.set(key, {
            employee_id: employee,
            tanggal: date,
            shift_id: newShiftId,
            element: select
        });

        select.closest('td').addClass('table-warning');

        clearTimeout(shiftDebounceTimer);
        shiftDebounceTimer = setTimeout(sendShiftBatch, 700);
    });

    async function sendShiftBatch() {
        const payload = Array.from(dirtyShiftCells.values());

        if (payload.length === 0) {
            return;
        }

        try {
            const response = await fetch("{{ route('shift-settings.update') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                credentials: "same-origin",
                body: JSON.stringify({
                    data: payload.map((item) => ({
                        employee_id: item.employee_id,
                        tanggal: item.tanggal,
                        shift_id: item.shift_id || null
                    }))
                })
            });

            let responseData = null;

            try {
                responseData = await response.json();
            } catch (error) {
                responseData = null;
            }

            if (!response.ok) {
                throw new Error(responseData && responseData.message ? responseData.message : 'Update gagal');
            }

            payload.forEach((item) => {
                const newShiftId = String(item.shift_id || '');
                const selectedOption = item.element.find('option:selected');
                const shiftLabel = newShiftId
                    ? selectedOption.text()
                    : 'Pola Kerja';

                item.element.data('shift-id', newShiftId);
                item.element.closest('td').removeClass('table-warning').addClass('table-success');
                item.element.siblings('small').text(newShiftId ? shiftLabel : 'Pola Kerja');

                setTimeout(() => {
                    item.element.closest('td').removeClass('table-success');
                }, 800);
            });

            dirtyShiftCells.clear();
        } catch (error) {
            payload.forEach((item) => {
                item.element.val(String(item.element.data('shift-id') || ''));
                item.element.closest('td').removeClass('table-warning');
            });

            alert(error.message || 'Update gagal');
        }
    }
</script>
@endpush
