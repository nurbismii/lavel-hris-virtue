<?php

namespace App\Services\Resign;

use App\Models\Resign;
use Yajra\DataTables\Facades\DataTables;

class ResignService
{
    // Service methods for Karyawan can be implemented here
    public function getDataResign($request)
    {
        $query = Resign::query()
            ->leftJoin('employees', 'employees.nik', '=', 'resign.nik_karyawan')
            ->select([
                'resign.id',
                'resign.nik_karyawan',
                'resign.tanggal_keluar',
                'resign.periode_awal',
                'resign.periode_akhir',
                'resign.tipe',
                'employees.nama_karyawan',
            ]);

        if ($request->filled('periode_awal') && $request->filled('periode_akhir')) {
            $query->whereBetween('resign.tanggal_keluar', [
                $request->periode_awal,
                $request->periode_akhir
            ]);
        }

        if ($request->filled('tipe')) {
            $query->where('resign.tipe', $request->tipe);
        }

        return DataTables::of($query)

            ->filter(function ($query) use ($request) {
                if ($request->filled('search.value')) {
                    $search = trim($request->input('search.value'));

                    if ($search === '' || strlen($search) < 3) {
                        return;
                    }

                    $escapedSearch = addcslashes($search, '%_\\');

                    $query->where(function ($q) use ($search, $escapedSearch) {
                        if (ctype_digit($search)) {
                            $q->where('resign.nik_karyawan', $search)
                                ->orWhere('resign.nik_karyawan', 'like', $escapedSearch . '%');

                            return;
                        }

                        $q->where('resign.nik_karyawan', 'like', $escapedSearch . '%')
                            ->orWhere('employees.nama_karyawan', 'like', '%' . $escapedSearch . '%');
                    });
                }
            })

            ->editColumn('nama_karyawan', function ($r) {
                return $r->nama_karyawan ?? '-';
            })

            ->addColumn('aksi', function ($r) {
                $namaKaryawan = e($r->nama_karyawan ?? '-');

                return '
                <a href="' . route('resign.edit', $r->id) . '" 
                   class="btn btn-sm btn-warning me-1">
                    <i class="fa fa-edit"></i>
                </a>

                <button class="btn btn-sm btn-danger btn-delete"
                    data-id="' . $r->id . '"
                    data-nama="' . $namaKaryawan . '">
                    <i class="fa fa-trash"></i>
                </button>
            ';
            })

            ->rawColumns(['aksi'])
            ->make(true);
    }
}
