<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\LokasiAbsen;
use App\Models\Perusahaan;
use Illuminate\Http\Request;

class SettingLokasiPresensiController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $lokasi = LokasiAbsen::with('divisi.departemen')
            ->select('id', 'divisi_id', 'lat', 'long', 'radius', 'created_at')
            ->get();

        return view('admin.setting-lokasi.index', compact(
            'lokasi'
        ));
    }

    public function create()
    {
        $areas = Perusahaan::select('*')->get();
        $departemens = Departemen::with('perusahaan')->orderBy('departemen')->get();
        $divisis = Divisi::orderBy('nama_divisi')->get();

        return view('admin.setting-lokasi.create', compact(
            'departemens',
            'divisis',
            'areas'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'divisi_id' => 'required|exists:divisis,id',
            'lat'       => 'required|numeric',
            'long'      => 'required|numeric',
            'radius'    => 'required|numeric|min:1|max:10000',
        ], [
            'divisi_id.required' => 'Divisi wajib dipilih.',
            'divisi_id.exists'   => 'Divisi tidak valid.',
            'lat.required'       => 'Latitude wajib diisi.',
            'lat.numeric'        => 'Latitude harus berupa angka.',
            'long.required'      => 'Longitude wajib diisi.',
            'long.numeric'       => 'Longitude harus berupa angka.',
            'radius.required'    => 'Radius wajib diisi.',
            'radius.numeric'     => 'Radius harus berupa angka.',
            'radius.min'         => 'Radius minimal 1 meter.',
            'radius.max'         => 'Radius maksimal 10.000 meter.',
        ]);

        LokasiAbsen::create([
            'divisi_id' => $validated['divisi_id'],
            'lat'       => $validated['lat'],
            'long'      => $validated['long'],
            'radius'    => $validated['radius'],
            'created_at' => now()
        ]);

        toast()->success('Success', 'Lokasi presensi created successfuly');
        return redirect()->route('setting-lokasi-presensi.index');
    }

    public function edit($id)
    {
        $areas = Perusahaan::select('*')->get();
        $departemens = Departemen::with('perusahaan')->orderBy('departemen')->get();
        $divisis = Divisi::orderBy('nama_divisi')->get();

        $lokasi = LokasiAbsen::where('id', $id)->first();

        return view('admin.setting-lokasi.edit', compact(
            'areas',
            'departemens',
            'divisis',
            'lokasi'
        ));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'divisi_id' => 'required|exists:divisis,id',
            'lat'       => 'required|numeric',
            'long'      => 'required|numeric',
            'radius'    => 'required|numeric|min:1|max:10000',
        ], [
            'divisi_id.required' => 'Divisi wajib dipilih.',
            'divisi_id.exists'   => 'Divisi tidak valid.',
            'lat.required'       => 'Latitude wajib diisi.',
            'lat.numeric'        => 'Latitude harus berupa angka.',
            'long.required'      => 'Longitude wajib diisi.',
            'long.numeric'       => 'Longitude harus berupa angka.',
            'radius.required'    => 'Radius wajib diisi.',
            'radius.numeric'     => 'Radius harus berupa angka.',
            'radius.min'         => 'Radius minimal 1 meter.',
            'radius.max'         => 'Radius maksimal 10.000 meter.',
        ]);

        LokasiAbsen::where('id', $id)->update($validated);

        toast()->success('Success', 'Lokasi presensi updated successfully');
        return redirect()->route('setting-lokasi-presensi.index');
    }

    public function destroy($id)
    {
        LokasiAbsen::where('id', $id)->delete();

        toast()->success('Success', 'Lokasi presensi deleted successfuly');
        return redirect()->route('setting-lokasi-presensi.index');
    }
}
