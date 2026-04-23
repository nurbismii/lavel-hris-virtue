<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\WorkPattern;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkPatternController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        return view('admin.work-patterns.index', [
            'workPatterns' => WorkPattern::query()
                ->withCount('employees')
                ->orderBy('name')
                ->get(),
            'divisions' => $this->getScopedDivisions($request),
            'activeWorkPatterns' => WorkPattern::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return view('admin.work-patterns.create', [
            'unitOptions' => WorkPattern::unitOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        WorkPattern::create($validated);

        toast()->success('Success', 'Master jadwal kerja berhasil ditambahkan.');
        return redirect()->route('work-patterns.index');
    }

    public function edit($id)
    {
        return view('admin.work-patterns.edit', [
            'workPattern' => WorkPattern::findOrFail($id),
            'unitOptions' => WorkPattern::unitOptions(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $workPattern = WorkPattern::findOrFail($id);
        $validated = $this->validateRequest($request, $workPattern->id);

        $workPattern->update($validated);

        toast()->success('Success', 'Master jadwal kerja berhasil diperbarui.');
        return redirect()->route('work-patterns.index');
    }

    public function destroy($id)
    {
        $workPattern = WorkPattern::findOrFail($id);

        if ($workPattern->employees()->exists()) {
            toast()->warning('Peringatan', 'Pola kerja ini masih dipakai oleh karyawan dan tidak dapat dihapus.');
            return redirect()->route('work-patterns.index');
        }

        $workPattern->delete();

        toast()->success('Success', 'Master jadwal kerja berhasil dihapus.');
        return redirect()->route('work-patterns.index');
    }

    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'divisi_id' => 'required|integer|exists:divisis,id',
            'work_pattern_id' => 'required|integer|exists:work_patterns,id',
            'work_pattern_start_date' => 'required|date',
        ]);

        $division = $this->getScopedDivisions($request)->firstWhere('id', (int) $validated['divisi_id']);

        if (!$division) {
            abort(403, 'Divisi yang dipilih tidak termasuk dalam scope akses Anda.');
        }

        $updatedCount = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->where('status_resign', 'AKTIF')
            ->where('divisi_id', $division->id)
            ->update([
                'work_pattern_id' => $validated['work_pattern_id'],
                'work_pattern_start_date' => $validated['work_pattern_start_date'],
                'updated_at' => now(),
            ]);

        if ($updatedCount < 1) {
            toast()->warning('Peringatan', 'Tidak ada karyawan aktif dalam divisi tersebut untuk diperbarui.');
            return redirect()->route('work-patterns.index');
        }

        toast()->success('Success', "{$updatedCount} karyawan aktif pada divisi {$division->nama_divisi} berhasil diperbarui.");
        return redirect()->route('work-patterns.index');
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('work_patterns', 'code')->ignore($ignoreId),
            ],
            'name' => 'required|string|max:120',
            'work_duration_value' => 'required|integer|min:1|max:365',
            'work_duration_unit' => ['required', Rule::in(array_keys(WorkPattern::unitOptions()))],
            'off_duration_value' => 'required|integer|min:1|max:365',
            'off_duration_unit' => ['required', Rule::in(array_keys(WorkPattern::unitOptions()))],
            'start_time' => 'nullable|date_format:H:i|required_with:end_time',
            'end_time' => 'nullable|date_format:H:i|required_with:start_time',
            'break_start_time' => 'nullable|date_format:H:i|required_with:break_end_time',
            'break_end_time' => 'nullable|date_format:H:i|required_with:break_start_time',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:2000',
        ]);

        if (
            (filled($validated['break_start_time'] ?? null) || filled($validated['break_end_time'] ?? null))
            && (!filled($validated['start_time'] ?? null) || !filled($validated['end_time'] ?? null))
        ) {
            throw ValidationException::withMessages([
                'break_start_time' => 'Jam istirahat hanya bisa diisi jika jam masuk dan jam pulang juga diatur.',
            ]);
        }

        if (filled($validated['start_time'] ?? null)) {
            $validated['start_time'] .= ':00';
        }

        if (filled($validated['end_time'] ?? null)) {
            $validated['end_time'] .= ':00';
        }

        if (filled($validated['break_start_time'] ?? null)) {
            $validated['break_start_time'] .= ':00';
        }

        if (filled($validated['break_end_time'] ?? null)) {
            $validated['break_end_time'] .= ':00';
        }

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function getScopedDivisions(Request $request)
    {
        $user = $request->user();
        $query = Divisi::query()
            ->with('departemen.perusahaan')
            ->withCount(['karyawan as active_employee_count'])
            ->orderBy('nama_divisi');

        if ($user->canAccessAllEmployees()) {
            return $query->get();
        }

        if ($user->isDepartmentScopedRole()) {
            return $query
                ->whereIn('departemen_id', $user->scopedDepartmentIds())
                ->get();
        }

        if ($user->isDivisionScopedRole()) {
            return $query
                ->whereIn('id', $user->scopedDivisionIds())
                ->get();
        }

        return collect();
    }
}
