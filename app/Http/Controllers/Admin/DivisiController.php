<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_divisi' => 'required|string|max:255'
        ]);

        $divisi = Divisi::findOrFail($id);

        $divisi->update([
            'nama_divisi' => $request->nama_divisi
        ]);

        return response()->json([
            'success' => true,
            'nama_divisi' => $divisi->nama_divisi
        ]);
    }

    public function destroy($id)
    {
        Divisi::where('id', $id)->delete();

        toast()->success('Success', 'Divisi deleted successfully');
        return back();
    }

    public function mergeDivisi(Request $request)
    {
        $sources = $request->source_divisi;
        $target  = $request->target_divisi;

        if (in_array($target, $sources)) {
            return response()->json([
                'error' => 'Target tidak boleh termasuk source'
            ], 422);
        }

        DB::transaction(function () use ($sources, $target) {

            Employee::whereIn('divisi_id', $sources)
                ->update([
                    'divisi_id' => $target
                ]);
        });

        return response()->json([
            'success' => true
        ]);
    }
}
