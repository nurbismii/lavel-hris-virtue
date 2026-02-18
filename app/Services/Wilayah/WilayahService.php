<?php

namespace App\Services\Wilayah;

use App\Models\Kabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Provinsi;

class WilayahService
{
    public function getProvinces()
    {
        // Logic to retrieve provinces
        $provinces = Provinsi::all();
        return $provinces;
    }

    public function getKabupatensByProvince($provinceId)
    {
        // Logic to retrieve kabupatens by province
        $kabupatens = Kabupaten::where('id_provinsi', $provinceId)->get();
        return $kabupatens;
    }

    public function getKecamatansByKabupaten($kabupatenId)
    {
        // Logic to retrieve kecamatans by kabupaten
        // Assuming there is a Kecamatan model
        $kecamatans = Kecamatan::where('id_kabupaten', $kabupatenId)->get();
        return $kecamatans;
    }

    public function getKelurahansByKecamatan($kecamatanId)
    {
        // Logic to retrieve kelurahans by kecamatan
        // Assuming there is a Kelurahan model
        $kelurahans = Kelurahan::where('id_kecamatan', $kecamatanId)->get();
        return $kelurahans;
    }
}
