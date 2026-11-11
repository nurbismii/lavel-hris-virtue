<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\SaveSalaryComponentRequest;
use App\Models\SalaryComponent;
use App\Services\Payroll\SalaryComponentService;
use App\Services\Recovery\DeletedDataService;
use Illuminate\Http\Request;

class SalaryComponentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('salary_components'), 503, 'Master komponen gaji belum disiapkan.');
        $filters = $request->validate(['q'=>['nullable','string','max:100']]);
        $search = trim($filters['q'] ?? '');
        $components = SalaryComponent::when($search !== '', fn ($q) => $q->where(fn ($match) => $match->where('code', $search)->orWhere('name', 'like', $search.'%')))
            ->orderBy('code')->paginate(40)->withQueryString();
        return view('admin.salary-components.index', compact('components', 'search'));
    }

    public function store(SaveSalaryComponentRequest $request, SalaryComponentService $service)
    {
        $service->save($request->validated(), $request->user());
        return response()->json(['success'=>true, 'message'=>'Komponen gaji berhasil ditambahkan.']);
    }

    public function update(SaveSalaryComponentRequest $request, SalaryComponent $component, SalaryComponentService $service)
    {
        $service->save($request->validated(), $request->user(), $component);
        return response()->json(['success'=>true, 'message'=>'Nama komponen berhasil diperbarui. Arsip payroll tetap menggunakan nama saat dihitung.']);
    }

    public function destroy(Request $request, SalaryComponent $component, DeletedDataService $service)
    {
        $service->change('komponen-gaji', (string) $component->id, $request->user(), false);
        return response()->json(['success'=>true, 'message'=>'Komponen dipindahkan ke Tempat Sampah. Komponen ini tidak dipakai pada perhitungan baru.']);
    }
}
