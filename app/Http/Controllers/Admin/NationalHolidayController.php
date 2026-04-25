<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NationalHoliday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NationalHolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);
        $isTableReady = Schema::hasTable('national_holidays');
        $nationalHolidays = collect();

        if ($isTableReady) {
            $nationalHolidays = NationalHoliday::query()
                ->whereBetween('holiday_date', [
                    Carbon::create($year, 1, 1)->toDateString(),
                    Carbon::create($year, 12, 31)->toDateString(),
                ])
                ->orderBy('holiday_date')
                ->get();
        }

        return view('admin.national-holidays.index', [
            'year' => $year,
            'nationalHolidays' => $nationalHolidays,
            'isTableReady' => $isTableReady,
        ]);
    }

    public function store(Request $request)
    {
        if (!Schema::hasTable('national_holidays')) {
            toast()->error('Error', 'Tabel tanggal merah nasional belum tersedia. Jalankan migrate terlebih dahulu.');
            return redirect()->route('national-holidays.index');
        }

        $validated = $request->validate([
            'holiday_date' => 'required|date',
            'holiday_name' => 'required|string|max:150',
        ]);

        $holiday = NationalHoliday::firstOrNew([
            'holiday_date' => $validated['holiday_date'],
        ]);

        $isNewRecord = !$holiday->exists;
        $holiday->holiday_name = $validated['holiday_name'];
        $holiday->updated_by = $request->user()->id;

        if ($isNewRecord) {
            $holiday->created_by = $request->user()->id;
        }

        $holiday->save();

        toast()->success(
            'Success',
            $isNewRecord
                ? 'Tanggal merah nasional berhasil ditambahkan.'
                : 'Tanggal merah nasional berhasil diperbarui.'
        );

        return redirect()->route('national-holidays.index', [
            'year' => Carbon::parse($validated['holiday_date'])->year,
        ]);
    }

    public function destroy(Request $request, NationalHoliday $nationalHoliday)
    {
        $year = $request->year ?: optional($nationalHoliday->holiday_date)->year ?: now()->year;

        $nationalHoliday->delete();

        toast()->success('Success', 'Tanggal merah nasional berhasil dihapus.');

        return redirect()->route('national-holidays.index', [
            'year' => $year,
        ]);
    }
}
