<?php

namespace App\Http\Requests\KaryawanRequest;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKaryawanRequest extends FormRequest
{
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
        return [
            'nama_karyawan' => 'required|string|max:255',
            'posisi' => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|in:L,P',
            'posisi' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'status_karyawan' => 'nullable|in:PKWT 合同工,PKWTT 固定工',
            'area_kerja' => 'nullable|string|in:VDNI,VDNIP,OSS,PMS-VDNI,PMS-OSS',
            'departemen_id' => 'nullable|exists:departemens,id',
            'divisi_id' => 'nullable|exists:divisis,id',
            'status_resign' => 'nullable|in:AKTIF,RESIGN SESUAI PROSEDUR,RESIGN TIDAK SESUAI PROSEDUR,PHK,PHK MENINGGAL DUNIA,PHK PENSIUN,PUTUS KONTRAK,PHK PIDANA',
            'tgl_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'status_perkawinan' => 'nullable|in:Kawin,Belum Kawin,Cerai',
            'no_telp' => 'nullable|string|max:13',
            'provinsi_id' => 'nullable|exists:master_provinsi,id',
            'kabupaten_id' => 'nullable|exists:master_kabupaten,id',
            'kecamatan_id' => 'nullable|exists:master_kecamatan,id',
            'kelurahan_id' => 'nullable|exists:master_kelurahan,id',
            'alamat_ktp' => 'nullable|string|max:500',
            'alamat_domisili' => 'nullable|string|max:500',
            'npwp' => 'nullable|string|max:50',
            'bpjs_kesehatan' => 'nullable|string|max:50',
            'bpjs_tk' => 'nullable|string|max:50',
        ];
    }
}
