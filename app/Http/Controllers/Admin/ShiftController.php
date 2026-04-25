<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        return view('admin.shifts.index', [
            'shifts' => Shift::query()
                ->withCount('assignments')
                ->orderByRaw("FIELD(type, 'reguler', 'shift_1', 'shift_2', 'shift_3', 'custom')")
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return view('admin.shifts.create', [
            'typeOptions' => Shift::typeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        Shift::create($validated);

        toast()->success('Success', 'Master shift berhasil ditambahkan.');
        return redirect()->route('shifts.index');
    }

    public function edit($id)
    {
        return view('admin.shifts.edit', [
            'shift' => Shift::findOrFail($id),
            'typeOptions' => Shift::typeOptions(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);
        $validated = $this->validateRequest($request, $shift->id);

        $shift->update($validated);

        toast()->success('Success', 'Master shift berhasil diperbarui.');
        return redirect()->route('shifts.index');
    }

    public function destroy($id)
    {
        $shift = Shift::findOrFail($id);

        if ($shift->assignments()->exists()) {
            toast()->warning('Peringatan', 'Shift ini masih dipakai pada pengaturan shift dan tidak dapat dihapus.');
            return redirect()->route('shifts.index');
        }

        $shift->delete();

        toast()->success('Success', 'Master shift berhasil dihapus.');
        return redirect()->route('shifts.index');
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('shifts', 'code')->ignore($ignoreId),
            ],
            'name' => 'required|string|max:120',
            'type' => ['required', Rule::in(array_keys(Shift::typeOptions()))],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
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

        $validated['start_time'] .= ':00';
        $validated['end_time'] .= ':00';

        if (filled($validated['break_start_time'] ?? null)) {
            $validated['break_start_time'] .= ':00';
        }

        if (filled($validated['break_end_time'] ?? null)) {
            $validated['break_end_time'] .= ':00';
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}
