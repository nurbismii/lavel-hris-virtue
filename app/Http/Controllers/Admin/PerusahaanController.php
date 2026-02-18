<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perusahaan;
use Illuminate\Http\Request;

class PerusahaanController extends Controller
{
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $perusahaan = Perusahaan::all();

        return view('admin.perusahaan.index', [
            'perusahaan' => $perusahaan
        ])->with('no');
    }

    public function edit($id)
    {
        $perusahaan = Perusahaan::where('id', $id)->first();

        return view('admin.perusahaan.edit', [
            'perusahaan' => $perusahaan
        ]);
    }

    public function show($id)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $perusahaan = Perusahaan::with([
            'departemen.divisi' => function ($q) {
                $q->withCount('karyawan');
            }
        ])->findOrFail($id);

        return view('admin.perusahaan.show', compact('perusahaan'));
    }

    public function update(Request $request, $id)
    {
        Perusahaan::where('id', $id)->update([
            'kode_perusahaan' => $request->kode_perusahaan,
            'nama_perusahaan' => $request->nama_perusahaan
        ]);

        toast()->success('Success', 'Company updated successfully.');
        return redirect()->route('perusahaan.index');
    }

    public function delete($id)
    {
        Perusahaan::where('id', $id)->delete();

        toast()->success('Success', 'Company deleted successfully.');
        return redirect()->route('perusahaan.index');
    }
}
