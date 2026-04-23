@extends('layouts.app')

@php
$employee = Auth::user()->employee;
$selectedPlanType = old('tipe_rencana');
$weekLabels = ['MINGGU KE-1', 'MINGGU KE-2', 'MINGGU KE-3', 'MINGGU KE-4', 'MINGGU KE-5'];
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
                    <h3 class="fw-bold text-primary mb-1">Formulir Pengajuan Cuti Roster</h3>
                    <small class="text-muted">Versi step by step agar lebih nyaman di mobile dan tidak terlalu panjang ke bawah.</small>
                </div>
                <a href="{{ route('roster.index') }}" class="btn btn-sm btn-primary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
            </div>

            <form id="rosterWizardForm" action="{{ route('roster.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
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
                                    <p class="pane-text">Pastikan identitas dasar dan kontak yang dipakai pada pengajuan ini sudah benar.</p>
                                </div>
                                <span class="pane-chip"><i class="fas fa-user-check"></i> Siap diverifikasi</span>
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
                                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', Auth::user()->email) }}" required></div>
                                        <div class="col-md-6"><label class="form-label">No HP</label><input type="text" name="no_telp" class="form-control" value="{{ old('no_telp', $employee->no_telp) }}" required></div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-pane" data-step-pane="2">
                            <div class="pane-head">
                                <div>
                                    <h5 class="pane-title">Periode Roster Saat Ini</h5>
                                    <p class="pane-text">Tentukan periode aktif dan isi status mingguan agar perhitungan rencana lebih jelas.</p>
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
                                        <div class="col-md-6"><label class="form-label">Periode Awal</label><input type="date" name="periode_awal" class="form-control" value="{{ old('periode_awal') }}" required></div>
                                        <div class="col-md-6"><label class="form-label">Periode Akhir</label><input type="date" name="periode_akhir" class="form-control" value="{{ old('periode_akhir') }}" required></div>
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
                                        @foreach ($weekLabels as $index => $label)
                                        <div class="week-item">
                                            <div class="week-label">{{ $label }}</div>
                                            <div>
                                                <label class="form-label">Status</label>
                                                <select name="hari_{{ $index + 1 }}" class="form-select form-control">
                                                    <option value="OFF" {{ old('hari_' . ($index + 1), 'OFF') === 'OFF' ? 'selected' : '' }}>OFF</option>
                                                    <option value="BEKERJA" {{ old('hari_' . ($index + 1)) === 'BEKERJA' ? 'selected' : '' }}>BEKERJA</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label">Tanggal</label>
                                                <input type="date" name="tanggal_{{ $index + 1 }}" class="form-control" value="{{ old('tanggal_' . ($index + 1)) }}" required>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-pane" data-step-pane="3">
                            <div class="pane-head">
                                <div>
                                    <h5 class="pane-title">Pilih Jenis Rencana</h5>
                                    <p class="pane-text">Tentukan apakah pengajuan ini untuk cuti roster atau insentif, lalu isi bagian yang relevan saja.</p>
                                </div>
                                <span class="pane-chip"><i class="fas fa-layer-group"></i> Bagian tampil sesuai pilihan</span>
                            </div>
                            <div class="plan-grid">
                                <div class="plan-option">
                                    <input type="radio" name="tipe_rencana" value="1" id="roster" {{ $selectedPlanType == '1' ? 'checked' : '' }} required>
                                    <label for="roster">
                                        <span class="plan-tag cuti"><i class="fas fa-umbrella-beach"></i> Cuti Roster</span>
                                        <strong>Atur cuti roster, tahunan, dan OFF</strong>
                                        <span class="desc">Untuk pengajuan roster yang fokus ke jadwal istirahat dan hari cuti.</span>
                                    </label>
                                </div>
                                <div class="plan-option">
                                    <input type="radio" name="tipe_rencana" value="2" id="insentif" {{ $selectedPlanType == '2' ? 'checked' : '' }}>
                                    <label for="insentif">
                                        <span class="plan-tag insentif"><i class="fas fa-briefcase"></i> Insentif</span>
                                        <strong>Atur periode kerja insentif</strong>
                                        <span class="desc">Untuk pengajuan difokuskan ke tambahan hari kerja insentif.</span>
                                    </label>
                                </div>
                            </div>

                            <div class="plan-panel {{ $selectedPlanType == '1' ? 'active' : '' }}" id="planPanelCuti">
                                <div class="box">
                                    <div class="box-head">
                                        <h6 class="box-title">Jadwal Cuti Roster</h6>
                                        <p class="box-text">Isi rentang cuti roster, cuti tahunan, dan OFF. Sistem akan mengecek tumpang tindih otomatis.</p>
                                    </div>
                                    <div class="box-body">
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai cuti roster</label><input type="date" id="mulai_cuti_roster" name="tgl_mulai_cuti_roster" class="form-control" value="{{ old('tgl_mulai_cuti_roster') }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir cuti roster</label><input type="date" id="akhir_cuti_roster" name="tgl_berakhir_cuti_roster" class="form-control" value="{{ old('tgl_berakhir_cuti_roster') }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai cuti tahunan</label><input type="date" id="mulai_cuti_tahunan" name="tgl_mulai_cuti_tahunan" class="form-control" value="{{ old('tgl_mulai_cuti_tahunan') }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir cuti tahunan</label><input type="date" id="akhir_cuti_tahunan" name="tgl_berakhir_cuti_tahunan" class="form-control" value="{{ old('tgl_berakhir_cuti_tahunan') }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai off</label><input type="date" id="mulai_off" name="tgl_mulai_off" class="form-control" value="{{ old('tgl_mulai_off') }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir off</label><input type="date" id="akhir_off" name="tgl_berakhir_off" class="form-control" value="{{ old('tgl_berakhir_off') }}"></div>
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

                            <div class="plan-panel {{ $selectedPlanType == '2' ? 'active' : '' }}" id="planPanelInsentif">
                                <div class="box">
                                    <div class="box-head">
                                        <h6 class="box-title">Jadwal Insentif</h6>
                                        <p class="box-text">Isi rentang kerja insentif. Grand total dihitung dari jumlah minggu bekerja dan durasi insentif.</p>
                                    </div>
                                    <div class="box-body">
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">Tanggal mulai insentif</label><input type="date" id="tgl_awal_kerja" name="tgl_awal_kerja" class="form-control" value="{{ old('tgl_awal_kerja') }}"></div>
                                            <div class="col-md-6"><label class="form-label">Tanggal akhir insentif</label><input type="date" id="tgl_akhir_kerja" name="tgl_akhir_kerja" class="form-control" value="{{ old('tgl_akhir_kerja') }}"></div>
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
                                    <p class="pane-text">Lengkapi jadwal keberangkatan, kepulangan, dan lampiran pendukung sebelum kirim pengajuan.</p>
                                </div>
                                <span class="pane-chip"><i class="fas fa-plane-departure"></i> Langkah terakhir</span>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Detail Keberangkatan</h6>
                                    <p class="box-text">Isi tanggal, jam, kota awal, kota tujuan, dan catatan penting.</p>
                                </div>
                                <div class="box-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Tanggal Keberangkatan</label><input type="date" name="tanggal_keberangkatan" class="form-control" value="{{ old('tanggal_keberangkatan') }}"></div>
                                        <div class="col-md-6"><label class="form-label">Jam Keberangkatan</label><input type="text" name="jam_keberangkatan" class="form-control" placeholder="07:00" value="{{ old('jam_keberangkatan') }}"></div>
                                        <div class="col-md-6"><label class="form-label">Dari</label><select name="kota_awal_keberangkatan" class="form-select form-control search-airport" data-placeholder="Cari bandara keberangkatan...">@if(old('kota_awal_keberangkatan'))<option value="{{ old('kota_awal_keberangkatan') }}" selected>{{ old('kota_awal_keberangkatan') }}</option>@endif</select></div>
                                        <div class="col-md-6"><label class="form-label">Tujuan</label><select name="kota_tujuan_keberangkatan" class="form-select form-control search-airport" data-placeholder="Cari bandara tujuan...">@if(old('kota_tujuan_keberangkatan'))<option value="{{ old('kota_tujuan_keberangkatan') }}" selected>{{ old('kota_tujuan_keberangkatan') }}</option>@endif</select></div>
                                        <div class="col-md-12"><label class="form-label">Catatan Penting</label><textarea name="catatan_penting_keberangkatan" class="form-control" rows="4">{{ old('catatan_penting_keberangkatan') }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Detail Kepulangan</h6>
                                    <p class="box-text">Isi detail perjalanan pulang dan catatan tambahan bila diperlukan.</p>
                                </div>
                                <div class="box-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Tanggal Kepulangan</label><input type="date" name="tanggal_kepulangan" class="form-control" value="{{ old('tanggal_kepulangan') }}"></div>
                                        <div class="col-md-6"><label class="form-label">Jam Kepulangan</label><input type="text" name="jam_kepulangan" class="form-control" placeholder="07:00" value="{{ old('jam_kepulangan') }}"></div>
                                        <div class="col-md-6"><label class="form-label">Dari</label><select name="kota_awal_kepulangan" class="form-select form-control search-airport" data-placeholder="Cari bandara asal pulang...">@if(old('kota_awal_kepulangan'))<option value="{{ old('kota_awal_kepulangan') }}" selected>{{ old('kota_awal_kepulangan') }}</option>@endif</select></div>
                                        <div class="col-md-6"><label class="form-label">Tujuan</label><select name="kota_tujuan_kepulangan" class="form-select form-control search-airport" data-placeholder="Cari bandara tujuan pulang..." required>@if(old('kota_tujuan_kepulangan'))<option value="{{ old('kota_tujuan_kepulangan') }}" selected>{{ old('kota_tujuan_kepulangan') }}</option>@endif</select></div>
                                        <div class="col-md-12"><label class="form-label">Catatan Penting</label><textarea name="catatan_penting_kepulangan" class="form-control" rows="4">{{ old('catatan_penting_kepulangan') }}</textarea></div>
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-head">
                                    <h6 class="box-title">Berkas Pendukung</h6>
                                    <p class="box-text">Upload berkas bila diperlukan untuk membantu proses approval.</p>
                                </div>
                                <div class="box-body">
                                    <div class="upload-box">
                                        <label class="form-label fw-semibold">Upload Berkas</label>
                                        <input type="file" name="berkas_cuti" class="form-control">
                                        <small class="text-muted d-block mt-2">File bersifat opsional.</small>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="wizard-footer">
                            <button type="button" class="btn btn-light border d-none" id="wizardPrevBtn"><i class="fas fa-arrow-left me-2"></i>Sebelumnya</button>
                            <button type="button" class="btn btn-primary" id="wizardNextBtn">Lanjutkan<i class="fas fa-arrow-right ms-2"></i></button>
                            <button type="submit" class="btn btn-success d-none" id="wizardSubmitBtn"><i class="fas fa-save me-2"></i>Simpan Pengajuan</button>
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

        function validateStep() {
            const pane = panes[currentStep - 1];
            if (!pane) return true;
            const fields = Array.from(pane.querySelectorAll('input, select, textarea'));
            for (const field of fields) {
                if (field.disabled || field.type === 'hidden' || field.type === 'file') continue;
                const hiddenPanel = field.closest('.plan-panel');
                if (hiddenPanel && !hiddenPanel.classList.contains('active')) continue;
                if (typeof field.reportValidity === 'function' && !field.reportValidity()) return false;
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

            field.addEventListener('input', clearHighlight, { once: true });
            field.addEventListener('change', clearHighlight, { once: true });

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
            for (let i = 1; i <= 5; i++) {
                const field = document.querySelector('[name="hari_' + i + '"]');
                if (field && field.value === 'BEKERJA') jumlahBekerja++;
            }
            const totalInsentif = hitungHari('tgl_awal_kerja', 'tgl_akhir_kerja');
            document.getElementById('jumlah_bekerja').innerText = jumlahBekerja;
            document.getElementById('total_insentif').innerText = totalInsentif;
            document.getElementById('grand_total_insentif').innerText = (jumlahBekerja + totalInsentif) + ' Hari';
        }

        document.querySelectorAll('input[type="date"]').forEach(function(el) {
            el.addEventListener('change', updateTotal);
        });
        document.querySelectorAll('#tgl_awal_kerja, #tgl_akhir_kerja').forEach(function(el) {
            el.addEventListener('change', updateInsentifRoster);
        });
        document.querySelectorAll('[name^="hari_"]').forEach(function(el) {
            el.addEventListener('change', updateInsentifRoster);
        });
        planInputs.forEach(function(input) {
            input.addEventListener('change', showPlanPanel);
        });

        showPlanPanel();
        syncStep();
        updateTotal();
        updateInsentifRoster();
    });
</script>
@endpush
@endsection
