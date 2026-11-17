@extends('layouts.app')
@section('title', 'Detail Pengajuan Pesangon')
@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('assets/css/severance.css') }}">@endpush
@section('content')
@php
$actor = auth()->user();
@endphp
<div class="container-fluid">
    <div class="page-inner salary-page severance-page">
        <header class="salary-header">
            <div><span class="salary-eyebrow">{{ $entry->employee_nik }} · Pengajuan #{{ $entry->id }}</span>
                <h1>{{ $entry->employee_name }}</h1><span class="severance-badge severance-badge--{{ $entry->status }}">{{ \App\Models\SeveranceRequest::statuses()[$entry->status] }}</span>
            </div><a class="btn btn-light" href="{{ route('severance.batch', $entry->import_history_id) }}">Kembali ke batch</a>
        </header>
        <section class="salary-card severance-next-step" aria-labelledby="next-step-title">
            <div class="salary-card-body">
                <h2 id="next-step-title">Langkah berikutnya</h2>
                @if($entry->status === 'draft')
                    @if($canEdit)
                        <p>Lengkapi dan simpan draf di bawah, lalu buka batch untuk mengirim draf ke Supervisor Comben/Payroll.</p>
                        <a class="btn btn-primary" href="{{ route('severance.batch', $entry->import_history_id) }}">Buka batch dan kirim draf</a>
                    @else
                        <p>Draf masih disiapkan oleh staf pengaju. Persetujuan dimulai setelah draf dikirim ke Supervisor Comben/Payroll.</p>
                    @endif
                @elseif($entry->status === 'payroll_review')
                    @if($canPayroll)
                        @if($salary)
                            <p>Periksa gaji pokok, tunjangan jabatan, dan tunjangan masa kerja, lalu setujui upah untuk meneruskan pengajuan ke Supervisor HI.</p>
                            <a class="btn btn-primary" href="#payroll-review" data-action-loading-ignore>Periksa dan setujui upah</a>
                        @else
                            <p>Sumber upah V Payslip belum tersedia untuk disetujui. Periksa keterangan pada bagian verifikasi upah, lalu muat ulang sumber setelah diperbaiki.</p>
                            <a class="btn btn-outline-primary" href="#payroll-review" data-action-loading-ignore>Lihat kendala sumber upah</a>
                        @endif
                    @elseif((string) $entry->created_by === (string) $actor->id)
                        <p>Anda adalah pembuat pengajuan ini. Persetujuan upah harus dilakukan oleh akun Supervisor Comben/Payroll yang berbeda.</p>
                        <p>Masuk dengan akun Supervisor Comben/Payroll, lalu buka batch dan pengajuan ini untuk memeriksa serta menyetujui upah.</p>
                    @else
                        <p>Pengajuan menunggu persetujuan Supervisor Comben/Payroll. Akun Anda dapat memantau pengajuan, tetapi tidak memiliki akses persetujuan upah.</p>
                    @endif
                @elseif($entry->status === 'hi_review')
                    @if($canHi)
                        <p>Isi sisa cuti secara manual, tampilkan pratinjau, lalu periksa dan setujui hasil perhitungan untuk membuka hasil kepada staf HI.</p>
                        <a class="btn btn-primary" href="#hi-approval" data-action-loading-ignore>Isi cuti dan periksa hasil</a>
                    @elseif((string) $entry->created_by === (string) $actor->id)
                        <p>Anda adalah pembuat pengajuan ini. Persetujuan akhir harus dilakukan oleh akun Supervisor HI yang berbeda dari pembuat dan penyetuju Payroll.</p>
                        <p>Masuk dengan akun Supervisor HI untuk mengisi sisa cuti dan menyetujui hasil perhitungan.</p>
                    @elseif((string) $entry->payroll_approved_by === (string) $actor->id)
                        <p>Anda sudah menyetujui tahap Payroll. Persetujuan akhir harus dilakukan oleh akun Supervisor HI yang berbeda.</p>
                        <p>Supervisor HI perlu mengisi sisa cuti dan memeriksa hasil perhitungan sebelum menyetujui.</p>
                    @else
                        <p>Pengajuan menunggu persetujuan Supervisor HI. Akun Anda dapat memantau pengajuan, tetapi tidak memiliki akses persetujuan akhir.</p>
                    @endif
                @elseif($entry->status === 'approved')
                    <p>Pengajuan sudah disetujui final. Hasil tersedia di bawah dan staf HI pengaju dapat mencetak atau mengexport hasilnya.</p>
                    <a class="btn btn-primary" href="#final-result" data-action-loading-ignore>Lihat hasil final</a>
                @endif
            </div>
        </section>
        <section class="salary-card">
            <div class="salary-card-header">
                <div>
                    <h2>Dasar pengajuan</h2>
                    <p>Tanggal masuk HRIS: {{ $entry->entry_date->format('d M Y') }}. Untuk pasal 16, gunakan awal kontrak/periode yang belum dibayarkan kompensasinya.</p>
                </div>
            </div>
            <div class="salary-card-body">
                @if($canEdit)<form class="severance-form" data-severance-form action="{{ route('severance.update', $entry) }}" method="POST">@csrf<input type="hidden" name="revision" value="{{ $entry->revision }}">@else<div class="severance-form">@endif
                        <div><label for="article" class="form-label">Pasal yang dipilih staf HI</label><select id="article" name="article" class="form-select" required @disabled(!$canEdit)>
                                <option value="">Pilih pasal</option>@foreach(config('severance.articles') as $value => $label)<option value="{{ $value }}" @selected((string) $entry->article === (string) $value)>{{ $label }}</option>@endforeach
                            </select></div>
                        <div><label for="start-date" class="form-label">Awal perhitungan</label><input id="start-date" type="date" name="calculation_start" class="form-control" min="{{ $entry->entry_date->format('Y-m-d') }}" value="{{ $entry->calculation_start->format('Y-m-d') }}" required @readonly(!$canEdit)></div>
                        <div><label for="end-date" class="form-label">Tanggal akhir kerja (termasuk hari terakhir)</label><input id="end-date" type="date" name="termination_date" class="form-control" value="{{ $entry->termination_date->format('Y-m-d') }}" required @readonly(!$canEdit)></div>
                        <div><label for="fare" class="form-label">Ongkos pulang (Rp)</label><input id="fare" type="number" min="0" max="999999999" step="0.01" name="return_fare" class="form-control" value="{{ $entry->return_fare }}" required @readonly(!$canEdit)></div>
                        <div class="severance-wide"><label for="reason" class="form-label">Pelanggaran / alasan</label><textarea id="reason" name="reason" class="form-control" maxlength="2000" rows="3" required @readonly(!$canEdit)>{{ $entry->reason }}</textarea></div>
                        @if($canEdit)<div class="severance-wide"><button class="btn btn-primary" type="submit">Simpan draf</button>
                            <p data-form-error class="severance-error" role="alert"></p>
                        </div>@endif
                        @if($canEdit)</form>@else</div>@endif
    </div>
    </section>
    <section class="salary-card" id="payroll-review">
        <div class="salary-card-header">
            <div>
                <h2>Verifikasi upah Supervisor Payroll</h2>
                <p>V Payslip FULL terakhir dengan akhir periode pada/sebelum tanggal akhir kerja. Tunjangan jabatan menggunakan Tunjangan Pengawas saja.</p>
            </div>
        </div>
        <div class="salary-card-body">
            <h3 class="severance-employee-title">Detail karyawan</h3>
            <dl class="severance-employee">
                <div><dt>NIK</dt><dd>{{ $entry->employee_nik }}</dd></div>
                <div><dt>Nama</dt><dd>{{ $entry->employee_name }}</dd></div>
                <div><dt>Perusahaan / area kerja</dt><dd>{{ $employee->area_kerja ?? '—' }}</dd></div>
                <div><dt>Departemen</dt><dd>{{ $employee->departemen->departemen ?? '—' }}</dd></div>
                <div><dt>Divisi</dt><dd>{{ $employee->divisi->nama_divisi ?? '—' }}</dd></div>
                <div><dt>Posisi</dt><dd>{{ optional($employee)->posisi ?: '—' }}</dd></div>
                <div><dt>Jabatan</dt><dd>{{ optional($employee)->jabatan ?: '—' }}</dd></div>
                <div><dt>Status karyawan</dt><dd>{{ optional($employee)->status_karyawan ?: '—' }}</dd></div>
                <div><dt>Status kerja</dt><dd>{{ optional($employee)->status_resign ?: '—' }}</dd></div>
                <div><dt>Tanggal masuk</dt><dd>{{ $entry->entry_date->format('d M Y') }}</dd></div>
                <div><dt>Tanggal akhir kerja</dt><dd>{{ $entry->termination_date->format('d M Y') }}</dd></div>
                <div><dt>Masa kerja s.d. akhir kerja</dt><dd>{{ $tenure->y }} tahun {{ $tenure->m }} bulan {{ $tenure->d }} hari</dd></div>
            </dl>
            <p class="salary-note">Identitas dan tanggal mengikuti pengajuan. @if($employee) Unit, posisi, jabatan, dan status mengikuti data HRIS saat ini.@else Data organisasi karyawan belum tersedia di HRIS.@endif</p>
            @if($salary)<dl class="severance-money mt-4">
                <div>
                    <dt>Gaji pokok</dt>
                    <dd>Rp {{ number_format($salary['basic_salary'], 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>Tunjangan jabatan</dt>
                    <dd>Rp {{ number_format($salary['position_allowance'], 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>Tunjangan masa kerja</dt>
                    <dd>Rp {{ number_format($salary['tenure_allowance'], 2, ',', '.') }}</dd>
                </div>
                <div>
                    <dt>Total upah dasar</dt>
                    <dd>Rp {{ number_format($salary['basic_salary'] + $salary['position_allowance'] + $salary['tenure_allowance'], 2, ',', '.') }}</dd>
                </div>
            </dl>
            <p class="salary-note">Sumber: V Payslip FULL periode {{ $salary['period'] }}; akhir periode {{ $salary['period_end'] }}.</p>
            @elseif($sourceError)<p class="severance-error" role="alert">{{ $sourceError }}</p><a class="btn btn-light" href="{{ route('severance.show', $entry) }}">Muat ulang sumber upah</a>
            @else<p class="salary-note">Nominal terisi otomatis setelah Supervisor Payroll menyetujui data upah.</p>@endif
            @if($canPayroll && $salary)<form class="mt-4" data-severance-form data-confirm="Setujui upah FULL ini dan teruskan pengajuan ke Supervisor HI?" action="{{ route('severance.payroll', $entry) }}" method="POST">@csrf<input type="hidden" name="revision" value="{{ $entry->revision }}"><input type="hidden" name="source_hash" value="{{ $salary['hash'] }}"><label class="form-check"><input class="form-check-input" type="checkbox" name="reviewed" value="1" required><span>Saya telah memeriksa gaji pokok, Tunjangan Pengawas, dan tunjangan masa kerja pada slip FULL ini.</span></label><button class="btn btn-primary mt-3" type="submit">Setujui upah dan kirim ke Supervisor HI</button>
                <p data-form-error class="severance-error" role="alert"></p>
            </form>@endif
        </div>
    </section>
    @if($canHi)
    <section class="salary-card" id="hi-approval">
        <div class="salary-card-header">
            <div>
                <h2>Persetujuan akhir Supervisor HI</h2>
                <p>Isi sisa cuti, tampilkan pratinjau, lalu periksa pasal, masa kerja, upah, dan ongkos sebelum menyetujui.</p>
            </div>
        </div>
        <div class="salary-card-body">
            <form data-severance-form data-hi-form action="{{ route('severance.hi', $entry) }}" method="POST">@csrf<input type="hidden" name="revision" value="{{ $entry->revision }}"><input type="hidden" name="preview_hash" value="">
                <div class="severance-leave"><label for="leave-days" class="form-label">Sisa cuti yang disahkan (hari)</label><input id="leave-days" name="leave_days" type="number" class="form-control" min="0" max="366" step="0.01" required aria-describedby="leave-help"><small id="leave-help">Wajib diisi manual; isi 0 bila tidak ada. Penggantian cuti = gaji pokok ÷ 25 × sisa cuti.</small></div>
                <button class="btn btn-outline-primary mt-3" type="submit" name="intent" value="preview" formnovalidate>Tampilkan pratinjau</button>
                <div data-hi-preview class="severance-preview" hidden aria-live="polite">
                    <h3>Pratinjau untuk Supervisor HI</h3>
                    <p data-preview-tenure></p>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Komponen</th>
                                    <th>Perhitungan</th>
                                    <th>Nominal (Rp)</th>
                                </tr>
                            </thead>
                            <tbody data-preview-lines></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2"><span data-preview-total-label></span><small>Bruto sebelum pajak</small></th>
                                    <th data-preview-total></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="salary-note">Uang pisah mengikuti pengali master perusahaan. Masa kerja dihitung dari tanggal; penghargaan masa kerja menggunakan tabel PP 35/2021 yang lengkap. Kompensasi PKWT memakai bulan penuh seperti master; tinjau sisa harinya.</p>
                </div>
                <label class="form-check mt-4"><input type="checkbox" class="form-check-input" name="reviewed" value="1"><span>Saya telah memeriksa dasar pasal, periode yang belum dibayar, sisa cuti, komponen upah, ongkos pulang, dan seluruh rincian hitung, serta kesesuaiannya dengan ketentuan yang berlaku.</span></label>
                <button class="btn btn-primary mt-3" type="submit" name="intent" value="approve" data-hi-approve disabled>Setujui dan buka hasil untuk staf HI</button>
                <p data-form-error class="severance-error" role="alert"></p>
            </form>
        </div>
    </section>
    @endif
    @if($entry->status === 'approved')
    <section class="salary-card" id="final-result">
        <div class="salary-card-header">
            <div>
                <h2>Hasil perhitungan final</h2>
                <p>{{ $entry->calculation['years'] }} tahun {{ $entry->calculation['months'] }} bulan {{ $entry->calculation['days'] }} hari. Versi {{ $entry->calculation['rules_version'] }}.</p>
            </div>
        </div>
        <div class="salary-card-body">
            @include('admin.severance.result', ['result' => $entry->calculation])
            @if($canManage)<div class="salary-actions mt-4"><a class="btn btn-primary" href="{{ route('severance.print', ['history' => $entry->import_history_id, 'ids' => [$entry->id]]) }}">Cetak / simpan PDF</a>
                <form data-severance-form action="{{ route('severance.export', $entry->import_history_id) }}" method="POST">@csrf<input type="hidden" name="ids[]" value="{{ $entry->id }}"><button class="btn btn-outline-primary" type="submit">Export Excel</button>
                    <p data-form-error class="severance-error" role="alert"></p>
                </form>
            </div>@endif
        </div>
    </section>
    @else<div class="alert alert-info">Hasil final, cetak, dan export tersedia setelah Supervisor HI menyetujui.</div>@endif
    @if($canPayroll || $canHi)
    <details class="salary-card">
        <summary class="salary-card-header">Kembalikan ke staf HI untuk revisi</summary>
        <div class="salary-card-body">
            <form data-severance-form data-confirm="Kembalikan pengajuan? Setelah staf memperbaiki, persetujuan Payroll dan HI harus diulang." action="{{ route('severance.reject', $entry) }}" method="POST">@csrf<input type="hidden" name="revision" value="{{ $entry->revision }}"><label for="return-note" class="form-label">Alasan pengembalian</label><textarea id="return-note" class="form-control" name="note" maxlength="500" rows="3" required></textarea><button class="btn btn-outline-danger mt-3" type="submit">Kembalikan untuk revisi</button>
                <p data-form-error class="severance-error" role="alert"></p>
            </form>
        </div>
    </details>
    @endif
    @if($entry->decisions)<section class="salary-card">
        <div class="salary-card-header">
            <h2>Riwayat tindakan</h2>
        </div>
        <div class="salary-card-body">
            <ol class="severance-history">@foreach(array_reverse($entry->decisions) as $decision)<li><strong>{{ ['edited' => 'Draf diperbaiki', 'payroll_approved' => 'Upah disetujui Payroll', 'hi_approved' => 'Disetujui final oleh HI', 'returned' => 'Dikembalikan untuk revisi'][$decision['action']] ?? $decision['action'] }}</strong><small>{{ $decision['actor'] }} · {{ \Carbon\Carbon::parse($decision['at'])->timezone('Asia/Makassar')->format('d M Y H:i') }} WITA</small>@if($decision['note'])<p>{{ $decision['note'] }}</p>@endif</li>@endforeach</ol>
        </div>
    </section>@endif
</div>
</div>
@endsection
@push('scripts')<script src="{{ versioned_asset('assets/js/severance.js') }}"></script>@endpush
