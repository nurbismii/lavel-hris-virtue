<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class WilayahController extends Controller
{
    public function provinsi()
    {
        $wilayahService = app()->make(\App\Services\Wilayah\WilayahService::class);
        $provinces = $wilayahService->getProvinces();

        return response()->json($provinces);
    }
    
    public function kabupaten($provinceId)
    {
        $wilayahService = app()->make(\App\Services\Wilayah\WilayahService::class);
        $kabupatens = $wilayahService->getKabupatensByProvince($provinceId);

        return response()->json($kabupatens);
    }

    public function kecamatan($kabupatenId)
    {
        $wilayahService = app()->make(\App\Services\Wilayah\WilayahService::class);
        $kecamatans = $wilayahService->getKecamatansByKabupaten($kabupatenId);

        return response()->json($kecamatans);
    }

    public function kelurahan($kecamatanId)
    {
        $wilayahService = app()->make(\App\Services\Wilayah\WilayahService::class);
        $kelurahans = $wilayahService->getKelurahansByKecamatan($kecamatanId);

        return response()->json($kelurahans);
    }
    
}