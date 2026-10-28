<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreDepartmentRequest;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Perusahaan;

class DepartemenController extends Controller
{
    public function create($perusahaan_id)
    {
        $perusahaan = Perusahaan::findOrFail($perusahaan_id);

        return view('admin.departemen.create', compact('perusahaan'));
    }

    public function store(StoreDepartmentRequest $request)
    {
        Departemen::create($request->validated());

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
