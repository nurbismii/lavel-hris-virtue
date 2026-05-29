<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OvertimeOrder;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Presensi\AttendancePeriodLockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OvertimeOrderController extends Controller
{
    public function index(Request $request)
    {
        $overtimeOrders = OvertimeOrder::query()
            ->with(['requester', 'employee'])
            ->where('nik_karyawan', $request->user()->nik_karyawan)
            ->latest('overtime_date')
            ->latest('id')
            ->get();

        return view('user.lembur.index', [
            'overtimeOrders' => $overtimeOrders,
        ]);
    }

    public function respond(Request $request, $id)
    {
        $overtimeOrder = OvertimeOrder::query()
            ->with('requester')
            ->where('nik_karyawan', $request->user()->nik_karyawan)
            ->findOrFail($id);

        if ($overtimeOrder->employee_response_status !== OvertimeOrder::RESPONSE_PENDING) {
            toast()->warning('Peringatan', 'Perintah lembur ini sudah Anda respons.');
            return redirect()->route('lembur.index');
        }

        if ($overtimeOrder->isPastDate()) {
            toast()->warning('Peringatan', 'Perintah lembur ini sudah melewati tanggal pelaksanaan dan tidak dapat direspons lagi.');
            return redirect()->route('lembur.index');
        }

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardDate(
            $overtimeOrder->overtime_date,
            'Respons perintah lembur'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return redirect()->route('lembur.index');
        }

        $validated = $request->validate([
            'response' => ['required', Rule::in([OvertimeOrder::RESPONSE_ACCEPTED, OvertimeOrder::RESPONSE_REJECTED])],
            'employee_response_notes' => 'nullable|string|max:2000',
        ]);

        $overtimeOrder->update([
            'employee_response_status' => $validated['response'],
            'employee_response_notes' => $validated['employee_response_notes'] ?? null,
            'employee_response_at' => now(),
        ]);

        if ($overtimeOrder->requester) {
            $overtimeOrder->requester->notify(new StatusPengajuanNotification([
                'judul' => 'Perintah Lembur',
                'pesan' => (auth()->user()->employee->nama_karyawan ?? auth()->user()->name) . ' telah ' . strtolower($overtimeOrder->response_label) . ' untuk tanggal ' . $overtimeOrder->overtime_date->format('d-m-Y') . '.',
                'url' => route('overtime-orders.show', $overtimeOrder->id),
                'tipe' => $overtimeOrder->type_label,
            ]));
        }

        toast()->success('Success', 'Respons perintah lembur berhasil disimpan.');
        return redirect()->route('lembur.index');
    }
}
