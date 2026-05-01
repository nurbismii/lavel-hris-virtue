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
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $nikKaryawan = $this->authenticatedEmployeeNik();

            $slipGaji = KomponenGaji::query()
                ->join('data_karyawans', 'data_karyawans.id', '=', 'komponen_gajis.data_karyawan_id')
                ->where('data_karyawans.nik', $nikKaryawan)
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
        $slip = $this->scopedSlipGajiQuery()
            ->where('id', $id)
            ->firstOrFail();

        return view('user.slip-gaji.show', [
            'slip' => $slip
        ]);
    }

    public function exportPdf($id)
    {
        $slip = $this->scopedSlipGajiQuery()
            ->where('id', $id)
            ->firstOrFail();

        $pdf = Pdf::loadView('user.slip-gaji.pdf', compact('slip'))
            ->setPaper('A4', 'portrait');

        return $pdf->stream(
            'Slip-Gaji-' . ($slip->karyawan->nik ?? 'karyawan') . '-' . $slip->periode . '.pdf'
        );
    }

    private function scopedSlipGajiQuery()
    {
        $nikKaryawan = $this->authenticatedEmployeeNik();

        return KomponenGaji::query()
            ->with('karyawan')
            ->whereHas('karyawan', function ($query) use ($nikKaryawan) {
                $query->where('nik', $nikKaryawan);
            });
    }

    private function authenticatedEmployeeNik(): string
    {
        $nikKaryawan = (string) optional(Auth::user())->nik_karyawan;

        abort_if($nikKaryawan === '', 403, 'NIK karyawan tidak ditemukan pada akun ini.');

        return $nikKaryawan;
    }
}
