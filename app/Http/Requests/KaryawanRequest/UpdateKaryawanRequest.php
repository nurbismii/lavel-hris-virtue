<?php

namespace App\Http\Requests\KaryawanRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKaryawanRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        if (strtoupper(trim((string) $this->input('status_resign'))) === 'AKTIF') {
            $this->merge([
                'tgl_resign' => null,
                'kategori_keluar' => '-',
            ]);
        }

        if (strtolower(trim((string) $this->input('status_perkawinan'))) === 'belum kawin') {
            $this->merge([
                'tanggal_menikah' => null,
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'nama_karyawan' => 'required|string|max:255',
            'nama_ibu_kandung' => 'nullable|string|max:225',
            'nama_bapak' => 'nullable|string|max:225',
            'posisi' => 'nullable|string|max:128',
            'jenis_kelamin' => 'nullable|in:L,P',
            'jabatan' => 'nullable|string|max:123',
            'status_karyawan' => 'nullable|string|max:255',
            'kode_area_kerja' => 'nullable|string|max:225',
            'area_kerja' => 'nullable|string|in:VDNI,VDNIP,OSS,PMS-VDNI,PMS-OSS',
            'departemen_id' => 'nullable|exists:departemens,id',
            'divisi_id' => 'nullable|exists:divisis,id',
            'work_pattern_id' => 'nullable|exists:work_patterns,id',
            'work_pattern_start_date' => 'nullable|date',
            'entry_date' => 'nullable|date',
            'status_resign' => 'nullable|in:AKTIF,RESIGN SESUAI PROSEDUR,RESIGN TIDAK SESUAI PROSEDUR,RESIGN TIDAK SESUAI PROSEDUR-PENGAJUAN,RESIGN TIDAK SESUAI PROSEDUR-PAYROLL,RESIGN TIDAK SESUAI PROSEDUR-KABUR,PHK,PHK MENINGGAL DUNIA,PHK PENSIUN,PHK PENSIUN DINI,PUTUS KONTRAK,PHK PIDANA,PB RESIGN',
            'tgl_resign' => 'nullable|date',
            'alasan_resign' => 'nullable|string|max:1000',
            'kategori_keluar' => 'nullable|string|max:128',
            'tgl_lahir' => 'nullable|date',
            'tanggal_menikah' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'status_perkawinan' => 'nullable|in:Kawin,Belum Kawin,Cerai',
            'no_ktp' => 'nullable|string|max:20',
            'no_kk' => 'nullable|string|max:20',
            'no_sk_pkwtt' => 'nullable|string|max:255',
            'no_telp' => 'nullable|string|max:128',
            'provinsi_id' => 'nullable|exists:master_provinsi,id',
            'kabupaten_id' => 'nullable|exists:master_kabupaten,id',
            'kecamatan_id' => 'nullable|exists:master_kecamatan,id',
            'kelurahan_id' => 'nullable|exists:master_kelurahan,id',
            'rt' => 'nullable|string|max:4',
            'rw' => 'nullable|string|max:4',
            'kode_pos' => 'nullable|string|max:64',
            'alamat_ktp' => 'nullable|string|max:500',
            'alamat_domisili' => 'nullable|string|max:500',
            'golongan_darah' => 'nullable|string|max:8',
            'npwp' => 'nullable|string|max:64',
            'status_pajak' => 'nullable|string|max:225',
            'bpjs_kesehatan' => 'nullable|string|max:64',
            'bpjs_tk' => 'nullable|string|max:64',
            'vaksin' => 'nullable|string|max:225',
            'jam_kerja' => 'nullable|string|max:128',
            'skill' => 'nullable|string|max:128',
            'tinggi' => 'nullable|string|max:4',
            'berat' => 'nullable|string|max:4',
            'hobi' => 'nullable|string|max:225',
            'no_jamsostek' => 'nullable|string|max:225',
            'no_asuransi' => 'nullable|string|max:225',
            'no_kartu_asuransi' => 'nullable|string|max:225',
            'nama_instansi_pendidikan' => 'nullable|string|max:225',
            'pendidikan_terakhir' => 'nullable|string|max:225',
            'jurusan' => 'nullable|string|max:225',
            'tanggal_kelulusan' => 'nullable|date',
            'photo_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'ktp_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'kk_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'sim_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'sio_file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'face_reference' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ];

        $user = $this->user();

        if ($user && $user->canAccessAllEmployees()) {
            $rules['nama_bank'] = 'nullable|string|max:128';
            $rules['no_rekening'] = 'nullable|string|max:128';
        }

        return $rules;
    }
}
