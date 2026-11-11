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
        app(\App\Services\Recovery\DeletedDataService::class)->change('departemen', (string) $id, request()->user(), false);
        if (request()->expectsJson()) { return response()->json(['success'=>true,'message'=>'Departemen dipindahkan ke Tempat Sampah.']); }
        
        return back()->with('success', 'Departemen dipindahkan ke Tempat Sampah.');
    }
}
