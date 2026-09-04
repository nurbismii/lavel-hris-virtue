<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DistribusiWilayahExport;
use App\Http\Controllers\Controller;
use App\Models\Kabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Perusahaan;
use App\Models\Provinsi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WilayahController extends Controller
{
    private const SULAWESI_PROVINCE_IDS = ['71', '72', '73', '75', '76'];
    private const SULAWESI_TENGGARA_ID = '74';
    private const FOCUS_KECAMATANS = ['Bondoala', 'Morosi', 'Kapoiala'];
    private const SULAWESI_GORONTALO_MAP = [
        '71' => 'Sulawesi Utara',
        '72' => 'Sulawesi Tengah',
        '73' => 'Sulawesi Selatan',
        '74' => 'Sulawesi Tenggara',
        '75' => 'Gorontalo',
        '76' => 'Sulawesi Barat',
    ];

    public function index(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $baseQuery = $this->filteredEmployeeQuery($filters);

        $summary = $this->buildSummary((clone $baseQuery));
        $regionBreakdown = $this->buildRegionBreakdown((clone $baseQuery));
        $topSultraKabupaten = $this->buildTopSultraKabupaten((clone $baseQuery));
        $focusKecamatan = $this->buildFocusKecamatan((clone $baseQuery));
        $sulawesiGorontaloBreakdown = $this->buildSulawesiGorontaloBreakdown((clone $baseQuery));
        $kabupatenSummary = $this->buildKabupatenSummary((clone $baseQuery), $filters);
        $kecamatanSummary = $this->buildKecamatanSummary((clone $baseQuery), $filters);
        $distributionRows = $this->buildDistributionRows((clone $baseQuery));

        $provinsiOptions = Provinsi::orderBy('provinsi')->get(['id', 'provinsi']);
        $kabupatenOptions = $filters['provinsi_id']
            ? Kabupaten::where('id_provinsi', $filters['provinsi_id'])->orderBy('kabupaten')->get(['id', 'kabupaten'])
            : collect();
        $kecamatanOptions = $filters['kabupaten_id']
            ? Kecamatan::where('id_kabupaten', $filters['kabupaten_id'])->orderBy('kecamatan')->get(['id', 'kecamatan'])
            : collect();
        $kelurahanOptions = $filters['kecamatan_id']
            ? Kelurahan::where('id_kecamatan', $filters['kecamatan_id'])->orderBy('kelurahan')->get(['id', 'kelurahan'])
            : collect();

        $leadingSultraKabupaten = collect($topSultraKabupaten)->first();
        $sultraTotal = data_get(collect($regionBreakdown)->firstWhere('label', 'Sulawesi Tenggara'), 'total', 0);
        $insights = [
            'share_sultra' => $summary['total'] > 0
                ? round(($sultraTotal / $summary['total']) * 100, 1)
                : 0,
            'leading_sultra_kabupaten' => $leadingSultraKabupaten['label'] ?? 'Belum ada data',
            'leading_sultra_kabupaten_total' => $leadingSultraKabupaten['total'] ?? 0,
            'focus_kecamatan_total' => collect($focusKecamatan)->sum('total'),
            'sulawesi_gorontalo_total' => collect($sulawesiGorontaloBreakdown)->sum('total'),
        ];

        return view('admin.wilayah.index', [
            'filters' => $filters,
            'areaKerjaOptions' => Perusahaan::ORGANIZATION_COMPANY_CODES,
            'provinsiOptions' => $provinsiOptions,
            'kabupatenOptions' => $kabupatenOptions,
            'kecamatanOptions' => $kecamatanOptions,
            'kelurahanOptions' => $kelurahanOptions,
            'summary' => $summary,
            'regionBreakdown' => $regionBreakdown,
            'topSultraKabupaten' => $topSultraKabupaten,
            'focusKecamatan' => $focusKecamatan,
            'sulawesiGorontaloBreakdown' => $sulawesiGorontaloBreakdown,
            'kabupatenSummary' => $kabupatenSummary,
            'kecamatanSummary' => $kecamatanSummary,
            'distributionRows' => $distributionRows,
            'insights' => $insights,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $exportQuery = $this->buildExportQuery($filters)
            ->orderBy('provinsi')
            ->orderBy('kabupaten')
            ->orderBy('kecamatan')
            ->orderBy('kelurahan')
            ->orderBy('e.nama_karyawan');

        $filename = 'distribusi-wilayah-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($exportQuery) {
            $output = fopen('php://output', 'w');
            fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, [
                'NIK',
                'Nama Karyawan',
                'Area Kerja',
                'Jenis Kelamin',
                'Provinsi',
                'Kabupaten',
                'Kecamatan',
                'Kelurahan',
            ]);

            foreach ($exportQuery->cursor() as $row) {
                fputcsv($output, [
                    $row->nik,
                    $row->nama_karyawan,
                    $row->area_kerja,
                    $row->jenis_kelamin,
                    $row->provinsi,
                    $row->kabupaten,
                    $row->kecamatan,
                    $row->kelurahan,
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $query = $this->buildExportQuery($filters)
            ->orderBy('provinsi')
            ->orderBy('kabupaten')
            ->orderBy('kecamatan')
            ->orderBy('kelurahan')
            ->orderBy('e.nama_karyawan');

        return Excel::download(
            new DistribusiWilayahExport($query),
            'distribusi-wilayah-' . now()->format('Ymd_His') . '.xlsx'
        );
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

    private function resolveFilters(Request $request): array
    {
        $areaKerja = $request->input('area_kerja', Perusahaan::ORGANIZATION_COMPANY_CODES);

        if (!is_array($areaKerja)) {
            $areaKerja = [$areaKerja];
        }

        $areaKerja = collect($areaKerja)
            ->filter(fn($value) => in_array($value, Perusahaan::ORGANIZATION_COMPANY_CODES, true))
            ->values()
            ->all();

        return [
            'area_kerja' => $areaKerja,
            'provinsi_id' => $request->filled('provinsi_id') ? (string) $request->provinsi_id : null,
            'kabupaten_id' => $request->filled('kabupaten_id') ? (string) $request->kabupaten_id : null,
            'kecamatan_id' => $request->filled('kecamatan_id') ? (string) $request->kecamatan_id : null,
            'kelurahan_id' => $request->filled('kelurahan_id') ? (string) $request->kelurahan_id : null,
        ];
    }

    private function filteredEmployeeQuery(array $filters)
    {
        return DB::table('employees as e')
            ->leftJoin('master_provinsi as p', 'e.provinsi_id', '=', 'p.id')
            ->leftJoin('master_kabupaten as kb', 'e.kabupaten_id', '=', 'kb.id')
            ->leftJoin('master_kecamatan as kc', 'e.kecamatan_id', '=', 'kc.id')
            ->leftJoin('master_kelurahan as kl', 'e.kelurahan_id', '=', 'kl.id')
            ->whereRaw("UPPER(COALESCE(e.status_resign, '')) = 'AKTIF'")
            ->whereIn('e.area_kerja', $filters['area_kerja'])
            ->when($filters['provinsi_id'], function ($query, $provinsiId) {
                $query->where('e.provinsi_id', $provinsiId);
            })
            ->when($filters['kabupaten_id'], function ($query, $kabupatenId) {
                $query->where('e.kabupaten_id', $kabupatenId);
            })
            ->when($filters['kecamatan_id'], function ($query, $kecamatanId) {
                $query->where('e.kecamatan_id', $kecamatanId);
            })
            ->when($filters['kelurahan_id'], function ($query, $kelurahanId) {
                $query->where('e.kelurahan_id', $kelurahanId);
            });
    }

    private function buildExportQuery(array $filters)
    {
        return $this->filteredEmployeeQuery($filters)
            ->selectRaw("
                e.nik,
                e.nama_karyawan,
                e.area_kerja,
                COALESCE(e.jenis_kelamin, '-') as jenis_kelamin,
                COALESCE(p.provinsi, 'Belum diketahui') as provinsi,
                COALESCE(kb.kabupaten, 'Belum diketahui') as kabupaten,
                COALESCE(kc.kecamatan, 'Belum diketahui') as kecamatan,
                COALESCE(kl.kelurahan, 'Belum diketahui') as kelurahan
            ");
    }

    private function buildSummary($query): array
    {
        $row = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(COALESCE(e.jenis_kelamin, '')) IN ('l', 'laki-laki') THEN 1 ELSE 0 END) as laki_laki,
            SUM(CASE WHEN LOWER(COALESCE(e.jenis_kelamin, '')) IN ('p', 'perempuan') THEN 1 ELSE 0 END) as perempuan,
            SUM(CASE WHEN e.provinsi_id IS NOT NULL AND e.kabupaten_id IS NOT NULL AND e.kecamatan_id IS NOT NULL AND e.kelurahan_id IS NOT NULL THEN 1 ELSE 0 END) as wilayah_lengkap
        ")->first();

        $total = (int) ($row->total ?? 0);
        $wilayahLengkap = (int) ($row->wilayah_lengkap ?? 0);

        return [
            'total' => $total,
            'laki_laki' => (int) ($row->laki_laki ?? 0),
            'perempuan' => (int) ($row->perempuan ?? 0),
            'wilayah_lengkap' => $wilayahLengkap,
            'wilayah_belum_lengkap' => max(0, $total - $wilayahLengkap),
            'wilayah_lengkap_persen' => $total > 0 ? round(($wilayahLengkap / $total) * 100, 1) : 0,
        ];
    }

    private function buildRegionBreakdown($query): array
    {
        $provinceCounts = $query
            ->selectRaw('COALESCE(e.provinsi_id, "") as provinsi_id, COUNT(*) as total')
            ->groupBy('e.provinsi_id')
            ->get();

        $regions = [
            'Sulawesi' => 0,
            'Sulawesi Tenggara' => 0,
            'Non Sulawesi' => 0,
        ];

        foreach ($provinceCounts as $row) {
            $provinsiId = (string) $row->provinsi_id;
            $total = (int) $row->total;

            if ($provinsiId === self::SULAWESI_TENGGARA_ID) {
                $regions['Sulawesi Tenggara'] += $total;
                continue;
            }

            if (in_array($provinsiId, self::SULAWESI_PROVINCE_IDS, true)) {
                $regions['Sulawesi'] += $total;
                continue;
            }

            $regions['Non Sulawesi'] += $total;
        }

        return collect($regions)
            ->map(fn($total, $label) => [
                'label' => $label,
                'total' => (int) $total,
            ])
            ->values()
            ->all();
    }

    private function buildTopSultraKabupaten($query): array
    {
        return $query
            ->where('e.provinsi_id', self::SULAWESI_TENGGARA_ID)
            ->selectRaw("
                COALESCE(kb.kabupaten, 'Belum diketahui') as label,
                COUNT(*) as total
            ")
            ->groupBy('e.kabupaten_id', 'kb.kabupaten')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    private function buildFocusKecamatan($query): array
    {
        $focusNames = collect(self::FOCUS_KECAMATANS)->map(fn($item) => strtolower($item))->all();

        $totals = $query
            ->selectRaw("
                LOWER(COALESCE(kc.kecamatan, '')) as label_key,
                COUNT(*) as total
            ")
            ->where(function ($builder) use ($focusNames) {
                foreach ($focusNames as $name) {
                    $builder->orWhereRaw('LOWER(COALESCE(kc.kecamatan, "")) = ?', [$name]);
                }
            })
            ->groupBy(DB::raw('LOWER(COALESCE(kc.kecamatan, ""))'))
            ->pluck('total', 'label_key');

        return collect(self::FOCUS_KECAMATANS)
            ->map(fn($label) => [
                'label' => $label,
                'total' => (int) ($totals[strtolower($label)] ?? 0),
            ])
            ->all();
    }

    private function buildSulawesiGorontaloBreakdown($query): array
    {
        $provinceTotals = $query
            ->whereIn('e.provinsi_id', array_keys(self::SULAWESI_GORONTALO_MAP))
            ->selectRaw('COALESCE(e.provinsi_id, "") as provinsi_id, COUNT(*) as total')
            ->groupBy('e.provinsi_id')
            ->pluck('total', 'provinsi_id');

        return collect(self::SULAWESI_GORONTALO_MAP)
            ->map(fn($label, $provinsiId) => [
                'label' => $label,
                'total' => (int) ($provinceTotals[$provinsiId] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function buildKabupatenSummary($query, array $filters): array
    {
        $row = $query
            ->selectRaw("
                COALESCE(kb.kabupaten, 'Belum diketahui') as label,
                COALESCE(p.provinsi, 'Belum diketahui') as parent_label,
                COUNT(*) as total,
                COUNT(DISTINCT e.kecamatan_id) as coverage_total
            ")
            ->groupBy('e.kabupaten_id', 'kb.kabupaten', 'p.provinsi')
            ->orderByDesc('total')
            ->orderBy('kb.kabupaten')
            ->first();

        if (!$row) {
            return [
                'title' => $filters['kabupaten_id'] ? 'Kabupaten Dipilih' : 'Kabupaten Dominan',
                'badge' => $filters['kabupaten_id'] ? 'Dipilih' : 'Dominan',
                'label' => 'Belum ada data',
                'parent_label' => 'Belum ada data',
                'total' => 0,
                'coverage_total' => 0,
                'coverage_label' => 'Kecamatan tercakup',
            ];
        }

        return [
            'title' => $filters['kabupaten_id'] ? 'Kabupaten Dipilih' : 'Kabupaten Dominan',
            'badge' => $filters['kabupaten_id'] ? 'Dipilih' : 'Dominan',
            'label' => $row->label,
            'parent_label' => $row->parent_label,
            'total' => (int) $row->total,
            'coverage_total' => (int) $row->coverage_total,
            'coverage_label' => 'Kecamatan tercakup',
        ];
    }

    private function buildKecamatanSummary($query, array $filters): array
    {
        $row = $query
            ->selectRaw("
                COALESCE(kc.kecamatan, 'Belum diketahui') as label,
                COALESCE(kb.kabupaten, 'Belum diketahui') as parent_label,
                COALESCE(p.provinsi, 'Belum diketahui') as province_label,
                COUNT(*) as total,
                COUNT(DISTINCT e.kelurahan_id) as coverage_total
            ")
            ->groupBy('e.kecamatan_id', 'kc.kecamatan', 'kb.kabupaten', 'p.provinsi')
            ->orderByDesc('total')
            ->orderBy('kc.kecamatan')
            ->first();

        if (!$row) {
            return [
                'title' => $filters['kecamatan_id'] ? 'Kecamatan Dipilih' : 'Kecamatan Dominan',
                'badge' => $filters['kecamatan_id'] ? 'Dipilih' : 'Dominan',
                'label' => 'Belum ada data',
                'parent_label' => 'Belum ada data',
                'province_label' => 'Belum ada data',
                'total' => 0,
                'coverage_total' => 0,
                'coverage_label' => 'Kelurahan tercakup',
            ];
        }

        return [
            'title' => $filters['kecamatan_id'] ? 'Kecamatan Dipilih' : 'Kecamatan Dominan',
            'badge' => $filters['kecamatan_id'] ? 'Dipilih' : 'Dominan',
            'label' => $row->label,
            'parent_label' => $row->parent_label,
            'province_label' => $row->province_label,
            'total' => (int) $row->total,
            'coverage_total' => (int) $row->coverage_total,
            'coverage_label' => 'Kelurahan tercakup',
        ];
    }

    private function buildDistributionRows($query): array
    {
        return $query
            ->selectRaw("
                COALESCE(p.provinsi, 'Belum diketahui') as provinsi,
                COALESCE(kb.kabupaten, 'Belum diketahui') as kabupaten,
                COALESCE(kc.kecamatan, 'Belum diketahui') as kecamatan,
                COALESCE(kl.kelurahan, 'Belum diketahui') as kelurahan,
                SUM(CASE WHEN LOWER(COALESCE(e.jenis_kelamin, '')) IN ('l', 'laki-laki') THEN 1 ELSE 0 END) as laki_laki,
                SUM(CASE WHEN LOWER(COALESCE(e.jenis_kelamin, '')) IN ('p', 'perempuan') THEN 1 ELSE 0 END) as perempuan,
                COUNT(*) as total
            ")
            ->groupBy(
                'e.provinsi_id',
                'p.provinsi',
                'e.kabupaten_id',
                'kb.kabupaten',
                'e.kecamatan_id',
                'kc.kecamatan',
                'e.kelurahan_id',
                'kl.kelurahan'
            )
            ->orderByDesc('total')
            ->orderBy('provinsi')
            ->orderBy('kabupaten')
            ->orderBy('kecamatan')
            ->orderBy('kelurahan')
            ->get()
            ->map(fn($row) => [
                'provinsi' => $row->provinsi,
                'kabupaten' => $row->kabupaten,
                'kecamatan' => $row->kecamatan,
                'kelurahan' => $row->kelurahan,
                'laki_laki' => (int) $row->laki_laki,
                'perempuan' => (int) $row->perempuan,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
