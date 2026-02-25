<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Provinsi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WilayahController extends Controller
{
    public function index(Request $request)
    {
        $provinsi_all = Provinsi::all();
        $area_kerja = $request->area_kerja ?? ['VDNI', 'VDNIP'];

        // ==============================
        // Preload nama wilayah (1 query per tabel)
        // ==============================
        $nama_provinsi  = Provinsi::pluck('provinsi', 'id');
        $nama_kabupaten = Kabupaten::pluck('kabupaten', 'id');
        $nama_kecamatan = Kecamatan::pluck('kecamatan', 'id');
        $nama_kelurahan = Kelurahan::pluck('kelurahan', 'id');

        // ==============================
        // Ambil data agregasi langsung dari DB
        // ==============================
        $response = DB::table('employees')
            ->selectRaw("
            provinsi_id,
            kabupaten_id,
            kecamatan_id,
            kelurahan_id,
            LOWER(jenis_kelamin) as gender,
            COUNT(*) as jumlah
        ")
            ->where('status_resign', 'Aktif')
            ->whereIn('area_kerja', $area_kerja)
            ->groupBy(
                'provinsi_id',
                'kabupaten_id',
                'kecamatan_id',
                'kelurahan_id',
                'gender'
            )
            ->get();

        $groupedData = [
            'Sulawesi' => [],
            'Sulawesi Tenggara' => [],
            'Non Sulawesi' => [],
        ];

        $sulawesi_ids = ['71', '72', '73', '75', '76'];
        $sultra_ids   = ['74'];

        foreach ($response as $row) {

            $prov = $row->provinsi_id;
            $kab  = $row->kabupaten_id;
            $kec  = $row->kecamatan_id;
            $kel  = $row->kelurahan_id;

            $genderMap = [
                'l' => 'laki-laki',
                'laki-laki' => 'laki-laki',
                'p' => 'perempuan',
                'perempuan' => 'perempuan',
            ];

            $gender = $genderMap[strtolower($row->gender)] ?? null;
            if (!$gender) continue;

            $region = in_array($prov, $sultra_ids)
                ? 'Sulawesi Tenggara'
                : (in_array($prov, $sulawesi_ids) ? 'Sulawesi' : 'Non Sulawesi');

            // ==============================
            // Inisialisasi Struktur
            // ==============================
            $groupedData[$region][$prov] ??= [
                'nama' => $nama_provinsi[$prov] ?? 'BELUM DIKETAHUI',
                'laki-laki' => 0,
                'perempuan' => 0,
                'jumlah' => 0,
                'kabupaten' => []
            ];

            $groupedData[$region][$prov]['kabupaten'][$kab] ??= [
                'nama' => $nama_kabupaten[$kab] ?? 'BELUM DIKETAHUI',
                'laki-laki' => 0,
                'perempuan' => 0,
                'jumlah' => 0,
                'kecamatan' => []
            ];

            $groupedData[$region][$prov]['kabupaten'][$kab]['kecamatan'][$kec] ??= [
                'nama' => $nama_kecamatan[$kec] ?? 'BELUM DIKETAHUI',
                'laki-laki' => 0,
                'perempuan' => 0,
                'jumlah' => 0,
                'kelurahan' => []
            ];

            $groupedData[$region][$prov]['kabupaten'][$kab]['kecamatan'][$kec]['kelurahan'][$kel] ??= [
                'nama' => $nama_kelurahan[$kel] ?? 'BELUM DIKETAHUI',
                'laki-laki' => 0,
                'perempuan' => 0,
                'jumlah' => 0
            ];

            $jumlah = $row->jumlah;

            $groupedData[$region][$prov]['laki-laki'] += $gender === 'laki-laki' ? $jumlah : 0;
            $groupedData[$region][$prov]['perempuan'] += $gender === 'perempuan' ? $jumlah : 0;
            $groupedData[$region][$prov]['jumlah']    += $jumlah;

            $groupedData[$region][$prov]['kabupaten'][$kab][$gender] += $jumlah;
            $groupedData[$region][$prov]['kabupaten'][$kab]['jumlah'] += $jumlah;

            $groupedData[$region][$prov]['kabupaten'][$kab]['kecamatan'][$kec][$gender] += $jumlah;
            $groupedData[$region][$prov]['kabupaten'][$kab]['kecamatan'][$kec]['jumlah'] += $jumlah;

            $groupedData[$region][$prov]['kabupaten'][$kab]['kecamatan'][$kec]['kelurahan'][$kel][$gender] += $jumlah;
            $groupedData[$region][$prov]['kabupaten'][$kab]['kecamatan'][$kec]['kelurahan'][$kel]['jumlah'] += $jumlah;
        }

        $arr_region_data = collect($groupedData)->map(function ($provinsis, $regionName) {
            $total = collect($provinsis)->sum('jumlah');

            return [
                'region_nama' => $regionName,
                'region_jumlah' => $total
            ];
        })->values()->toArray();

        return view('admin.wilayah.index', [
            'arr_region_data' => $arr_region_data,
            'array' => $groupedData,
            'response' => $groupedData,
            'area_kerja' => $area_kerja,
            'provinsi_all' => $provinsi_all,
        ]);
    }

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
