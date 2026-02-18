<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Perusahaan;
use Illuminate\Http\Request;

class DepartemenController extends Controller
{
    public function create($perusahaan_id)
    {
        $perusahaan = Perusahaan::where('id', $perusahaan_id)->first();

        return view('admin.departemen.create', compact('perusahaan'));
    }

    public function store(Request $request)
    {
        Departemen::create([
            'perusahaan_id' => $request->perusahaan_id,
            'departemen' => $request->departemen,
            'kepala_dept' => $request->kepala_dept,
            'no_telp_departemen' => $request->no_telp_departemen,
            'status_pengeluaran' => $request->status_pengeluaran
        ]);

        toast()->success('Success', 'Departemen created successfully');
        return back();
    }

    public function destroy($id)
    {
        $departemen = Departemen::findOrFail($id);
        Divisi::where('departemen_id', $departemen->id)->delete();
        
        return back()->with('success', 'Departemen & seluruh divisi berhasil dihapus');
    }
}
