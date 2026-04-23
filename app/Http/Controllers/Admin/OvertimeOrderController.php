<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeOrder;
use App\Models\Presensi;
use App\Notifications\StatusPengajuanNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OvertimeOrderController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $query = $request->user()
            ->applyEmployeeRelationScope(
                OvertimeOrder::query()->with(['employee.divisi.departemen', 'requester'])
            )
            ->latest('overtime_date')
            ->latest('id');

        if ($request->filled('response_status')) {
            $query->where('employee_response_status', $request->response_status);
        }

        return view('admin.overtime-orders.index', [
            'overtimeOrders' => $query->get(),
            'responseOptions' => $this->responseOptions(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.overtime-orders.create', [
            'employees' => $request->user()
                ->applyEmployeeScope(Employee::query()->with('workPattern'))
                ->where('status_resign', 'AKTIF')
                ->orderBy('nama_karyawan')
                ->get(['nik', 'nama_karyawan', 'divisi_id', 'departemen_id', 'work_pattern_id']),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $employee = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->where('nik', $validated['nik_karyawan'])
            ->where('status_resign', 'AKTIF')
            ->firstOrFail();

        $validated['requested_by_user_id'] = $request->user()->id;
        $validated['required_minutes'] = $this->calculateRequiredMinutes(
            $validated['start_time'] ?? null,
            $validated['end_time'] ?? null
        );

        $overtimeOrder = OvertimeOrder::create($validated);
        $employeeUser = $overtimeOrder->employeeUser;

        if ($employeeUser) {
            $employeeUser->notify(new StatusPengajuanNotification([
                'judul' => 'Perintah Lembur',
                'pesan' => 'Anda menerima perintah ' . strtolower($overtimeOrder->type_label) . ' untuk tanggal ' . $overtimeOrder->overtime_date->format('d-m-Y') . '.',
                'url' => route('lembur.index'),
                'tipe' => $overtimeOrder->type_label,
            ]));
        }

        toast()->success('Success', 'Perintah lembur berhasil dibuat.');
        return redirect()->route('overtime-orders.index');
    }

    public function show(Request $request, $id)
    {
        $overtimeOrder = $request->user()
            ->applyEmployeeRelationScope(
                OvertimeOrder::query()->with(['employee.divisi.departemen', 'requester', 'employeeUser'])
            )
            ->findOrFail($id);

        return view('admin.overtime-orders.show', [
            'overtimeOrder' => $overtimeOrder,
            'attendanceRecord' => Presensi::query()
                ->where('nik_karyawan', $overtimeOrder->nik_karyawan)
                ->whereDate('tanggal', $overtimeOrder->overtime_date)
                ->first(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $overtimeOrder = $request->user()
            ->applyEmployeeRelationScope(OvertimeOrder::query()->with('employeeUser'))
            ->findOrFail($id);

        if ($overtimeOrder->employee_response_status !== OvertimeOrder::RESPONSE_PENDING) {
            toast()->warning('Peringatan', 'Perintah lembur yang sudah direspons tidak dapat dihapus.');
            return redirect()->route('overtime-orders.index');
        }

        if ($overtimeOrder->employeeUser) {
            $overtimeOrder->employeeUser->notify(new StatusPengajuanNotification([
                'judul' => 'Perintah Lembur',
                'pesan' => 'Perintah lembur untuk tanggal ' . $overtimeOrder->overtime_date->format('d-m-Y') . ' telah dibatalkan.',
                'url' => route('lembur.index'),
                'tipe' => 'Perintah Lembur',
            ]));
        }

        $overtimeOrder->delete();

        toast()->success('Success', 'Perintah lembur berhasil dibatalkan.');
        return redirect()->route('overtime-orders.index');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'nik_karyawan' => 'required|string|exists:employees,nik',
            'overtime_type' => ['required', Rule::in(array_keys($this->typeOptions()))],
            'overtime_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'reason' => 'required|string|max:2000',
            'instruction_notes' => 'nullable|string|max:2000',
        ]);
    }

    private function calculateRequiredMinutes(?string $startTime, ?string $endTime): ?int
    {
        if (!$startTime || !$endTime) {
            return null;
        }

        $start = strtotime($startTime);
        $end = strtotime($endTime);

        if ($end <= $start) {
            return null;
        }

        return (int) round(($end - $start) / 60);
    }

    private function typeOptions(): array
    {
        return [
            OvertimeOrder::TYPE_OFF => 'Lembur Off',
            OvertimeOrder::TYPE_HOLIDAY => 'Lembur Tanggal Merah',
            OvertimeOrder::TYPE_EXTRA_HOURS => 'Lembur Kelebihan Jam',
        ];
    }

    private function responseOptions(): array
    {
        return [
            OvertimeOrder::RESPONSE_PENDING => 'Menunggu Respons',
            OvertimeOrder::RESPONSE_ACCEPTED => 'Disetujui Karyawan',
            OvertimeOrder::RESPONSE_REJECTED => 'Ditolak Karyawan',
        ];
    }
}
