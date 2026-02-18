<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Perusahaan;
use Illuminate\Http\Request;

class DivisiController extends Controller
{
    public function create($perusahaan_id)
    {
        $perusahaan = Perusahaan::where('id', $perusahaan_id)->first();
        $departemens = Departemen::with('perusahaan')->orderBy('departemen')->get();

        return view('admin.divisi.create', compact('perusahaan', 'departemens'));
    }

    public function store(Request $request)
    {
        Divisi::create([
            'departemen_id' => $request->departemen_id,
            'nama_divisi' => $request->nama_divisi,
        ]);

        toast()->success('Success', 'Divisi created successfully');
        return back();
    }

    public function destroy($id)
    {
        Divisi::where('id', $id)->delete();

        toast()->success('Success', 'Divisi deleted successfully');
        return back();
    }
}
