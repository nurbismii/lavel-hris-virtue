@extends('layouts.app')

@section('title', 'Edit Jadwal Roster')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Edit Jadwal Roster</h4>
                <small class="text-muted">{{ optional($schedule->employee)->nama_karyawan }} · {{ $schedule->employee_nik }} · Periode {{ $schedule->period_label }}</small>
            </div>
            <div class="ms-md-auto"><a href="{{ route('roster-schedules.index', ['search' => $schedule->employee_nik]) }}" class="btn btn-light border">Kembali</a></div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-9">
                <form action="{{ route('roster-schedules.update', $schedule) }}" method="POST" id="rosterScheduleForm">
                    @csrf
                    @method('PUT')
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Mulai kerja</label>
                                    <input type="date" name="work_start" value="{{ old('work_start', $schedule->work_start->toDateString()) }}" class="form-control @error('work_start') is-invalid @enderror" required>
                                    @error('work_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Akhir kerja</label>
                                    <input type="date" name="work_end" value="{{ old('work_end', $schedule->work_end->toDateString()) }}" class="form-control @error('work_end') is-invalid @enderror" required>
                                    @error('work_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mulai off</label>
                                    <input type="date" name="off_start" value="{{ old('off_start', $schedule->off_start->toDateString()) }}" class="form-control @error('off_start') is-invalid @enderror" required>
                                    @error('off_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Akhir off</label>
                                    <input type="date" name="off_end" value="{{ old('off_end', $schedule->off_end->toDateString()) }}" class="form-control @error('off_end') is-invalid @enderror" required>
                                    @error('off_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Realisasi</label>
                                    <select name="realization_type" class="form-select" required>
                                        @foreach($realizationOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('realization_type', $schedule->realization_type) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch mb-2">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" class="form-check-input" name="is_active" value="1" id="isActive" @checked((bool)old('is_active', $schedule->is_active))>
                                        <label class="form-check-label" for="isActive">Jadwal aktif dan dapat menerima reminder</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Keterangan</label>
                                    <textarea name="notes" rows="4" maxlength="2000" class="form-control @error('notes') is-invalid @enderror" placeholder="Contoh: Periode II diambil sebagai insentif">{{ old('notes', $schedule->notes) }}</textarea>
                                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <div class="form-check border rounded p-3 ps-5 bg-light">
                                        <input type="hidden" name="regenerate_following" value="0">
                                        <input type="checkbox" class="form-check-input" name="regenerate_following" value="1" id="regenerateFollowing" @checked((bool)old('regenerate_following'))>
                                        <label class="form-check-label fw-semibold" for="regenerateFollowing">Generate ulang jadwal berikutnya yang masih “Menunggu pilihan”</label>
                                        <div class="small text-muted">Jadwal yang sudah ditandai Cuti Roster atau Insentif tidak digeser agar histori realisasi tetap aman.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                            <small class="text-muted">Perubahan tanggal akan mereset status reminder agar H-14 dihitung kembali.</small>
                            <button type="submit" class="btn btn-success" data-loading-text="Menyimpan...">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('rosterScheduleForm').addEventListener('submit', function () {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + button.dataset.loadingText;
    });
});
</script>
@endpush
