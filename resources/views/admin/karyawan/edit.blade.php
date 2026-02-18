@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-inner">

        <div class="page-header">
            <h3 class="fw-bold mb-3">Edit Karyawan</h3>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('karyawan.update', $employee->nik) }}" method="POST">
                    @csrf
                    @method('PUT')

                    {{-- DATA UTAMA --}}
                    <h5 class="fw-bold mb-3">Data Utama</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-control" value="{{ $employee->nik }}" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nama Karyawan</label>
                            <input type="text" class="form-control" name="nama_karyawan" value="{{ $employee->nama_karyawan }}" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-select form-control">
                                <option value="">-- Pilih --</option>
                                <option value="L" {{ $employee->jenis_kelamin == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="P" {{ $employee->jenis_kelamin == 'P' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                        </div>
                    </div>

                    {{-- PEKERJAAN --}}
                    <h5 class="fw-bold mt-4 mb-3">Pekerjaan</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Posisi</label>
                            <input type="text" class="form-control" name="posisi" value="{{ $employee->posisi }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jabatan</label>
                            <input type="text" class="form-control" name="jabatan" value="{{ $employee->jabatan }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Kontrak</label>
                            <select name="status_karyawan" class="form-select form-control">
                                <option value="">-- Pilih --</option>
                                <option value="PKWT 合同工" {{ $employee->status_karyawan == 'PKWT 合同工' ? 'selected' : '' }}>PKWT 合同工</option>
                                <option value="PKWTT 固定工" {{ $employee->status_karyawan == 'PKWTT 固定工' ? 'selected' : '' }}>PKWTT 固定工</option>
                            </select>
                        </div>

                        {{-- PERUSAHAAN --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Perusahaan</label>
                            <select name="area_kerja" id="perusahaan_id" class="form-select form-control">
                                <option value="">-- Pilih Perusahaan --</option>
                                @foreach ($areas as $area)
                                <option value="{{ $area->kode_perusahaan }}"
                                    {{ old('perusahaan_id', $employee->area_kerja) == $area->kode_perusahaan ? 'selected' : '' }}>
                                    {{ $area->kode_perusahaan }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- DEPARTEMEN --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Departemen</label>
                            <select name="departemen_id" id="departemen_id" class="form-select form-control">
                                <option value="">-- Pilih Departemen --</option>
                                @foreach ($departemens as $departemen)
                                <option value="{{ $departemen->id }}"
                                    {{ old('departemen_id', $employee->departemen_id) == $departemen->id ? 'selected' : '' }}>
                                    {{ $departemen->nama_departemen }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- DIVISI --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Divisi</label>
                            <select name="divisi_id" id="divisi_id" class="form-select form-control">
                                <option value="">-- Pilih Divisi --</option>
                                @foreach ($divisis as $divisi)
                                <option value="{{ $divisi->id }}"
                                    {{ old('divisi_id', $employee->divisi_id) == $divisi->id ? 'selected' : '' }}>
                                    {{ $divisi->nama_divisi }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- STATUS KARYAWAN --}}
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Karyawan</label>
                            <select name="status_resign" id="status_resign" class="form-select form-control">
                                <option value="">-- Pilih Status --</option>
                                <option value="">Semua Status</option>
                                <option value="AKTIF" {{ old('status_resign', $employee->status_resign) == 'AKTIF' ? 'selected' : '' }}>Aktif</option>
                                <option value="RESIGN SESUAI PROSEDUR" {{ old('status_resign', $employee->status_resign) == 'RESIGN SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Sesuai Prosedur</option>
                                <option value="RESIGN TIDAK SESUAI PROSEDUR" {{ old('status_resign', $employee->status_resign) == 'RESIGN TIDAK SESUAI PROSEDUR' ? 'selected' : '' }}>Resign Tidak Sesuai Prosedur</option>
                                <option value="PUTUS KONTRAK" {{ old('status_resign', $employee->status_resign) == 'PUTUS KONTRAK' ? 'selected' : '' }}>Putus Kontrak</option>
                                <option value="PHK" {{ old('status_resign', $employee->status_resign) == 'PHK' ? 'selected' : '' }}>PHK</option>
                                <option value="PHK PENSIUN" {{ old('status_resign', $employee->status_resign) == 'PHK PENSIUN' ? 'selected' : '' }}>PHK Pensiun</option>
                                <option value="PHK PIDANA" {{ old('status_resign', $employee->status_resign) == 'PHK PIDANA' ? 'selected' : '' }}>PHK Pidana</option>
                                <option value="PHK MENINGGAL DUNIA" {{ old('status_resign', $employee->status_resign) == 'PHK MENINGGAL DUNIA' ? 'selected' : '' }}>PHK MENINGGAL DUNIA</option>
                            </select>
                        </div>

                    </div>

                    {{-- DATA PRIBADI --}}
                    <h5 class="fw-bold mt-4 mb-3">Data Pribadi</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" name="tgl_lahir" value="{{ $employee->tgl_lahir->format('Y-m-d') }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Agama</label>
                            <select name="agama" class="form-select form-control">
                                <option value="">-- Pilih Agama --</option>
                                <option value="ISLAM 伊斯兰教" {{ $employee->agama == 'ISLAM 伊斯兰教' ? 'selected' : '' }}>ISLAM 伊斯兰教</option>
                                <option value="KRISTEN PROTESTAN 基督教新教" {{ $employee->agama == 'KRISTEN PROTESTAN 基督教新教' ? 'selected' : '' }}>KRISTEN PROTESTAN 基督教新教</option>
                                <option value="KRISTEN KATHOLIK 天主教徒" {{ $employee->agama == 'KRISTEN KATHOLIK 天主教徒' ? 'selected' : '' }}>KRISTEN KATHOLIK 天主教徒</option>
                                <option value="HINDU 印度教" {{ $employee->agama == 'HINDU 印度教' ? 'selected' : '' }}>HINDU 印度教</option>
                                <option value="BUDHA 佛教" {{ $employee->agama == 'BUDHA 佛教' ? 'selected' : '' }}>BUDHA 佛教</option>
                                <option value="KHONGHUCU 儒教" {{ $employee->agama == 'KHONGHUCU 儒教' ? 'selected' : '' }}>KHONGHUCU 儒教</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Perkawinan</label>
                            <select name="status_perkawinan" id="status_perkawinan" class="form-select form-control">
                                <option value="">Pilih Status</option>
                                <option value="Belum Kawin" {{ $employee->status_perkawinan == 'Belum Kawin' ? 'selected' : '' }}>Belum Kawin</option>
                                <option value="Kawin" {{ $employee->status_perkawinan == 'Kawin' ? 'selected' : '' }}>Kawin</option>
                                <option value="Cerai" {{ $employee->status_perkawinan == 'Cerai' ? 'selected' : '' }}>Cerai</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">No. Telp</label>
                            <input type="text" class="form-control" name="no_telp" value="{{ $employee->no_telp }}">
                        </div>
                    </div>

                    {{-- ALAMAT --}}
                    <h5 class="fw-bold mt-4 mb-3">Alamat</h5>
                    <div class="row">

                        {{-- PROVINSI --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Provinsi</label>
                            <select name="provinsi_id" id="provinsi_id" class="form-select form-control">
                                <option value="">-- Pilih Provinsi --</option>
                            </select>
                        </div>

                        {{-- KABUPATEN --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kabupaten</label>
                            <select name="kabupaten_id" id="kabupaten_id" class="form-select form-control">
                                <option value="">-- Pilih Kabupaten --</option>
                            </select>
                        </div>

                        {{-- KECAMATAN --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kecamatan</label>
                            <select name="kecamatan_id" id="kecamatan_id" class="form-select form-control">
                                <option value="">-- Pilih Kecamatan --</option>
                            </select>
                        </div>

                        {{-- KELURAHAN --}}
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kelurahan</label>
                            <select name="kelurahan_id" id="kelurahan_id" class="form-select form-control">
                                <option value="">-- Pilih Kelurahan --</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat KTP</label>
                            <textarea class="form-control" name="alamat_ktp" rows="3">{{ $employee->alamat_ktp }}</textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea class="form-control" name="alamat_domisili" rows="3">{{ $employee->alamat_domisili }}</textarea>
                        </div>
                    </div>

                    {{-- ADMINISTRASI --}}
                    <h5 class="fw-bold mt-4 mb-3">Administrasi</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">NPWP</label>
                            <input type="text" class="form-control" name="npwp" value="{{ $employee->npwp }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">BPJS Kesehatan</label>
                            <input type="text" class="form-control" name="bpjs_kesehatan"  value="{{ $employee->bpjs_kesehatan }}">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">BPJS Ketenagakerjaan</label>
                            <input type="text" class="form-control" name="bpjs_tk" value="{{ $employee->bpjs_tk }}">
                        </div>
                    </div>

                    {{-- BUTTON --}}
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="{{ route('karyawan.index') }}" class="btn btn-secondary">
                            Kembali
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const oldPerusahaan = "{{ old('perusahaan_id', $employee->area_kerja) }}";
    const oldDepartemen = "{{ old('departemen_id', $employee->departemen_id) }}";
    const oldDivisi = "{{ old('divisi_id', $employee->divisi_id) }}";

    $(document).ready(function() {

        // ===============================
        // PERUSAHAAN → DEPARTEMEN
        // ===============================
        $('#perusahaan_id').on('change', function() {
            let perusahaan = $(this).val();

            // kalau user pilih ulang → reset
            $('#departemen_id').html('<option value="">Loading...</option>');
            $('#divisi_id').html('<option value="">-- Pilih Divisi --</option>');

            if (!perusahaan) {
                $('#departemen_id').html('<option value="">-- Pilih Departemen --</option>');
                return;
            }

            $.get('/admin/ajax/departemen-by-area', {
                area: perusahaan
            }, function(data) {
                let options = '<option value="">-- Pilih Departemen --</option>';

                data.forEach(item => {
                    options += `<option value="${item.id}">${item.departemen}</option>`;
                });

                $('#departemen_id').html(options);

                /**
                 * AUTO SELECT hanya jika:
                 * - ini pertama kali load (edit mode)
                 * - perusahaan tidak berubah
                 */
                if (oldDepartemen && perusahaan === oldPerusahaan) {
                    $('#departemen_id').val(oldDepartemen).trigger('change');
                }
            });
        });

        // ===============================
        // DEPARTEMEN → DIVISI
        // ===============================
        $('#departemen_id').on('change', function() {
            let departemen = $(this).val();

            $('#divisi_id').html('<option value="">Loading...</option>');

            if (!departemen) {
                $('#divisi_id').html('<option value="">-- Pilih Divisi --</option>');
                return;
            }

            $.get("/admin/ajax/divisi-by-departemen", {
                departemen: departemen
            }, function(data) {
                let options = '<option value="">-- Pilih Divisi --</option>';

                data.forEach(item => {
                    options += `<option value="${item.id}">${item.nama_divisi}</option>`;
                });

                $('#divisi_id').html(options);

                /**
                 * AUTO SELECT hanya jika:
                 * - edit mode
                 * - departemen tidak berubah
                 */
                if (oldDivisi && departemen == oldDepartemen) {
                    $('#divisi_id').val(oldDivisi);
                }
            });
        });

        // ===============================
        // AUTO LOAD SAAT EDIT
        // ===============================
        if (oldPerusahaan) {
            $('#perusahaan_id').trigger('change');
        }

    });
</script>

<script>
    const oldProvinsi = "{{ old('provinsi_id', $employee->provinsi_id) }}";
    const oldKabupaten = "{{ old('kabupaten_id', $employee->kabupaten_id) }}";
    const oldKecamatan = "{{ old('kecamatan_id', $employee->kecamatan_id) }}";
    const oldKelurahan = "{{ old('kelurahan_id', $employee->kelurahan_id) }}";

    $(document).ready(function() {

        // ===============================
        // LOAD PROVINSI
        // ===============================
        $.get("{{ route('wilayah.provinces') }}", function(data) {
            let opt = '<option value="">-- Pilih Provinsi --</option>';
            data.forEach(item => {
                opt += `<option value="${item.id}">${item.provinsi}</option>`;
            });
            $('#provinsi_id').html(opt);

            if (oldProvinsi) {
                $('#provinsi_id').val(oldProvinsi).trigger('change');
            }
        });

        // ===============================
        // PROVINSI → KABUPATEN
        // ===============================
        $('#provinsi_id').on('change', function() {
            let provinsi = $(this).val();

            $('#kabupaten_id').html('<option>Loading...</option>');
            $('#kecamatan_id').html('<option value="">-- Pilih Kecamatan --</option>');
            $('#kelurahan_id').html('<option value="">-- Pilih Kelurahan --</option>');

            if (!provinsi) return;

            $.get(`/wilayah/kabupatens/${provinsi}`, function(data) {
                let opt = '<option value="">-- Pilih Kabupaten --</option>';
                data.forEach(item => {
                    opt += `<option value="${item.id}">${item.kabupaten}</option>`;
                });
                $('#kabupaten_id').html(opt);

                if (oldKabupaten && provinsi == oldProvinsi) {
                    $('#kabupaten_id').val(oldKabupaten).trigger('change');
                }
            });
        });

        // ===============================
        // KABUPATEN → KECAMATAN
        // ===============================
        $('#kabupaten_id').on('change', function() {
            let kabupaten = $(this).val();

            $('#kecamatan_id').html('<option>Loading...</option>');
            $('#kelurahan_id').html('<option value="">-- Pilih Kelurahan --</option>');

            if (!kabupaten) return;

            $.get(`/wilayah/kecamatans/${kabupaten}`, function(data) {
                let opt = '<option value="">-- Pilih Kecamatan --</option>';
                data.forEach(item => {
                    opt += `<option value="${item.id}">${item.kecamatan}</option>`;
                });
                $('#kecamatan_id').html(opt);

                if (oldKecamatan && kabupaten == oldKabupaten) {
                    $('#kecamatan_id').val(oldKecamatan).trigger('change');
                }
            });
        });

        // ===============================
        // KECAMATAN → KELURAHAN
        // ===============================
        $('#kecamatan_id').on('change', function() {
            let kecamatan = $(this).val();

            $('#kelurahan_id').html('<option>Loading...</option>');

            if (!kecamatan) return;

            $.get(`/wilayah/kelurahans/${kecamatan}`, function(data) {
                let opt = '<option value="">-- Pilih Kelurahan --</option>';
                data.forEach(item => {
                    opt += `<option value="${item.id}">${item.kelurahan}</option>`;
                });
                $('#kelurahan_id').html(opt);

                if (oldKelurahan && kecamatan == oldKecamatan) {
                    $('#kelurahan_id').val(oldKelurahan);
                }
            });
        });

    });
</script>
@endpush

@endsection