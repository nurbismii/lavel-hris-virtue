<?php

namespace App\Services\Resign;

use App\Models\Employee;
use App\Models\Resign;
use Yajra\DataTables\Facades\DataTables;

class ResignService
{
    // Service methods for Karyawan can be implemented here
    public function getDataResign($request)
    {
        $query = Employee::query()
            ->from('employees')
            ->leftJoin('resign', 'resign.nik_karyawan', '=', 'employees.nik')
            ->select([
                'employees.nik as nik_karyawan',
                'employees.nama_karyawan',
                'employees.area_kerja',
                'employees.departemen_id',
                'employees.divisi_id',
                'employees.status_resign',
                'employees.posisi',
                'employees.tgl_resign',

                'resign.id as resign_id',
                'resign.tanggal_keluar',
                'resign.tipe',
                'resign.periode_awal',
                'resign.periode_akhir',
            ])
            ->whereNotNull('employees.status_resign')
            ->whereRaw('LOWER(employees.status_resign) != LOWER(?)', ['aktif']);

        if ($request->filled('periode_awal') && $request->filled('periode_akhir')) {
            $query->whereBetween('resign.tanggal_keluar', [
                $request->input('periode_awal'),
                $request->input('periode_akhir'),
            ]);
        }

        if ($request->filled('tipe')) {
            $query->where('resign.tipe', $request->input('tipe'));
        }

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                $search = trim($request->input('search.value', ''));

                if ($search === '' || strlen($search) < 3) {
                    return;
                }

                $escapedSearch = addcslashes($search, '%_\\');

                $query->where(function ($q) use ($search, $escapedSearch) {
                    if (ctype_digit($search)) {
                        $q->where('employees.nik', $search)
                            ->orWhere('employees.nik', 'like', $escapedSearch . '%');

                        return;
                    }

                    $q->where('employees.nama_karyawan', 'like', $escapedSearch . '%');
                });
            })

            ->editColumn('nama_karyawan', function ($r) {
                return $r->nama_karyawan ?: '-';
            })

            ->editColumn('tanggal_keluar', function ($r) {
                return $r->tanggal_keluar ?: '-';
            })

            ->editColumn('tipe', function ($r) {
                return $r->tipe ?: '-';
            })

            ->editColumn('periode_awal', function ($r) {
                return $r->periode_awal ?: '-';
            })

            ->editColumn('periode_akhir', function ($r) {
                return $r->periode_akhir ?: '-';
            })

            ->addColumn('aksi', function ($r) {
                if (!$r->resign_id) {
                    return '<span class="badge bg-secondary">Belum ada data resign</span>';
                }

                $namaKaryawan = e($r->nama_karyawan ?? '-');

                return '
                    <a href="' . route('resign.edit', $r->resign_id) . '" 
                       class="btn btn-sm btn-warning me-1">
                        <i class="fa fa-edit"></i>
                    </a>

                    <button class="btn btn-sm btn-danger btn-delete"
                        data-id="' . e($r->resign_id) . '"
                        data-nama="' . $namaKaryawan . '">
                        <i class="fa fa-trash"></i>
                    </button>
                ';
            })

            ->rawColumns(['aksi'])
            ->make(true);
    }
}
