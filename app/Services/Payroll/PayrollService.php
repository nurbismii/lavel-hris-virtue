<?php

namespace App\Services\Payroll;

use App\Jobs\ProcessPayrollImport;
use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\ImportHistoryItem;
use App\Models\PayrollEntry;
use App\Models\Perusahaan;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayrollService
{
    public function upload(UploadedFile $file, array $settings, User $actor): ImportHistory
    {
        return $this->enqueue($file, $settings, $actor);
    }

    public function start(array $settings, User $actor, PayrollInputs $inputs): ImportHistory
    {
        $cutoff = $inputs->cutoff($settings['period']);
        if ($settings['pay_date'] < $cutoff['end_date']) {
            throw ValidationException::withMessages(['pay_date'=>'Tanggal gajian harus pada atau setelah akhir cut-off.']);
        }
        return $this->enqueue(null, $settings + $cutoff + ['income_source'=>'hris'], $actor);
    }

    private function enqueue(?UploadedFile $file, array $settings, User $actor): ImportHistory
    {
        $this->authorize($actor);
        if (!Schema::hasTable('payroll_entries') || !Schema::hasColumn('import_histories','payroll_upload_key') || (!$file && !Schema::hasTable('payroll_inputs'))) {
            $this->invalid('Penyimpanan arsip payroll belum disiapkan. Minta admin menerapkan pembaruan database terlebih dahulu.');
        }
        if (in_array(config('queue.connections.'.config('payroll.connection').'.driver'), [null,'sync','null'], true)) {
            $this->invalid('Antrean payroll belum aktif. Minta admin menyiapkan koneksi antrean dan worker.');
        }
        $settings['employer_id']=(int) $settings['employer_id'];
        $settings['employer_name']=Perusahaan::findOrFail($settings['employer_id'])->nama_perusahaan;
        $settings['rules_version']=config('payroll.rules_version');
        $settings['skema_pajak']='gross_up';
        if (($settings['income_source'] ?? 'excel') === 'hris') {
            app(PayrollHrisSource::class)->validatePeriod($settings);
        }
        if (!$file) {
            if (!Schema::hasTable('salary_components')) { $this->invalid('Master komponen gaji belum disiapkan. Minta admin menerapkan pembaruan database.'); }
            $settings['component_version'] = app(SalaryComponentService::class)->version();
        }
        if (!$file) { $settings['input_version'] = app(PayrollInputs::class)->version($settings['employer_id'], $settings['period']); }
        $key=hash('sha256', ($file ? hash_file('sha256',$file->getRealPath()) : ImportHistory::SOURCE_HRIS).json_encode($settings));
        $existing=ImportHistory::where('payroll_upload_key',$key)->first();
        if ($existing && $existing->status===ImportHistory::STATUS_AWAITING_CONFIRMATION && $existing->expires_at->isPast()) {
            $existing->update(['status'=>ImportHistory::STATUS_EXPIRED,'payroll_upload_key'=>null]);
            $existing=null;
        }
        if ($existing) { return $existing; }
        $id=(string) Str::uuid();
        $storage=app(SensitiveFileStorageService::class);
        $path=$file ? $storage->storeUploadedFileAs($file,'payroll/'.$id,'source.xlsx') : null;
        $history=null;
        try {
            $history=ImportHistory::firstOrCreate(['payroll_upload_key'=>$key], [
                'import_id'=>$id,'import_type'=>ImportHistory::TYPE_PAYROLL,'module'=>'payroll',
                'source'=>$file ? ImportHistory::SOURCE_EXCEL : ImportHistory::SOURCE_HRIS,'status'=>ImportHistory::STATUS_QUEUED,
                'file_name'=>$file ? 'komponen-payroll.xlsx' : null,'file_path'=>$path,'disk'=>'local',
                'file_size'=>$file?->getSize(),'created_by'=>(string) $actor->id,
                'expires_at'=>now()->addDay(),'summary'=>['settings'=>$settings],
            ]);
            if ($history->wasRecentlyCreated) { ProcessPayrollImport::dispatch($history->id); }
            elseif ($path) { $storage->deletePrivate($path,['payroll/']); }
        } catch (Throwable $e) {
            if ($path) { $storage->deletePrivate($path,['payroll/']); }
            if ($history && $history->wasRecentlyCreated) {
                $history->update(['status'=>ImportHistory::STATUS_FAILED,'payroll_upload_key'=>null,'finished_at'=>now(),'error_message'=>'Payroll gagal masuk antrean. Minta admin memeriksa worker lalu coba lagi.']);
            }
            throw $e;
        }
        $this->audit($actor,$history,$file ? 'uploaded' : 'requested');
        return $history;
    }

    public function preview(ImportHistory $history): void
    {
        $actor=User::find($history->created_by);
        $this->authorize($actor);
        $settings=$history->summary['settings'];
        unset($settings['tenure_in_fixed']);
        if ($settings['rules_version']!==config('payroll.rules_version')) { $this->invalid('Versi aturan berubah. Jalankan perhitungan baru dengan aturan terbaru.'); }
        $native = $history->source === ImportHistory::SOURCE_HRIS;
        if ($native && ($settings['component_version'] ?? null) !== app(SalaryComponentService::class)->version()) {
            $this->invalid('Master atau aturan sumber komponen gaji berubah. Jalankan perhitungan baru.');
        }
        $storage=app(SensitiveFileStorageService::class);
        $path=$native ? null : $storage->resolvePrivatePath($history->file_path,['payroll/']);
        if (!$native && !$path) { $this->invalid('File sumber tidak tersedia. Upload kembali.'); }
        $history->items()->delete();
        $history->update(['total_rows'=>0,'success_count'=>0,'failed_count'=>0]);
        $workbook = app(PayrollWorkbook::class);
        $source = ($settings['income_source'] ?? 'excel') === 'hris' ? app(PayrollHrisSource::class) : null;
        $minutes = $source && !$native ? $workbook->verifiedOvertime($path) : [];
        $separate = 0;
        $batches = $native ? app(PayrollInputs::class)->rows($settings, $separate) : $workbook->read($path);
        $totals=array_fill_keys(['cash_income','overtime','tax_allowance','pph21','employee_bpjs','employer_bpjs','take_home','employer_cost'],0);
        if ($source) { $totals += array_fill_keys(['basic_salary','other_fixed_allowance','meal','tenure','roster_incentive','bonus'], 0); }
        $ledgerVersion=(int) PayrollEntry::where('employer_id',$settings['employer_id'])->max('id');
        $processed=0; $failed=0;
        foreach ($batches as $rows) {
            $niks=array_map(function ($row) { return trim((string) $row['columns']['A']); },$rows);
            $employees=Employee::with($source ? ['departemen', 'divisi', 'workPattern'] : ['departemen'])->whereIn('nik',$niks)->get()->keyBy('nik');
            $ledgers=$this->ledgers($niks,$settings);
            $data = $source ? $source->load($employees->filter(fn (Employee $e) => (int) $e->departemen?->perusahaan_id === $settings['employer_id'])->pluck('nik')->all(), $settings) : [];
            $items=[];
            foreach ($rows as $number=>$row) {
                $nik=trim((string) $row['columns']['A']);
                $employee=$employees->get($nik);
                $payload=[];
                try {
                    if (!$employee || !$employee->departemen || (int) $employee->departemen->perusahaan_id!==$settings['employer_id']) {
                        $this->invalid('NIK tidak ditemukan pada perusahaan yang dipilih.');
                    }
                    if ($row['duplicate']) { $this->invalid('NIK berulang dalam file. Gabungkan semua penghasilan satu karyawan dalam satu masa.'); }
                    if ($native) {
                        $this->prepareNativeRow($row, $employee, $settings, $ledgers->get($nik, collect()), $data);
                        $rowMinutes = $row['minutes'];
                        $origin = $source->apply($row['columns'], $employee, $settings, $data, $rowMinutes, $row['manual_overtime']);
                        app(SalaryComponentService::class)->assertFixedBreakdown($row['custom_components'], $origin['other_fixed_allowance']);
                        if ($rowMinutes) { $this->invalid('Data perintah lembur berubah. Periksa menit yang disahkan pada isian karyawan.'); }
                        if ($row['manual_overtime'] !== null) { $origin['overtime_note'] = $row['overtime_note']; }
                        if ($row['recurring_components']) { $origin['recurring_components'] = $row['recurring_components']; }
                    } else {
                        $origin = $source ? $source->apply($row['columns'], $employee, $settings, $data, $minutes) : null;
                    }
                    $payload=$this->calculateRow($row,$employee,$settings,$ledgers->get($nik,collect()));
                    if (!empty($row['custom_components'])) {
                        $payload['custom_components'] = array_values(array_map(fn ($component) => array_merge($component, [
                            'included_in_wage'=>$component['type'] === 'income' && $component['fixed'],
                        ]), $row['custom_components']));
                    }
                    foreach ($totals as $field=>$unused) {
                        $totals[$field] += match ($field) {
                            'cash_income' => $payload['input']['cash_income'],
                            'overtime' => $payload['columns']['P'],
                            'basic_salary', 'other_fixed_allowance' => $origin[$field],
                            'meal' => $payload['columns']['K'],
                            'tenure' => $payload['columns']['N'],
                            'roster_incentive' => $payload['columns']['X'],
                            'bonus' => $payload['columns']['W'],
                            default => $payload['result'][$field],
                        };
                    }
                    if ($origin !== null) { $payload['hris'] = $origin; }
                    $message='Valid; siap ditinjau HR.'; $category=ImportHistoryItem::CATEGORY_UPDATED;
                } catch (ValidationException $e) {
                    $message=Str::limit(implode(' ',array_slice(collect($e->errors())->flatten()->all(),0,3)),500,'');
                    $category=ImportHistoryItem::CATEGORY_FAILED; $failed++;
                }
                $items[]=['import_history_id'=>$history->id,'row_number'=>$number,'nik'=>Str::limit($nik,100,''),
                    'employee_name'=>$employee ? $employee->nama_karyawan : null,'category'=>$category,'message'=>$message,
                    'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()];
                $processed++;
            }
            ImportHistoryItem::insert($items);
            $history->update(['total_rows'=>$processed,'success_count'=>$processed-$failed,'updated_count'=>$processed-$failed,'failed_count'=>$failed]);
        }
        if (!$processed) { $this->invalid($native ? 'Tidak ada karyawan yang dapat dihitung pada perusahaan dan periode ini.' : 'File belum berisi karyawan. Isi template lalu upload kembali.'); }
        if (!$failed && $minutes) { $this->invalid('Sheet LEMBUR memuat ID perintah yang tidak diterima, di luar cut-off, atau bukan milik karyawan pada file.'); }
        $summary=['settings'=>$settings,'totals'=>$totals,
            'ledger_version'=>$ledgerVersion];
        if ($native) { $summary['separate_count'] = $separate; }
        if (!$failed) {
            $summary['export_path']='payroll/'.$history->import_id.'/result.xlsx';
            $destination='private/'.$summary['export_path'];
            $disk = Storage::disk('local');
            $disk->makeDirectory(dirname($destination));
            app(PayrollExportService::class)->write($history,$summary,$disk->path($destination));
            $summary['export_hash']=hash_file('sha256',$disk->path($destination));
        }
        $history->update(['summary'=>$summary,'status'=>$failed ? ImportHistory::STATUS_VALIDATION_FAILED : ImportHistory::STATUS_AWAITING_CONFIRMATION,
            'error_message'=>$failed ? ($native ? 'Finalisasi ditahan. Lengkapi data karyawan yang gagal lalu hitung ulang.' : 'Finalisasi ditahan. Perbaiki seluruh baris gagal dan upload ulang file lengkap.') : null,
            'finished_at'=>now(),'expires_at'=>now()->addDay(),'payroll_upload_key'=>$failed || $source ? null : $history->payroll_upload_key]);
        if ($path) { $storage->deletePrivate($history->file_path,['payroll/']); }
    }

    private function prepareNativeRow(array &$row, Employee $employee, array $settings, $ledger, array $data): void
    {
        if (!$row['parameters']) { $this->invalid('Profil pajak dan BPJS belum dikonfirmasi. Buka isian karyawan.'); }
        $salary = $data['salaries']['start:'.$employee->nik] ?? null;
        if ($salary && (int) $row['profile_salary_id'] !== (int) $salary->id) {
            $this->invalid('Riwayat upah berubah. Periksa dasar BPJS dan konfirmasi ulang profil karyawan.');
        }
        $row['columns'] += ['C'=>$employee->departemen?->departemen, 'D'=>$employee->divisi?->nama_divisi, 'E'=>$employee->posisi ?: $employee->jabatan];
        $params = &$row['parameters'];
        $taxMonth = (int) substr($settings['tax_period'], 5);
        if ($taxMonth !== 12 && !(int) $params['masa_terakhir']) { return; }
        $start = (int) $params['bulan_mulai_pajak'];
        $year = substr($settings['tax_period'], 0, 4);
        $previous = $ledger->whereBetween('tax_period', [sprintf('%s-%02d', $year, $start), $settings['tax_period']])->where('tax_period', '<', $settings['tax_period']);
        $params['ytd_bulan'] = $taxMonth - $start;
        if ($previous->count() === $params['ytd_bulan']) {
            $params += $this->ledgerBalances($previous);
        }
    }

    private function calculateRow(array $row, Employee $employee, array $settings, $ledger): array
    {
        $columns=$row['columns']; $p=$row['parameters'];
        if (!$p) { $this->invalid('NIK belum memiliki baris pada sheet PARAMETER.'); }
        if (!is_string($columns['A']) || strlen(trim($columns['A']))>100) { $this->invalid('NIK wajib berupa teks, maksimal 100 karakter, agar nol di depan tidak hilang.'); }
        foreach (array_merge(PayrollWorkbook::INCOME_COLUMNS,['AA','AB','AC']) as $key) {
            if ($key==='J' && ($columns[$key]===null || $columns[$key]==='')) { $this->invalid('SALARY RECEIVED wajib diisi, termasuk bila nilainya 0.'); }
            $columns[$key]=$this->amount($columns[$key] ?? 0,$key);
        }
        if ($columns['Z']) { $this->invalid('Pesangon/kompensasi/uang pisah memerlukan perlakuan pajak terpisah. Pisahkan pembayaran kolom Z dari payroll ini.'); }
        foreach (['AH'=>'period','AJ'=>'start_date','AK'=>'end_date','AL'=>'pay_date'] as $column=>$field) {
            if ($column!=='AH' && is_numeric($columns[$column] ?? null)) {
                $columns[$column]=$this->excelDate($columns[$column]);
            }
            if (filled($columns[$column] ?? null) && (string) $columns[$column]!==$settings[$field]) { $this->invalid('Kolom '.$column.' berbeda dari periode/tanggal pada form. Gunakan teks sesuai template atau kosongkan.'); }
            $columns[$column]=$settings[$field];
        }
        if (filled($columns['G'] ?? null)) {
            $columns['G']=is_numeric($columns['G']) ? $this->excelDate($columns['G']) : trim((string) $columns['G']);
            if ($columns['G']==='0000-00-00') { $columns['G']=''; }
            elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$columns['G']) || !checkdate((int) substr($columns['G'],5,2),(int) substr($columns['G'],8,2),(int) substr($columns['G'],0,4))) {
                $this->invalid('Tanggal akhir SP harus YYYY-MM-DD atau sel tanggal Excel yang valid.');
            }
        }
        foreach (['B','AF'] as $column) {
            if (filled($columns[$column] ?? null) && !is_string($columns[$column])) { $this->invalid('KTP dan rekening harus teks agar tidak kehilangan digit.'); }
        }
        $columns['B']=filled($columns['B'] ?? null) ? trim($columns['B']) : (string) $employee->no_ktp;
        if (!preg_match('/^\d{16}$/',$columns['B'])) { $this->invalid('NIK KTP harus 16 digit. Periksa identitas karyawan.'); }
        if (filled($employee->no_ktp) && $columns['B']!==trim((string) $employee->no_ktp)) { $this->invalid('NIK KTP pada file berbeda dari data karyawan. Periksa identitas sebelum payroll difinalisasi.'); }
        $columns['AF']=filled($columns['AF'] ?? null) ? trim($columns['AF']) : (string) $employee->no_rekening;
        $columns['AG']=filled($columns['AG'] ?? null) ? trim($columns['AG']) : (string) $employee->nama_bank;
        foreach (['H','I'] as $key) {
            if (filled($columns[$key] ?? null) && (!is_numeric($columns[$key]) || (float) $columns[$key]<0 || (float) $columns[$key]>1000)) { $this->invalid('Hari kerja/jam mesin harus angka 0 sampai 1000.'); }
            $columns[$key]=(float) ($columns[$key] ?? 0);
        }
        foreach ($columns as $value) {
            if (is_string($value) && (strlen($value)>500 || preg_match('/^[=+@\-\t\r]/u',$value))) { $this->invalid('Teks kolom tidak valid atau menyerupai formula. Perbaiki menjadi teks biasa.'); }
        }
        $cash=array_sum(array_intersect_key($columns,array_flip(PayrollWorkbook::INCOME_COLUMNS))) - $columns['AA'] - $columns['AB'];
        if ($cash<0) { $this->invalid('Potongan unpaid/alpa melebihi penghasilan.'); }
        $customIncome = 0; $customDeductions = 0;
        foreach ($row['custom_components'] ?? [] as $component) {
            $amount = $this->amount($component['amount'], 'Komponen tambahan');
            if ($component['type'] === 'income') {
                if (!$component['fixed']) { $customIncome += $amount; }
            } else { $customDeductions += $amount; }
        }
        $input=['cash_income'=>$cash + $customIncome,'cash_deductions'=>$columns['AC'] + $customDeductions,'skema_pajak'=>'gross_up'];
        foreach (['ptkp'=>'ptkp','upah_bpjs_tk'=>'wage_tk','upah_bpjs_kes'=>'wage_health','upah_minimum'=>'minimum_wage','jkk_persen'=>'jkk_persen','jht_aktif'=>'jht_active','jp_aktif'=>'jp_active','kes_aktif'=>'health_active','jkk_jkm_aktif'=>'risk_active','keluarga_tambahan'=>'extra_family','masa_terakhir'=>'last_period','bulan_mulai_pajak'=>'start_month','ytd_bulan'=>'ytd_months','ytd_bruto'=>'ytd_gross','ytd_iuran'=>'ytd_contributions','ytd_zakat'=>'ytd_zakat','ytd_pph21'=>'ytd_tax','natura'=>'natura','iuran_pensiun_lain'=>'other_pension','zakat'=>'zakat','subjek_pajak'=>'subjek_pajak','fasilitas'=>'fasilitas','nik_npwp_valid'=>'nik_npwp_valid'] as $key=>$field) {
            $input[$field]=is_string($p[$key] ?? null) ? trim($p[$key]) : ($p[$key] ?? null);
        }
        if ($employee->tgl_resign && $employee->tgl_resign->format('Y-m')<=$settings['tax_period'] && !(int) $input['last_period']) { $this->invalid('Karyawan sudah berhenti; tandai masa terakhir dan isi saldo YTD untuk rekonsiliasi.'); }
        $this->checkLedger($settings,$input,$ledger);
        $result=app(PayrollCalculator::class)->calculate($input,$settings['tax_period']);
        $columns['R']=$result['bpjs']['employee_jht']; $columns['S']=$result['bpjs']['employee_jp'];
        $columns['T']=$result['bpjs']['employee_health']+$result['bpjs']['extra_family'];
        $columns['AD']=$result['pph21'];
        $columns['AE']=$result['take_home']; $columns['AM']=$result['tax_allowance'];
        return ['columns'=>$columns,'parameters'=>$p,'input'=>$input,'result'=>$result,'employee_name'=>$employee->nama_karyawan];
    }

    private function ledgers(array $niks, array $settings)
    {
        return PayrollEntry::where('employer_id',$settings['employer_id'])->whereIn('employee_nik',$niks)
            ->where(function ($q) use ($settings) { $q->where('period',$settings['period'])->orWhere('tax_period','>=',substr($settings['tax_period'],0,4).'-01'); })
            ->orderBy('tax_period')->get()->groupBy('employee_nik');
    }

    private function checkLedger(array $settings, array $input, $entries): void
    {
        if ($entries->contains(function ($entry) use ($settings) { return $entry->period===$settings['period'] || $entry->tax_period>=$settings['tax_period']; })) {
            $this->invalid('Payroll karyawan untuk periode ini atau masa pajak setelahnya sudah final. Finalisasi harus berurutan dan satu per masa pajak.');
        }
        if ($entries->contains(function ($entry) { return !empty($entry->snapshot['result']['last_period']); })) {
            $this->invalid('Sudah ada masa terakhir pada pemberi kerja ini. Rehire atau pembayaran setelah berhenti memerlukan review terpisah.');
        }
        if (substr($settings['tax_period'],5)!=='12' && !(int) ($input['last_period'] ?? 0)) { return; }
        $previous=$entries->filter(function ($entry) use ($settings) { return $entry->tax_period >= substr($settings['tax_period'],0,4).'-01' && $entry->tax_period < $settings['tax_period']; });
        if ($previous->isEmpty()) { return; }
        $known=$this->ledgerBalances($previous);
        foreach (['ytd_bruto'=>'ytd_gross', 'ytd_iuran'=>'ytd_contributions', 'ytd_zakat'=>'ytd_zakat', 'ytd_pph21'=>'ytd_tax'] as $param=>$key) {
            $value = $known[$param];
            if (!isset($input[$key]) || (int) $input[$key]<$value || ($previous->count()==(int) ($input['ytd_months'] ?? -1) && (int) $input[$key]!==$value)) {
                $this->invalid('Saldo YTD tidak sesuai arsip payroll final. Periksa kembali saldo pada pemberi kerja ini.');
            }
        }
    }

    private function ledgerBalances($previous): array
    {
        $known=['ytd_bruto'=>0,'ytd_iuran'=>0,'ytd_zakat'=>0,'ytd_pph21'=>0];
        foreach ($previous as $entry) {
            $snapshot=$entry->snapshot;
            if ($snapshot['result']['last_period']) { $this->invalid('Sudah ada rekonsiliasi masa terakhir dalam tahun ini. Rehire/koreksi memerlukan review terpisah.'); }
            $known['ytd_bruto']+=$snapshot['result']['tax_gross'];
            $known['ytd_iuran']+=$snapshot['result']['deductible_contributions'];
            $known['ytd_zakat']+=(int) $snapshot['input']['zakat'];
            $known['ytd_pph21']+=$snapshot['result']['pph21'];
        }
        return $known;
    }

    public function finalize(ImportHistory $history, User $actor): ImportHistory
    {
        $this->authorize($actor);
        if ($history->import_type!==ImportHistory::TYPE_PAYROLL) { $this->invalid('Riwayat ini bukan perhitungan payroll.'); }
        if ($history->status===ImportHistory::STATUS_FINALIZED) { return $history; }
        $path=app(SensitiveFileStorageService::class)->resolvePrivatePath($history->summary['export_path'] ?? '',['payroll/']);
        if (!$path || !hash_equals($history->summary['export_hash'] ?? '',hash_file('sha256',$path))) { $this->invalid('Arsip ekspor tidak tersedia atau berubah. Hitung dan tinjau kembali payroll.'); }
        return DB::transaction(function () use ($history,$actor) {
            $locked=ImportHistory::whereKey($history->id)->lockForUpdate()->firstOrFail();
            if ($locked->status===ImportHistory::STATUS_FINALIZED) { return $locked; }
            if ($locked->status!==ImportHistory::STATUS_AWAITING_CONFIRMATION || !$locked->expires_at || $locked->expires_at->isPast()
                || !$locked->total_rows || $locked->failed_count || $locked->success_count!==$locked->total_rows
                || $locked->summary['settings']['rules_version']!==config('payroll.rules_version')) {
                $this->invalid('Payroll belum valid, kedaluwarsa, atau aturan berubah. Hitung dan tinjau kembali.');
            }
            $settings=$locked->summary['settings'];
            if ($locked->source === ImportHistory::SOURCE_HRIS && ($settings['component_version'] ?? null) !== app(SalaryComponentService::class)->version(true)) {
                $this->invalid('Master atau aturan sumber komponen gaji berubah. Hitung ulang payroll sebelum finalisasi.');
            }
            if (($settings['income_source'] ?? 'excel') === 'hris' && !($settings['tenure_in_fixed'] ?? true)) {
                $this->invalid('Pratinjau ini menambahkan tunjangan masa kerja ke tunjangan tetap. Upload ulang untuk memisahkannya sesuai aturan perusahaan.');
            }
            Perusahaan::whereKey($settings['employer_id'])->lockForUpdate()->firstOrFail();
            if ($locked->source === ImportHistory::SOURCE_HRIS
                && app(PayrollInputs::class)->version($settings['employer_id'], $settings['period']) !== $settings['input_version']) {
                $this->invalid('Isian payroll berubah setelah pratinjau. Hitung ulang sebelum finalisasi.');
            }
            if ((int) PayrollEntry::where('employer_id',$settings['employer_id'])->max('id')!==$locked->summary['ledger_version']) { $this->invalid('Arsip payroll perusahaan berubah setelah pratinjau. Hitung ulang agar saldo pajak diperiksa kembali.'); }
            $count=0;
            $locked->items()->orderBy('id')->chunkById(200,function ($items) use ($settings,$locked,$actor,&$count) {
                $validNiks=Employee::whereIn('nik',$items->pluck('nik'))->whereHas('departemen',function ($q) use ($settings) { $q->where('perusahaan_id',$settings['employer_id']); })->orderBy('nik')->lockForUpdate()->pluck('nik')->all();
                $salaryDates = [];
                foreach ($items as $item) {
                    if (isset($item->payload['hris'])) {
                        $salaryDates['start:'.$item->nik] = ['nik'=>$item->nik, 'berlaku_mulai'=>$settings['period'].'-01'];
                        $salaryDates['end:'.$item->nik] = ['nik'=>$item->nik, 'berlaku_mulai'=>\Carbon\CarbonImmutable::parse($settings['period'].'-01')->endOfMonth()->toDateString()];
                    }
                }
                $salaries = app(\App\Services\Salary\EmployeeSalaryService::class)->forRows($salaryDates, true);
                $ledgers=$this->ledgers($items->pluck('nik')->all(),$settings);
                $inserts=[];
                foreach ($items as $item) {
                    if ($item->category!==ImportHistoryItem::CATEGORY_UPDATED || !in_array($item->nik,$validNiks,true) || empty($item->payload['result'])) { $this->invalid('Data karyawan atau hasil perhitungan berubah. Hitung ulang.'); }
                    if (isset($item->payload['hris']) && ((int) ($salaries['start:'.$item->nik]?->id ?? 0) !== (int) $item->payload['hris']['salary_history_id']
                        || (int) ($salaries['end:'.$item->nik]?->id ?? 0) !== (int) $item->payload['hris']['salary_history_id'])) {
                        $this->invalid('Riwayat Upah berubah setelah pratinjau. Hitung ulang agar tunjangan tetap tidak tercatat ganda.');
                    }
                    $this->checkLedger($settings,$item->payload['input'],$ledgers->get($item->nik,collect()));
                    $snapshot=json_encode($item->payload,JSON_UNESCAPED_UNICODE);
                    $inserts[]=['import_history_id'=>$locked->id,'employer_id'=>$settings['employer_id'],'employee_nik'=>$item->nik,
                        'period'=>$settings['period'],'tax_period'=>$settings['tax_period'],'snapshot'=>$snapshot,'snapshot_hash'=>hash('sha256',$snapshot),
                        'finalized_by'=>(string) $actor->id,'finalized_at'=>now()];
                }
                PayrollEntry::insert($inserts); $count+=count($inserts);
            });
            if ($count!==$locked->total_rows) { $this->invalid('Jumlah baris arsip tidak sesuai. Hitung ulang.'); }
            $locked->update(['status'=>ImportHistory::STATUS_FINALIZED,'confirmed_by'=>(string) $actor->id,'confirmed_at'=>now()]);
            $this->audit($actor,$locked,'finalized');
            return $locked;
        },3);
    }

    private function amount($value, string $field): int
    {
        if ($value===null || $value==='') { return 0; }
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value<0 || (float) $value>100000000000 || floor((float) $value)!=(float) $value) {
            $this->invalid($field.' harus berupa rupiah bulat, tidak negatif, tanpa pemisah ribuan.');
        }
        return (int) $value;
    }

    private function excelDate($value): string
    {
        if (!is_finite((float) $value) || $value<1 || $value>100000 || floor((float) $value)!=(float) $value) {
            $this->invalid('Tanggal Excel tidak valid. Gunakan tanggal tanpa komponen waktu.');
        }
        return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
    }

    private function authorize(?User $actor): void
    {
        if (!$actor || !$actor->hasRole(['Super Admin','HR']) || !$actor->hasMenuAccess('slip_gaji_admin')) { $this->invalid('Akses HR/payroll tidak berlaku. Hubungi admin.'); }
    }

    private function audit(User $actor, ImportHistory $history, string $action): void
    {
        app(AuditTrailService::class)->record(['event'=>'payroll.'.$action,'module'=>'payroll','actor'=>$actor,
            'reference_table'=>'import_histories','reference_id'=>(string) $history->id,'metadata'=>['rows'=>$history->total_rows,'period'=>$history->summary['settings']['period']]]);
    }

    private function invalid(string $message): void { throw ValidationException::withMessages(['payroll'=>$message]); }
}
