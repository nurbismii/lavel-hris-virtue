<?php

namespace App\Services\Resign;

use App\Models\Resign;
use Yajra\DataTables\Facades\DataTables;

class ResignService
{
    // Service methods for Karyawan can be implemented here
    public function getDataResign($request)
    {
        $query = Resign::with('employee')
            ->select(
                'id',
                'nik_karyawan',
                'tanggal_keluar',
                'periode_awal',
                'periode_akhir',
                'tipe',
            );

        if ($request->filled('periode_awal') && $request->filled('periode_akhir')) {
            $query->whereBetween('tanggal_keluar', [
                $request->periode_awal,
                $request->periode_akhir
            ]);
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
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
                return '
                <a href="' . route('resign.edit', $r->id) . '" 
                   class="btn btn-sm btn-warning me-1">
                    <i class="fa fa-edit"></i>
                </a>

                <button class="btn btn-sm btn-danger btn-delete"
                    data-id="' . $r->id . '"
                    data-nama="' . ($r->employee->nama_karyawan ?? '-') . '">
                    <i class="fa fa-trash"></i>
                </button>
            ';
            })

            ->rawColumns(['aksi'])
            ->make(true);
    }
}
