<?php
namespace App\Services\Roster;

use App\Models\Employee;
use App\Models\RosterSchedule;
use App\Support\Roster\RosterWorkbookData;
use Illuminate\Support\Facades\DB;

final class RosterScheduleImportValidationService
{
    public function validate(RosterWorkbookData $data): array
    {
        $n = $data->rows->pluck('nik')->map(fn($v)=>(string)$v)->filter()->countBy();
        $niks = $n->keys()->filter(fn($v)=>preg_match('/^\d+$/',$v));
        $employees = Employee::query()->whereIn('nik',$niks)->get(['nik','no_ktp','nama_karyawan','status_resign'])->keyBy(fn($e)=>(string)$e->nik);
        $periodRows=[]; foreach($data->rows as $row) foreach($row['periods'] as $period) $periodRows[] = [$row,$period];
        $pairs = collect($periodRows)->filter(fn($x)=>$x[1]['off_start'])->map(fn($x)=>[$x[0]['nik'],$x[1]['off_start']]);
        $schedules=RosterSchedule::query()->whereIn('employee_nik',$niks)->get(['employee_nik','off_start','source'])->keyBy(fn($s)=>$s->employee_nik.'|'.$s->getRawOriginal('off_start'));
        $errors=[];$warnings=[];$rows=[];$seen=[];
        foreach($periodRows as [$row,$period]) { $item=['row_number'=>$row['row_number'],'nik'=>(string)$row['nik'],'no_ktp'=>(string)$row['no_ktp'],'employee_name'=>$row['employee_name'],'hris_name'=>null,'year'=>$period['year'],'period_number'=>$period['period_number'],'source_column'=>$period['source_column'],'off_start'=>$period['off_start'],'action'=>'create','errors'=>[],'warnings'=>[]];
            $add=function($code,$reason,$warning=false)use(&$item,&$errors,&$warnings){$x=['code'=>$code,'row'=>$item['row_number'],'column'=>$item['source_column'],'reason'=>$reason];$item[$warning?'warnings':'errors'][]=$x;if($warning){$warnings[]=$x;}else{$errors[]=$x;}};
            $nik=$item['nik'];$ktp=$item['no_ktp']; if($nik==='')$add('missing_nik','NIK wajib diisi'); elseif(!preg_match('/^\d+$/',$nik))$add('invalid_nik','NIK harus angka'); elseif(($n[$nik]??0)>1)$add('duplicate_nik','NIK duplikat dalam workbook');
            if(!preg_match('/^\d{16}$/',$ktp)||($row['identity_error']??null))$add('invalid_ktp','Nomor KTP harus 16 digit teks');
            $employee=$employees->get($nik); if($nik!==''&&!$employee)$add('employee_not_found','Karyawan tidak ditemukan'); if($employee){$item['hris_name']=$employee->nama_karyawan; if(preg_match('/^\d{16}$/',$ktp)&&!hash_equals((string)$employee->no_ktp,$ktp))$add('ktp_mismatch','Nomor KTP tidak sesuai'); if($row['employee_name']!==''&&$row['employee_name']!==$employee->nama_karyawan)$add('name_mismatch','Nama berbeda',true); if(strtoupper((string)$employee->status_resign)!=='AKTIF')$add('inactive_employee','Karyawan tidak aktif',true);}
            if(($period['cell_error']??null)||!$period['off_start'])$add('invalid_date','Tanggal roster tidak valid'); $pair=$nik.'|'.$period['off_start']; if($period['off_start']&&isset($seen[$pair]))$add('duplicate_off_start','Tanggal off duplikat');$seen[$pair]=true; $existing=$schedules->get($pair); if($existing&&$existing->source==='manual')$add('manual_conflict','Jadwal manual tidak boleh ditimpa'); elseif($existing)$item['action']='unchanged'; if($item['errors'])$item['action']='blocked'; if($period['raw_remark']&&stripos($period['raw_remark'],'review')!==false)$add('remark_need_review','Remark perlu review',true); $rows[]=$item;
        }
        return ['is_valid'=>!$errors,'summary'=>['total_rows'=>count($rows),'blocker_count'=>count($errors),'warning_count'=>count($warnings)],'rows'=>$rows,'errors'=>$errors,'warnings'=>$warnings];
    }
}
