<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Epayslip\KomponenGaji;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class SlipgajiController extends Controller
{
    //
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $slipGaji = KomponenGaji::where('nik', auth()->user()->nik_karyawan)
                ->join('data_karyawans', 'data_karyawans.id', '=', 'komponen_gajis.data_karyawan_id')
                ->select([
                    'komponen_gajis.*',
                    'data_karyawans.nik',
                    'data_karyawans.nama'
                ])
                ->orderBy('komponen_gajis.periode', 'desc');

            return DataTables::of($slipGaji)
                ->addColumn('total_gaji', fn($r) => number_format($r->tot_diterima, 0, ',', '.'))
                ->addColumn('aksi', function ($r) {
                    return '
                    <a href="' . route('slipgaji.show', $r->id) . '" 
                        target="_blank"   
                        class="btn btn-sm btn-info me-1">
                        <i class="fa fa-eye"></i>
                    </a>
                ';
                })
                ->rawColumns(['aksi'])
                ->make(true);
        }

        return view('user.slip-gaji.index');
    }

    public function show($id)
    {
        $slip = KomponenGaji::with('karyawan')->where('id', $id)->first();

        return view('user.slip-gaji.show', [
            'slip' => $slip
        ]);
    }

    public function exportPdf($id)
    {
        $slip = KomponenGaji::with('karyawan')->findOrFail($id);

        $pdf = Pdf::loadView('user.slip-gaji.pdf', compact('slip'))
            ->setPaper('A4', 'portrait');

        return $pdf->stream(
            'Slip-Gaji-' . ($slip->karyawan->nik ?? 'karyawan') . '-' . $slip->periode . '.pdf'
        );
    }
}
