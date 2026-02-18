<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Epayslip\KomponenGaji;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class SlipGajiController extends Controller
{
    //
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $slipGajiService = app()->make(\App\Services\SlipGaji\SlipGajiService::class);

            return $slipGajiService->getSlipGajiData($request);
        }

        return view('admin.slip-gaji.index');
    }

    public function show($id)
    {
        $slip = KomponenGaji::with('karyawan')->where('id', $id)->first();

        return view('admin.slip-gaji.show', [
            'slip' => $slip
        ]);
    }

    public function exportPdf($id)
    {
        $slip = KomponenGaji::with('karyawan')->findOrFail($id);

        $pdf = Pdf::loadView('admin.slip-gaji.pdf', compact('slip'))
            ->setPaper('A4', 'portrait');

        return $pdf->stream(
            'Slip-Gaji-' . ($slip->karyawan->nik ?? 'karyawan') . '-' . $slip->periode . '.pdf'
        );
    }
}
