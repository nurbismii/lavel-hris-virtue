<?php

namespace App\Http\Requests\Presensi;

use Illuminate\Foundation\Http\FormRequest;

class SaveAttendanceLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasMenuAccess('setting_lokasi_presensi');
    }

    public function rules(): array
    {
        return [
            'nama_lokasi' => 'required|string|max:150',
            'lat' => 'required|numeric|between:-90,90',
            'long' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:1|max:10000',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_lokasi.required' => 'Nama lokasi presensi wajib diisi.',
            'nama_lokasi.max' => 'Nama lokasi presensi maksimal 150 karakter.',
            'lat.required' => 'Latitude wajib diisi.',
            'lat.numeric' => 'Latitude harus berupa angka.',
            'lat.between' => 'Latitude harus berada di antara -90 sampai 90.',
            'long.required' => 'Longitude wajib diisi.',
            'long.numeric' => 'Longitude harus berupa angka.',
            'long.between' => 'Longitude harus berada di antara -180 sampai 180.',
            'radius.required' => 'Radius wajib diisi.',
            'radius.numeric' => 'Radius harus berupa angka.',
            'radius.min' => 'Radius minimal 1 meter.',
            'radius.max' => 'Radius maksimal 10.000 meter.',
        ];
    }
}
