@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h3 class="text-primary mb-1">Ajukan Koreksi Presensi</h3>
                <small class="text-muted">Isi hanya bagian yang perlu dikoreksi. Pengajuan akan direview HOD lalu HR.</small>
            </div>
            <a href="{{ route('attendance-corrections.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('attendance-corrections.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label for="tanggal" class="form-label">Tanggal Presensi <span class="text-danger">*</span></label>
                                <input type="date" id="tanggal" name="tanggal" class="form-control @error('tanggal') is-invalid @enderror" value="{{ old('tanggal') }}" max="{{ now()->toDateString() }}" required>
                                @error('tanggal')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="jam_masuk" class="form-label">Jam Masuk</label>
                                    <input type="time" id="jam_masuk" name="jam_masuk" class="form-control @error('jam_masuk') is-invalid @enderror" value="{{ old('jam_masuk') }}">
                                    @error('jam_masuk')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="jam_istirahat" class="form-label">Jam Istirahat</label>
                                    <input type="time" id="jam_istirahat" name="jam_istirahat" class="form-control @error('jam_istirahat') is-invalid @enderror" value="{{ old('jam_istirahat') }}">
                                    @error('jam_istirahat')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="jam_kembali_istirahat" class="form-label">Jam Kembali Istirahat</label>
                                    <input type="time" id="jam_kembali_istirahat" name="jam_kembali_istirahat" class="form-control @error('jam_kembali_istirahat') is-invalid @enderror" value="{{ old('jam_kembali_istirahat') }}">
                                    @error('jam_kembali_istirahat')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="jam_pulang" class="form-label">Jam Pulang</label>
                                    <input type="time" id="jam_pulang" name="jam_pulang" class="form-control @error('jam_pulang') is-invalid @enderror" value="{{ old('jam_pulang') }}">
                                    @error('jam_pulang')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="status_presensi" class="form-label">Status Harian Khusus</label>
                                <select id="status_presensi" name="status_presensi" class="form-select @error('status_presensi') is-invalid @enderror">
                                    <option value="">Tidak mengubah status harian khusus</option>
                                    <option value="__clear__" {{ old('status_presensi') === '__clear__' ? 'selected' : '' }}>Hadir normal - gunakan jam presensi</option>
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('status_presensi') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Gunakan hanya untuk status full-day seperti Off, Cuti, Izin, atau Libur Nasional. Jika lupa absen tetapi hadir, isi jam koreksi dan pilih hadir normal.</small>
                                @error('status_presensi')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mt-3">
                                <label for="reason" class="form-label">Alasan Koreksi <span class="text-danger">*</span></label>
                                <textarea id="reason" name="reason" rows="4" class="form-control @error('reason') is-invalid @enderror" maxlength="2000" required>{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mt-3">
                                <label for="attachment" class="form-label">Bukti Lampiran</label>
                                <input type="file" id="attachment" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                <small class="text-muted">Opsional. Format JPG, PNG, WEBP, atau PDF maksimal 5MB.</small>
                                @error('attachment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ route('attendance-corrections.index') }}" class="btn btn-outline-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i> Kirim Pengajuan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Presensi Terakhir</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th>Jam</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentPresensi as $item)
                                        <tr>
                                            <td>{{ formatDateIndonesia($item->tanggal) }}</td>
                                            <td>{{ $item->status_presensi ?: 'Hadir normal' }}</td>
                                            <td class="small">
                                                M {{ optional($item->jam_masuk)->format('H:i') ?: '-' }} /
                                                I {{ optional($item->jam_istirahat)->format('H:i') ?: '-' }} /
                                                K {{ optional($item->jam_kembali_istirahat)->format('H:i') ?: '-' }} /
                                                P {{ optional($item->jam_pulang)->format('H:i') ?: '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-3">Belum ada data presensi.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted d-block mt-2">Data ini hanya referensi cepat. HR akan menerapkan koreksi setelah HOD menyetujui.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
