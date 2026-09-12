<?php

namespace App\Services\SuratPeringatan;

use App\Models\SuratPeringatan;
use Yajra\DataTables\Facades\DataTables;

class SuratPeringatanService
{
    // Service methods for Karyawan can be implemented here
    public function getDataSuratPeringatan($request)
    {
        $query = SuratPeringatan::with(['employee', 'issuance:id,sp_report_id'])
            ->select(
                'id',
                'nik_karyawan',
                'no_sp',
                'level_sp',
                'tgl_mulai',
                'tgl_berakhir',
            );

        if (!$request->user()->canAccessAllEmployees()) {
            $query->whereHas('employee', function ($employees) use ($request) {
                $request->user()->applyEmployeeScope($employees);
            });
        }

        if ($request->filled('tgl_mulai') && $request->filled('tgl_berakhir')) {

            $start = $request->tgl_mulai;
            $end   = $request->tgl_berakhir;

            $query->where('tgl_mulai', '>=', $start)->where('tgl_berakhir', '<=', $end);
        }

        if ($request->filled('level_sp')) {
            $query->where('level_sp', $request->level_sp);
        }

        return DataTables::of($query)

            ->filter(function ($query) use ($request) {
                if ($request->filled('search.value')) {
                    $search = $request->search['value'];

                    $query->where(function ($q) use ($search) {
                        $q->where('nik_karyawan', 'like', "%{$search}%")
                            ->orWhereHas('employee', function ($k) use ($search) {
                                $k->where('nama_karyawan', 'like', "%{$search}%");
                            });
                    });
                }
            })

            ->addColumn('nama_karyawan', function ($r) {
                return optional($r->employee)->nama_karyawan ?? '-';
            })

            ->addColumn('aksi', function ($r) {
                if ($r->issuance) {
                    return '<a class="btn btn-sm btn-outline-primary" href="' . e(route('warning-letter-requests.show', $r->issuance)) . '">Lihat surat</a>';
                }
                return '
                <a href="' . e(route('surat-peringatan.edit', $r->id)) . '" 
                   class="btn btn-sm btn-warning me-1">
                    <i class="fa fa-edit"></i>
                </a>

                <button type="button" class="btn btn-sm btn-danger btn-delete"
                    data-id="' . e($r->id) . '"
                    data-nama="' . e($r->employee->nama_karyawan ?? '-') . '">
                    <i class="fa fa-trash"></i>
                </button>
            ';
            })

            ->rawColumns(['aksi'])
            ->make(true);
    }
}
