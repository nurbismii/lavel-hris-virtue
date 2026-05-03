@extends('layouts.app')

@php
$employee = Auth::user()->employee;
$selectedPlanType = old('tipe_rencana', optional($roster->periodeKerjaRoster)->tipe_rencana);
$weekLabels = ['MINGGU KE-1', 'MINGGU KE-2', 'MINGGU KE-3', 'MINGGU KE-4', 'MINGGU KE-5'];
$weekFields = [1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima'];
@endphp

@push('styles')
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="{{ versioned_asset('assets/css/user-roster-form.css') }}">
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-inner">
        <div class="wizard-wrap">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <h3 class="fw-bold text-primary mb-1">Edit Pengajuan Cuti Roster</h3>
                    <small class="text-muted">Versi step by step agar proses edit tetap nyaman di mobile dan konsisten dengan form create.</small>
                </div>
                <a href="{{ route('roster.index') }}" class="btn btn-sm btn-primary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
            </div>

            <form id="rosterWizardForm" action="{{ route('roster.update', $roster->id) }}" method="POST" enctype="multipart/form-data" data-off-dates-url="{{ Auth::user()->hasRole(['Staff Roster', 'Super Admin']) ? route('roster-off.effective-dates') : '' }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="nik_karyawan" value="{{ $employee->nik }}">

                <div class="card wizard-card">
                    <div class="card-body p-3 p-md-4">
                        <div class="wizard-head">
                            <div class="wizard-step active" data-step-indicator="1"><span class="wizard-num">1</span>
                                <div><span class="wizard-label">Langkah 1</span><span class="wizard-title">Data Karyawan</span></div>
                            </div>
                            <div class="wizard-step" data-step-indicator="2"><span class="wizard-num">2</span>
                                <div><span class="wizard-label">Langkah 2</span><span class="wizard-title">Periode Roster</span></div>
                            </div>
                            <div class="wizard-step" data-step-indicator="3"><span class="wizard-num">3</span>
                                <div><span class="wizard-label">Langkah 3</span><span class="wizard-title">Rencana</span></div>
                            </div>
                            <div class="wizard-step" data-step-indicator="4"><span class="wizard-num">4</span>
                                <div><span class="wizard-label">Langkah 4</span><span class="wizard-title">Perjalanan</span></div>
                            </div>
                        </div>

                        <section class="wizard-pane active" data-step-pane="1">
                            <div class="pane-head">
                                <div>
                                    <h5 class="pane-title">Informasi Karyawan</h5>
                                    <p class="pane-text">Pastikan identitas dasar dan kontak pengajuan masih sesuai sebelum disimpan ulang.</p>
                                </div>
                                <span class="pane-chip"><i class="fas fa-user-edit"></i> Mode edit</span>
                            </div>
                            <div class="box">
                                <div class="box-body">
                                    <div class="info-grid">
                                        <div class="info-item"><small>Nama</small><strong>{{ $employee->nama_karyawan }}</strong></div>
                                        <div class="info-item"><small>NIK</small><strong>{{ $employee->nik }}</strong></div>
                                        <div class="info-item"><small>Departemen</small><strong>{{ optional(optional($employee->divisi)->departemen)->departemen ?? '-' }}</strong></div>
                                        <div class="info-item"><small>Posisi</small><strong>{{ $employee->posisi ?? '-' }}</strong></div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $roster->email) }}" required></div>
                                        <div class="col-md-6"><label class="form-label">No HP</label><input type="text" name="no_telp" class="form-control" value="{{ old('no_telp', $roster->no_telp) }}" required></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-pane" data-step-pane="2">
                            <div class="pane-head">
                                <div>
                                    <h5 class="pane-title">Periode Roster Saat Ini</h5>
                                    <p class="pane-text">Perbarui periode aktif dan status mingguan jika ada perubahan pada jadwal kerja roster.</p>
                                </div>
                                <span class="pane-chip"><i class="fas fa-calendar-alt"></i> 5 minggu roster</span>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Periode Aktif</h6>
                                    <p class="box-text">Isi tanggal awal dan akhir periode roster yang sedang berjalan.</p>
                                </div>
                                <div class="box-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Periode Awal</label><input type="date" name="periode_awal" class="form-control" value="{{ old('periode_awal', optional($roster->periodeKerjaRoster)->periode_awal) }}" required></div>
                                        <div class="col-md-6"><label class="form-label">Periode Akhir</label><input type="date" name="periode_akhir" class="form-control" value="{{ old('periode_akhir', optional($roster->periodeKerjaRoster)->periode_akhir) }}" required></div>
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Status Mingguan</h6>
                                    <p class="box-text">Pilih OFF atau BEKERJA untuk setiap minggu dan masukkan tanggalnya.</p>
                                </div>
                                <div class="box-body">
                                    <div class="week-list">
                                        @foreach ($weekFields as $no => $field)
                                        @php
                                        $dateField = 'tanggal_' . $field;
                                        @endphp
                                        <div class="week-item">
                                            <div class="week-label">{{ $weekLabels[$no - 1] }}</div>
                                            <div>
                                                <label class="form-label">Status</label>
                                                <select name="{{ $field }}" class="form-select form-control">
                                                    <option value="OFF" {{ old($field, optional($roster->periodeKerjaRoster)->{$field}) === 'OFF' ? 'selected' : '' }}>OFF</option>
                                                    <option value="BEKERJA" {{ old($field, optional($roster->periodeKerjaRoster)->{$field}) === 'BEKERJA' ? 'selected' : '' }}>BEKERJA</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label">Tanggal</label>
                                                <input type="date" name="{{ $dateField }}" class="form-control" value="{{ old($dateField, optional($roster->periodeKerjaRoster)->{$dateField}) }}" required>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="alert alert-light border small mt-3 mb-0" id="approvedRosterOffList">
                                        Isi periode awal dan akhir untuk mendeteksi OFF roster yang sudah disetujui.
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-pane" data-step-pane="3">
                            <div class="pane-head">
                                <div>
                                    <h5 class="pane-title">Pilih Jenis Rencana</h5>
                                    <p class="pane-text">Sesuaikan jenis rencana dan ubah hanya bagian yang memang relevan untuk pengajuan ini.</p>
                                </div>
                                <span class="pane-chip"><i class="fas fa-layer-group"></i> Bagian tampil sesuai pilihan</span>
                            </div>
                            <div class="plan-grid">
                                <div class="plan-option">
                                    <input type="radio" name="tipe_rencana" value="1" id="roster" {{ (string) $selectedPlanType === '1' ? 'checked' : '' }} required>
                                    <label for="roster">
                                        <span class="plan-tag cuti"><i class="fas fa-umbrella-beach"></i> Cuti Roster</span>
                                        <strong>Atur cuti roster, tahunan, dan OFF</strong>
                                        <span class="desc">Gunakan bagian ini jika pengajuan roster berisi kombinasi cuti dan hari istirahat.</span>
                                    </label>
                                </div>
                                <div class="plan-option">
                                    <input type="radio" name="tipe_rencana" value="2" id="insentif" {{ (string) $selectedPlanType === '2' ? 'checked' : '' }}>
                                    <label for="insentif">
                                        <span class="plan-tag insentif"><i class="fas fa-briefcase"></i> Insentif</span>
                                        <strong>Atur periode kerja insentif</strong>
                                        <span class="desc">Pilih ini jika pengajuan yang sedang diedit fokus pada tambahan hari kerja insentif.</span>
                                    </label>
                                </div>
                            </div>

                            <div class="plan-panel {{ (string) $selectedPlanType === '1' ? 'active' : '' }}" id="planPanelCuti">
                                <div class="box">
                                    <div class="box-head">
                                        <h6 class="box-title">Jadwal Cuti Roster</h6>
                                        <p class="box-text">Perbarui rentang cuti roster, cuti tahunan, dan OFF. Sistem akan mengecek tumpang tindih otomatis.</p>
                                    </div>
                                    <div class="box-body">
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai cuti roster</label><input type="date" id="mulai_cuti_roster" name="tgl_mulai_cuti_roster" class="form-control" value="{{ old('tgl_mulai_cuti_roster', $roster->tgl_mulai_cuti) }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir cuti roster</label><input type="date" id="akhir_cuti_roster" name="tgl_berakhir_cuti_roster" class="form-control" value="{{ old('tgl_berakhir_cuti_roster', $roster->tgl_mulai_cuti_berakhir) }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai cuti tahunan</label><input type="date" id="mulai_cuti_tahunan" name="tgl_mulai_cuti_tahunan" class="form-control" value="{{ old('tgl_mulai_cuti_tahunan', $roster->tgl_mulai_cuti_tahunan) }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir cuti tahunan</label><input type="date" id="akhir_cuti_tahunan" name="tgl_berakhir_cuti_tahunan" class="form-control" value="{{ old('tgl_berakhir_cuti_tahunan', $roster->tgl_mulai_cuti_tahunan_berakhir) }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai off</label><input type="date" id="mulai_off" name="tgl_mulai_off" class="form-control" value="{{ old('tgl_mulai_off', $roster->tgl_mulai_off) }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir off</label><input type="date" id="akhir_off" name="tgl_berakhir_off" class="form-control" value="{{ old('tgl_berakhir_off', $roster->tgl_mulai_off_berakhir) }}"></div>
                                        </div>
                                        <div class="summary-grid">
                                            <div class="summary-item"><small>Cuti Roster</small><strong><span id="total_cuti_roster">0</span> Hari</strong></div>
                                            <div class="summary-item"><small>Cuti Tahunan</small><strong><span id="total_cuti_tahunan">0</span> Hari</strong></div>
                                            <div class="summary-item"><small>OFF</small><strong><span id="total_off">0</span> Hari</strong></div>
                                        </div>
                                        <div class="total-banner">
                                            <div><small>Total Keseluruhan Roster</small><strong id="grand_total">0 Hari</strong></div>
                                            <i class="fas fa-calendar-check fa-lg"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="plan-panel {{ (string) $selectedPlanType === '2' ? 'active' : '' }}" id="planPanelInsentif">
                                <div class="box">
                                    <div class="box-head">
                                        <h6 class="box-title">Jadwal Insentif</h6>
                                        <p class="box-text">Perbarui rentang kerja insentif. Total akan dihitung dari minggu bekerja dan durasi insentif.</p>
                                    </div>
                                    <div class="box-body">
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai insentif</label><input type="date" id="tgl_awal_kerja" name="tgl_awal_kerja" class="form-control" value="{{ old('tgl_awal_kerja', $roster->tgl_awal_kerja) }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir insentif</label><input type="date" id="tgl_akhir_kerja" name="tgl_akhir_kerja" class="form-control" value="{{ old('tgl_akhir_kerja', $roster->tgl_akhir_kerja) }}"></div>
                                        </div>
                                        <div class="summary-grid summary-grid--two-columns">
                                            <div class="summary-item"><small>Hari Insentif</small><strong><span id="total_insentif">0</span> Hari</strong></div>
                                            <div class="summary-item"><small>Status Bekerja</small><strong><span id="jumlah_bekerja">0</span> Minggu</strong></div>
                                        </div>
                                        <div class="total-banner success">
                                            <div><small>Total Keseluruhan Insentif</small><strong id="grand_total_insentif">0 Hari</strong></div>
                                            <i class="fas fa-briefcase fa-lg"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-pane" data-step-pane="4">
                            <div class="pane-head">
                                <div>
                                    <h5 class="pane-title">Detail Perjalanan dan Berkas</h5>
                                    <p class="pane-text">Periksa ulang jadwal perjalanan dan ganti berkas pendukung bila memang ada pembaruan.</p>
                                </div>
                                <span class="pane-chip"><i class="fas fa-plane-departure"></i> Langkah terakhir</span>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Detail Keberangkatan</h6>
                                    <p class="box-text">Perbarui tanggal, jam, rute, dan catatan keberangkatan bila ada perubahan.</p>
                                </div>
                                <div class="box-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Tanggal Keberangkatan</label><input type="date" name="tanggal_keberangkatan" class="form-control" value="{{ old('tanggal_keberangkatan', $roster->tgl_keberangkatan) }}"></div>
                                        <div class="col-md-6"><label class="form-label">Jam Keberangkatan</label><input type="text" name="jam_keberangkatan" class="form-control" placeholder="07:00" value="{{ old('jam_keberangkatan', $roster->jam_keberangkatan) }}"></div>
                                        <div class="col-md-6"><label class="form-label">Dari</label><select name="kota_awal_keberangkatan" class="form-select form-control search-airport" data-placeholder="Cari bandara keberangkatan...">@if($roster->kota_awal_keberangkatan)<option value="{{ $roster->kota_awal_keberangkatan }}" selected>{{ $roster->kota_awal_keberangkatan }}</option>@endif</select></div>
                                        <div class="col-md-6"><label class="form-label">Tujuan</label><select name="kota_tujuan_keberangkatan" class="form-select form-control search-airport" data-placeholder="Cari bandara tujuan...">@if($roster->kota_tujuan_keberangkatan)<option value="{{ $roster->kota_tujuan_keberangkatan }}" selected>{{ $roster->kota_tujuan_keberangkatan }}</option>@endif</select></div>
                                        <div class="col-md-12"><label class="form-label">Catatan Penting</label><textarea name="catatan_penting_keberangkatan" class="form-control" rows="4">{{ old('catatan_penting_keberangkatan', $roster->catatan_penting_keberangkatan) }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Detail Kepulangan</h6>
                                    <p class="box-text">Perbarui detail perjalanan pulang bila ada perubahan jadwal atau rute.</p>
                                </div>
                                <div class="box-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Tanggal Kepulangan</label><input type="date" name="tanggal_kepulangan" class="form-control" value="{{ old('tanggal_kepulangan', $roster->tgl_kepulangan) }}"></div>
                                        <div class="col-md-6"><label class="form-label">Jam Kepulangan</label><input type="text" name="jam_kepulangan" class="form-control" placeholder="07:00" value="{{ old('jam_kepulangan', $roster->jam_kepulangan) }}"></div>
                                        <div class="col-md-6"><label class="form-label">Dari</label><select name="kota_awal_kepulangan" class="form-select form-control search-airport" data-placeholder="Cari bandara asal pulang...">@if($roster->kota_awal_kepulangan)<option value="{{ $roster->kota_awal_kepulangan }}" selected>{{ $roster->kota_awal_kepulangan }}</option>@endif</select></div>
                                        <div class="col-md-6"><label class="form-label">Tujuan</label><select name="kota_tujuan_kepulangan" class="form-select form-control search-airport" data-placeholder="Cari bandara tujuan pulang...">@if($roster->kota_tujuan_kepulangan)<option value="{{ $roster->kota_tujuan_kepulangan }}" selected>{{ $roster->kota_tujuan_kepulangan }}</option>@endif</select></div>
                                        <div class="col-md-12"><label class="form-label">Catatan Penting</label><textarea name="catatan_penting_kepulangan" class="form-control" rows="4">{{ old('catatan_penting_kepulangan', $roster->catatan_penting_kepulangan) }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Berkas Pendukung</h6>
                                    <p class="box-text">Upload file baru bila perlu mengganti berkas lama.</p>
                                </div>
                                <div class="box-body">
                                    <div class="upload-box">
                                        <label class="form-label fw-semibold">Upload Berkas Baru</label>
                                        <input type="file" name="berkas_cuti" class="form-control">
                                        @if($roster->file)
                                        <a href="{{ route('roster.attachment', $roster->id) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-3">
                                            Lihat File Lama
                                        </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="wizard-footer">
                            <button type="button" class="btn btn-light border d-none" id="wizardPrevBtn"><i class="fas fa-arrow-left me-2"></i>Sebelumnya</button>
                            <button type="button" class="btn btn-primary" id="wizardNextBtn">Lanjutkan<i class="fas fa-arrow-right ms-2"></i></button>
                            <button type="submit" class="btn btn-success d-none" id="wizardSubmitBtn"><i class="fas fa-save me-2"></i>Simpan Perubahan</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('rosterWizardForm');
        const panes = Array.from(document.querySelectorAll('[data-step-pane]'));
        const steps = Array.from(document.querySelectorAll('[data-step-indicator]'));
        const prevBtn = document.getElementById('wizardPrevBtn');
        const nextBtn = document.getElementById('wizardNextBtn');
        const submitBtn = document.getElementById('wizardSubmitBtn');
        const planInputs = document.querySelectorAll('input[name="tipe_rencana"]');
        const cutiPanel = document.getElementById('planPanelCuti');
        const insentifPanel = document.getElementById('planPanelInsentif');
        const weekNames = ['satu', 'dua', 'tiga', 'empat', 'lima'];
        const offDatesUrl = form.dataset.offDatesUrl || '';
        const approvedRosterOffList = document.getElementById('approvedRosterOffList');
        let approvedRosterOffDates = new Map();
        let currentStep = 1;

        function initAirportSelects() {
            $('.search-airport').each(function() {
                if ($(this).data('select2')) return;
                $(this).select2({
                    width: '100%',
                    placeholder: $(this).data('placeholder') || 'Cari bandara...',
                    allowClear: true,
                    ajax: {
                        url: '/api/airports',
                        dataType: 'json',
                        delay: 250,
                        processResults: function(data) {
                            return {
                                results: $.map(data, function(item) {
                                    return {
                                        id: item.name,
                                        text: item.name + ' | ' + item.iata_code
                                    };
                                })
                            };
                        },
                        cache: true
                    }
                });
            });
        }

        function showPlanPanel() {
            const selected = document.querySelector('input[name="tipe_rencana"]:checked');
            const type = selected ? selected.value : '';
            cutiPanel.classList.toggle('active', type === '1');
            insentifPanel.classList.toggle('active', type === '2');
        }

        function syncStep() {
            panes.forEach(function(pane, index) {
                pane.classList.toggle('active', index + 1 === currentStep);
            });

            steps.forEach(function(step, index) {
                const no = index + 1;
                step.classList.toggle('active', no === currentStep);
                step.classList.toggle('done', no < currentStep);
            });

            prevBtn.classList.toggle('d-none', currentStep === 1);
            nextBtn.classList.toggle('d-none', currentStep === panes.length);
            submitBtn.classList.toggle('d-none', currentStep !== panes.length);

            if (currentStep === 4) initAirportSelects();

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        function rosterWeekRows() {
            return weekNames.map(function(fieldName) {
                return {
                    status: document.querySelector('[name="' + fieldName + '"]'),
                    date: document.querySelector('[name="tanggal_' + fieldName + '"]')
                };
            });
        }

        function renderApprovedRosterOffList(items) {
            if (!approvedRosterOffList) return;

            if (!offDatesUrl) {
                approvedRosterOffList.innerHTML = 'Auto-detect OFF hanya aktif untuk akun karyawan roster.';
                return;
            }

            if (!items.length) {
                approvedRosterOffList.innerHTML = 'Belum ada OFF roster efektif pada rentang periode ini.';
                return;
            }

            approvedRosterOffList.innerHTML = '<strong>OFF roster efektif pada periode ini:</strong> ' + items.map(function(item) {
                return item.date;
            }).join(', ');
        }

        function applyApprovedRosterOffDates() {
            const rows = rosterWeekRows();

            approvedRosterOffDates.forEach(function(item, date) {
                const alreadyUsed = rows.some(function(row) {
                    return row.date && row.date.value === date;
                });

                if (alreadyUsed) return;

                const emptyRow = rows.find(function(row) {
                    return row.date && !row.date.value;
                });

                if (emptyRow && emptyRow.date && emptyRow.status) {
                    emptyRow.date.value = date;
                    emptyRow.status.value = 'OFF';
                }
            });

            rows.forEach(function(row) {
                if (!row.status || !row.date || !row.date.value) return;

                if (approvedRosterOffDates.has(row.date.value)) {
                    row.status.value = 'OFF';
                    row.status.classList.add('is-valid');
                    row.status.title = 'Status otomatis OFF karena tanggal ini ada di pengajuan OFF roster efektif.';
                } else {
                    row.status.classList.remove('is-valid');
                    row.status.removeAttribute('title');
                }
            });
        }

        function fetchApprovedRosterOffDates() {
            if (!offDatesUrl) {
                renderApprovedRosterOffList([]);
                return;
            }

            const start = document.querySelector('[name="periode_awal"]').value;
            const end = document.querySelector('[name="periode_akhir"]').value;

            if (!start || !end) {
                approvedRosterOffDates = new Map();
                renderApprovedRosterOffList([]);
                applyApprovedRosterOffDates();
                return;
            }

            const url = offDatesUrl + '?periode_awal=' + encodeURIComponent(start) + '&periode_akhir=' + encodeURIComponent(end);

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(function(response) {
                    return response.ok ? response.json() : { data: [] };
                })
                .then(function(payload) {
                    const items = payload.data || [];
                    approvedRosterOffDates = new Map(items.map(function(item) {
                        return [item.date, item];
                    }));
                    renderApprovedRosterOffList(items);
                    applyApprovedRosterOffDates();
                })
                .catch(function() {
                    approvedRosterOffDates = new Map();
                    renderApprovedRosterOffList([]);
                    applyApprovedRosterOffDates();
                });
        }

        function validateStep() {
            const pane = panes[currentStep - 1];
            if (!pane) return true;

            const fields = Array.from(pane.querySelectorAll('input, select, textarea'));
            for (const field of fields) {
                if (field.disabled || field.type === 'hidden' || field.type === 'file') continue;

                const hiddenPanel = field.closest('.plan-panel');
                if (hiddenPanel && !hiddenPanel.classList.contains('active')) continue;

                if (typeof field.reportValidity === 'function' && !field.reportValidity()) {
                    return false;
                }
            }

            return true;
        }

        nextBtn.addEventListener('click', function() {
            if (!validateStep()) return;
            if (currentStep < panes.length) {
                currentStep += 1;
                syncStep();
            }
        });

        prevBtn.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep -= 1;
                syncStep();
            }
        });

        form.addEventListener('submit', function(event) {
            if (!form.reportValidity()) event.preventDefault();
        });

        function parseDate(id) {
            const el = document.getElementById(id);
            if (!el || !el.value) return null;
            const date = new Date(el.value);
            date.setHours(0, 0, 0, 0);
            return date;
        }

        function hitungHari(mulaiId, akhirId) {
            const mulai = parseDate(mulaiId);
            const akhir = parseDate(akhirId);
            if (mulai && akhir && akhir >= mulai) return ((akhir - mulai) / 86400000) + 1;
            return 0;
        }

        function isOverlap(start1, end1, start2, end2) {
            return start1 <= end2 && end1 >= start2;
        }

        function showOverlapWarning(message) {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                window.Swal.fire('Perhatian!', message, 'warning');
                return;
            }

            if (typeof window.swal === 'function') {
                window.swal({
                    title: 'Perhatian!',
                    text: message,
                    icon: 'warning'
                });
                return;
            }

            window.alert(message);
        }

        function highlightConflictFields(fieldIds) {
            fieldIds.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);

                if (!field) {
                    return;
                }

                if (field.dataset.overlapTimer) {
                    clearTimeout(Number(field.dataset.overlapTimer));
                }

                field.classList.remove('is-overlap-range');
                void field.offsetWidth;
                field.classList.add('is-overlap-range');

                const clearHighlight = function() {
                    field.classList.remove('is-overlap-range');
                    delete field.dataset.overlapTimer;
                    field.removeEventListener('input', clearHighlight);
                    field.removeEventListener('change', clearHighlight);
                };

                field.addEventListener('input', clearHighlight, {
                    once: true
                });
                field.addEventListener('change', clearHighlight, {
                    once: true
                });

                field.dataset.overlapTimer = String(window.setTimeout(clearHighlight, 3200));
            });
        }

        function resetRange(startId, endId) {
            const startField = document.getElementById(startId);
            const endField = document.getElementById(endId);
            if (startField) startField.value = '';
            if (endField) endField.value = '';
        }

        function renderRosterTotals() {
            const totalRoster = hitungHari('mulai_cuti_roster', 'akhir_cuti_roster');
            const totalTahunan = hitungHari('mulai_cuti_tahunan', 'akhir_cuti_tahunan');
            const totalOff = hitungHari('mulai_off', 'akhir_off');

            document.getElementById('total_cuti_roster').innerText = totalRoster;
            document.getElementById('total_cuti_tahunan').innerText = totalTahunan;
            document.getElementById('total_off').innerText = totalOff;
            document.getElementById('grand_total').innerText = (totalRoster + totalTahunan + totalOff) + ' Hari';
        }

        function cekTumpangTindih() {
            const rStart = parseDate('mulai_cuti_roster');
            const rEnd = parseDate('akhir_cuti_roster');
            const tStart = parseDate('mulai_cuti_tahunan');
            const tEnd = parseDate('akhir_cuti_tahunan');
            const oStart = parseDate('mulai_off');
            const oEnd = parseDate('akhir_off');

            if (rStart && rEnd && tStart && tEnd && isOverlap(rStart, rEnd, tStart, tEnd)) {
                resetRange('mulai_cuti_tahunan', 'akhir_cuti_tahunan');
                highlightConflictFields(['mulai_cuti_tahunan', 'akhir_cuti_tahunan']);
                renderRosterTotals();
                showOverlapWarning('Cuti Tahunan tidak boleh tumpang tindih dengan Cuti Roster!');
                return true;
            }
            if (rStart && rEnd && oStart && oEnd && isOverlap(rStart, rEnd, oStart, oEnd)) {
                resetRange('mulai_off', 'akhir_off');
                highlightConflictFields(['mulai_off', 'akhir_off']);
                renderRosterTotals();
                showOverlapWarning('OFF tidak boleh tumpang tindih dengan Cuti Roster!');
                return true;
            }
            if (tStart && tEnd && oStart && oEnd && isOverlap(tStart, tEnd, oStart, oEnd)) {
                resetRange('mulai_off', 'akhir_off');
                highlightConflictFields(['mulai_off', 'akhir_off']);
                renderRosterTotals();
                showOverlapWarning('OFF tidak boleh tumpang tindih dengan Cuti Tahunan!');
                return true;
            }
            return false;
        }

        function updateTotal() {
            if (cekTumpangTindih()) return;
            renderRosterTotals();
        }

        function updateInsentifRoster() {
            let jumlahBekerja = 0;
            weekNames.forEach(function(fieldName) {
                const field = document.querySelector('[name="' + fieldName + '"]');
                if (field && field.value === 'BEKERJA') jumlahBekerja++;
            });

            const totalInsentif = hitungHari('tgl_awal_kerja', 'tgl_akhir_kerja');
            document.getElementById('jumlah_bekerja').innerText = jumlahBekerja;
            document.getElementById('total_insentif').innerText = totalInsentif;
            document.getElementById('grand_total_insentif').innerText = (jumlahBekerja + totalInsentif) + ' Hari';
        }

        document.querySelectorAll('input[type="date"]').forEach(function(el) {
            el.addEventListener('change', updateTotal);
        });

        document.querySelectorAll('[name="periode_awal"], [name="periode_akhir"]').forEach(function(el) {
            el.addEventListener('change', fetchApprovedRosterOffDates);
        });

        rosterWeekRows().forEach(function(row) {
            if (row.date) row.date.addEventListener('change', applyApprovedRosterOffDates);
        });

        document.querySelectorAll('#tgl_awal_kerja, #tgl_akhir_kerja').forEach(function(el) {
            el.addEventListener('change', updateInsentifRoster);
        });

        weekNames.forEach(function(fieldName) {
            const field = document.querySelector('[name="' + fieldName + '"]');
            if (field) field.addEventListener('change', updateInsentifRoster);
        });

        planInputs.forEach(function(input) {
            input.addEventListener('change', showPlanPanel);
        });

        showPlanPanel();
        syncStep();
        updateTotal();
        updateInsentifRoster();
        fetchApprovedRosterOffDates();
    });
</script>
@endpush
@endsection
