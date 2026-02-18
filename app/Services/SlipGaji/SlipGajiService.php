<?php

namespace App\Services\SlipGaji;

use App\Models\Epayslip\Karyawan;
use App\Models\Epayslip\KomponenGaji;
use Yajra\DataTables\Facades\DataTables;

class SlipGajiService
{
    //
    public function getSlipGajiData($request)
    {
        $slipGaji = KomponenGaji::query()
            ->join('data_karyawans', 'data_karyawans.id', '=', 'komponen_gajis.data_karyawan_id')
            ->select([
                'komponen_gajis.*',
                'data_karyawans.nik',
                'data_karyawans.nama'
            ])
            ->orderBy('komponen_gajis.periode', 'desc');

        if ($request->has('periode')) {
            $slipGaji = $slipGaji->where('komponen_gajis.periode', $request->periode);
        }

        return DataTables::of($slipGaji)
            ->addColumn('total_gaji', fn($r) => number_format($r->tot_diterima, 0, ',', '.'))
            ->addColumn('aksi', function ($r) {
                return '
                    <a href="' . route('slip-gaji.show', $r->id) . '" 
                        target="_blank"   
                        class="btn btn-sm btn-info me-1">
                        <i class="fa fa-eye"></i>
                    </a>
                ';
            })
            ->rawColumns(['aksi'])
            ->make(true);
    }
}
