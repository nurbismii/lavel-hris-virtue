<?php

namespace App\Services\Karyawan;

use App\Models\Employee;
use Yajra\DataTables\Facades\DataTables;

class KaryawanService
{
    // Service methods for Karyawan can be implemented here
    public function getDataKaryawan($request)
    {
        // Logic to retrieve and filter Karyawan data
        $query = Employee::select('nik', 'nama_karyawan', 'area_kerja', 'departemen_id', 'divisi_id', 'status_resign', 'posisi')
            ->whereNotNull('status_resign')
            ->with(['departemen', 'divisi']);

        if ($request->area) {
            $query->where('area_kerja', $request->area);
        }

        if ($request->departemen) {
            $query->where('departemen_id', $request->departemen);
        }

        if ($request->divisi) {
            $query->where('divisi_id', $request->divisi);
        }

        if ($request->status_resign) {
            $query->where('status_resign', $request->status_resign);
        }

        return DataTables::of($query)
            ->addColumn('area', fn($r) => $r->area_kerja ?? '-')
            ->addColumn('departemen', fn($r) => $r->departemen->departemen ?? '-')
            ->addColumn('divisi', fn($r) => $r->divisi->nama_divisi ?? '-')
            ->addColumn('status', fn($r) => $r->status_resign ?? '-')
            ->addColumn('aksi', function ($r) {
                return '
                    <a href="' . route('karyawan.edit', $r->nik) . '" 
                       class="btn btn-sm btn-warning me-1">
                        <i class="fa fa-edit"></i>
                    </a>

                    <button class="btn btn-sm btn-danger btn-delete"
                        data-id="' . $r->nik . '"
                        data-nama="' . $r->nama_karyawan . '">
                        <i class="fa fa-trash"></i>
                    </button>
                ';
            })
            ->rawColumns(['aksi'])
            ->make(true);
    }
}
