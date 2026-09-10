<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CvMaker\StorePdfBatchRequest;
use App\Models\CvMakerPdfBatch;
use App\Services\CvMaker\CvMakerPdfExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CvMakerPdfController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless($request->user() && CvMakerPdfExportService::canAccess($request->user()), 403);
            return $next($request);
        });
    }

    public function store(StorePdfBatchRequest $request, CvMakerPdfExportService $service)
    {
        $batch = $service->create($request->validated(), $request->user());
        return response()->json(['success' => true,
            'message' => 'Permintaan PDF diterima. Proses berjalan melalui antrean; unduh setelah status siap.',
            'data' => $service->payload($batch)], 202);
    }

    public function index(Request $request, CvMakerPdfExportService $service)
    {
        $batches = CvMakerPdfBatch::where('requested_by', $request->user()->id)->withCount([
            'items as success_count' => function ($q) { $q->where('status', 'completed'); },
            'items as failed_count' => function ($q) { $q->where('status', 'failed'); },
        ])->latest('id')->paginate(10);
        return response()->json(['success' => true, 'data' => $batches->getCollection()->map(function ($batch) use ($service) {
            return $service->payload($batch);
        }), 'next_page_url' => $batches->nextPageUrl(), 'prev_page_url' => $batches->previousPageUrl()]);
    }

    public function status(Request $request, CvMakerPdfBatch $batch, CvMakerPdfExportService $service)
    {
        abort_unless((string) $batch->requested_by === (string) $request->user()->id, 403);
        $data = $service->payload($batch);
        $scope = $request->user()->applyCvMakerEmployeeScope(\App\Models\Employee::query(), 'employees')->select('employees.nik');
        $data['failed_items'] = $batch->items()->where('status', 'failed')->whereIn('employee_nik', $scope)
            ->get(['employee_nik', 'error_message']);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function download(Request $request, CvMakerPdfBatch $batch, CvMakerPdfExportService $service)
    {
        return $service->download($batch, $request->user());
    }

    public function cancel(Request $request, CvMakerPdfBatch $batch, CvMakerPdfExportService $service)
    {
        abort_unless((string) $batch->requested_by === (string) $request->user()->id, 403);
        $lock = Cache::lock('cv-pdf-batch:' . $batch->id, 240);
        abort_unless($lock->get(), 409, 'Satu PDF sedang diselesaikan. Coba batalkan lagi sebentar.');
        try {
            $batch->refresh();
            if (!in_array($batch->status, ['cancelled', 'expired'], true)) {
                $service->close($batch, 'cancelled', 'Batch dibatalkan. CV yang belum diunduh dapat diajukan kembali.');
            }
        } finally {
            $lock->release();
        }
        return response()->json(['success' => true, 'message' => 'Batch dibatalkan.', 'data' => $service->payload($batch)]);
    }
}
