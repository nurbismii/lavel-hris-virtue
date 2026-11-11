<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Models\ImportHistory;
use App\Models\Perusahaan;
use App\Services\Payroll\PayrollCostReport;
use App\Services\Payroll\PayrollHrisSource;
use App\Services\Payroll\PayrollInputs;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\PayrollWorkbook;
use App\Services\Storage\SensitiveFileStorageService;
use App\Support\SafeExceptionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollHrisSource $source)
    {
        $this->authorizeActor($request);
        return response()->view('admin.payroll.index',[
            'imports'=>ImportHistory::where('import_type',ImportHistory::TYPE_PAYROLL)->latest('id')->paginate(15),
            'companies'=>Perusahaan::orderBy('nama_perusahaan')->get(['id','nama_perusahaan']),
            'nativeReady'=>Schema::hasTable('payroll_inputs') && Schema::hasTable('payroll_entries') && Schema::hasTable('salary_components'),
            'latestClosedPeriod'=>min($source->latestClosedPeriod(), config('payroll.valid_through')),
        ])->header('Cache-Control','private, no-store');
    }

    public function store(StorePayrollRequest $request, PayrollService $service)
    {
        try {
            $history=$service->upload($request->file('file'),$request->safe()->except('file'),$request->user());
        } catch (ValidationException $e) { throw $e; }
        catch (Throwable $e) {
            app(SafeExceptionLogger::class)->warning('payroll.upload',$e);
            return response()->json(['success'=>false,'message'=>'Payroll gagal masuk antrean. Minta admin memeriksa antrean lalu coba lagi.'],500);
        }
        return response()->json(['success'=>true,'message'=>'File diterima. Perhitungan gross-up dan BPJS masuk antrean; tinjau hasil sebelum finalisasi.',
            'data'=>['redirect_url'=>route('payroll.show',$history)]],202);
    }

    public function costs(Request $request, PayrollCostReport $report)
    {
        $this->authorizeActor($request);
        $filters = $request->validate([
            'employer_id'=>['nullable','integer','exists:perusahaan,id'],
            'period'=>['nullable','date_format:Y-m'],
            'nik'=>['nullable','string','max:100'],
        ]);
        $data = Schema::hasTable('payroll_entries')
            ? $report->forPeriod(isset($filters['employer_id']) ? (int) $filters['employer_id'] : null, $filters['period'] ?? null, $filters['nik'] ?? null)
            : ['period'=>$filters['period'] ?? now()->format('Y-m'), 'entries'=>null];

        return response()->view('admin.payroll.costs', $data + [
            'companies'=>Perusahaan::orderBy('nama_perusahaan')->pluck('nama_perusahaan', 'id'),
            'filters'=>$filters,
            'components'=>PayrollCostReport::COMPONENTS,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function calculate(Request $request, PayrollService $service, PayrollInputs $inputs)
    {
        $this->authorizeActor($request);
        $settings = $request->validate(PayrollInputs::periodRules() + [
            'tax_period'=>['required','date_format:Y-m','after_or_equal:'.config('payroll.valid_from'),'before_or_equal:'.config('payroll.valid_through')],
            'pay_date'=>['required','date_format:Y-m-d'],
        ]);
        try {
            $history = $service->start($settings, $request->user(), $inputs);
        } catch (ValidationException $e) { throw $e; }
        catch (Throwable $e) {
            app(SafeExceptionLogger::class)->warning('payroll.start',$e);
            return response()->json(['success'=>false,'message'=>'Payroll gagal masuk antrean. Minta admin memeriksa antrean lalu coba lagi.'],500);
        }
        return response()->json(['success'=>true,'message'=>'Perhitungan payroll masuk antrean. Tinjau hasil sebelum finalisasi.',
            'data'=>['redirect_url'=>route('payroll.show',$history)]],202);
    }

    public function show(Request $request, ImportHistory $history)
    {
        $this->authorizeHistory($request,$history);
        return response()->view('admin.payroll.show',[
            'history'=>$history,'items'=>$history->items()->orderBy('row_number')->paginate(50),
        ])->header('Cache-Control','private, no-store');
    }

    public function status(Request $request, ImportHistory $history)
    {
        $this->authorizeHistory($request,$history);
        return response()->json(['success'=>true,'message'=>$history->status_label,'data'=>[
            'status'=>$history->status,'status_label'=>$history->status_label,'total'=>$history->total_rows,
            'success'=>$history->success_count,'failed'=>$history->failed_count,'error_message'=>$history->error_message,
            'running'=>in_array($history->status,[ImportHistory::STATUS_QUEUED,ImportHistory::STATUS_PROCESSING],true),
        ]])->header('Cache-Control','private, no-store');
    }

    public function finalize(Request $request, ImportHistory $history, PayrollService $service)
    {
        $this->authorizeHistory($request,$history);
        $request->validate(['reviewed'=>['accepted']]);
        try { $service->finalize($history,$request->user()); }
        catch (ValidationException $e) { throw $e; }
        catch (Throwable $e) {
            app(SafeExceptionLogger::class)->warning('payroll.finalize',$e);
            return response()->json(['success'=>false,'message'=>'Finalisasi belum berhasil. Muat ulang status sebelum mencoba kembali; hasil yang sudah final tetap tersimpan.'],500);
        }
        return response()->json(['success'=>true,'message'=>'Payroll final tersimpan dan terkunci. Ekspor Excel sekarang tersedia.']);
    }

    public function template(Request $request, PayrollWorkbook $workbook)
    {
        $this->authorizeActor($request);
        return response()->streamDownload(function () use ($workbook) {
            $book=$workbook->template();
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        },'template-payroll-gross-up.xlsx',['Cache-Control'=>'private, no-store','Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function download(Request $request, ImportHistory $history, SensitiveFileStorageService $storage)
    {
        $this->authorizeHistory($request,$history);
        abort_unless($history->status===ImportHistory::STATUS_FINALIZED,409,'Payroll harus difinalisasi sebelum ekspor.');
        $path=$storage->resolvePrivatePath($history->summary['export_path'] ?? '',['payroll/']);
        abort_unless($path && hash_equals($history->summary['export_hash'] ?? '',hash_file('sha256',$path)),409,'Arsip ekspor tidak tersedia atau berubah. Hubungi admin.');
        return response()->download($path,'KOMPONEN-GAJI-'.$history->summary['settings']['period'].'-'.$history->id.'.xlsx',['Cache-Control'=>'private, no-store']);
    }

    private function authorizeActor(Request $request): void
    {
        abort_unless($request->user() && $request->user()->hasRole(['Super Admin','HR']) && $request->user()->hasMenuAccess('slip_gaji_admin'),403);
    }

    private function authorizeHistory(Request $request, ImportHistory $history): void
    {
        $this->authorizeActor($request);
        abort_unless($history->import_type===ImportHistory::TYPE_PAYROLL,404);
    }
}
