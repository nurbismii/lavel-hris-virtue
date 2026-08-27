<?php

namespace Tests\Feature;

use App\Models\ImportHistory;
use App\Models\User;
use App\Services\ImportHistory\ImportHistoryService;
use App\Services\Roster\RosterScheduleImportPreviewService;
use App\Services\Roster\RosterScheduleImportValidationService;
use App\Services\Roster\RosterScheduleWorkbookReader;
use App\Support\Roster\RosterWorkbookData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleImportPreviewTest extends TestCase
{
    use CreatesRosterImportSchema;
    private const KTP = '7402243101930001';
    private const NIK = '016090940';

    protected function setUp(): void { parent::setUp(); config()->set('database.default', 'sqlite'); config()->set('database.connections.sqlite', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']); DB::purge('sqlite'); DB::reconnect('sqlite'); Storage::fake('local'); $this->createRosterImportSchema(); }
    protected function tearDown(): void { $this->cleanRosterImportFixtures(); Schema::dropAllTables(); DB::disconnect('sqlite'); parent::tearDown(); }

    public function test_matching_identity_name_warning_and_blockers_are_classified(): void { $this->seedRosterEmployee(self::NIK, self::KTP, 'Nama HRIS'); $result = $this->validate([['nik'=>self::NIK,'ktp'=>self::KTP,'name'=>'Nama Lain']]); $this->assertTrue($result['is_valid']); $this->assertCount(0, $result['errors']); $this->assertSame('name_mismatch', $result['warnings'][0]['code']); }
    public function test_identity_and_schedule_blockers_are_all_or_nothing(): void { $this->seedRosterEmployee(self::NIK, self::KTP); $result=$this->validate([['nik'=>'','ktp'=>self::KTP],['nik'=>self::NIK,'ktp'=>'7402243101930002'],['nik'=>self::NIK,'ktp'=>self::KTP],['nik'=>self::NIK,'ktp'=>self::KTP,'off_start'=>'not a date']]); $this->assertFalse($result['is_valid']); $this->assertContains('missing_nik', array_column($result['errors'],'code')); $this->assertContains('ktp_mismatch', array_column($result['errors'],'code')); $this->assertContains('duplicate_nik', array_column($result['errors'],'code')); }
    public function test_manual_conflict_unchanged_and_inactive_are_classified(): void { $this->seedRosterEmployee(self::NIK,self::KTP); $this->seedRosterEmployee('016090941','7402243101930002','Nonaktif','RESIGN'); DB::table('roster_schedules')->insert(['employee_nik'=>self::NIK,'period_year'=>2026,'period_number'=>1,'off_start'=>'2026-09-10','source'=>'manual']); $blocked=$this->validate([['nik'=>self::NIK,'ktp'=>self::KTP]]); $this->assertContains('manual_conflict',array_column($blocked['errors'],'code')); DB::table('roster_schedules')->where('employee_nik',self::NIK)->update(['source'=>'import']); $unchanged=$this->validate([['nik'=>self::NIK,'ktp'=>self::KTP]]); $this->assertSame('unchanged',$unchanged['rows'][0]['action']); $inactive=$this->validate([['nik'=>'016090941','ktp'=>'7402243101930002']]); $this->assertTrue($inactive['is_valid']); $this->assertContains('inactive_employee',array_column($inactive['warnings'],'code')); }
    public function test_preview_writes_private_failure_export_without_persisting_ktp_and_lifecycle_transitions_are_atomic(): void { $path=$this->makeRosterWorkbook([['nik'=>self::NIK,'ktp'=>self::KTP,'name'=>'=formula']]); Storage::disk('local')->put('private/roster-imports/import-1/source.xlsx',file_get_contents($path)); $actor=$this->actor('user-1'); $history=ImportHistory::create(['import_id'=>'import-1','import_type'=>ImportHistory::TYPE_ROSTER_SCHEDULE,'status'=>'queued','created_by'=>$actor->id,'file_path'=>'roster-imports/import-1/source.xlsx']); $preview=app(RosterScheduleImportPreviewService::class)->preview($history,$actor); $fresh=$history->fresh(); $this->assertSame(ImportHistory::STATUS_VALIDATION_FAILED,$fresh->status); $this->assertStringNotContainsString(self::KTP,json_encode([$fresh->summary,$fresh->failure_samples])); $this->assertTrue(Storage::disk('local')->exists('private/'.$fresh->failure_file_path)); $book=IOFactory::load(Storage::disk('local')->path('private/'.$fresh->failure_file_path)); $this->assertSame(self::KTP,$book->getActiveSheet()->getCell('D2')->getValue()); $this->assertSame("'=formula",$book->getActiveSheet()->getCell('E2')->getValue()); $this->assertFalse(app(ImportHistoryService::class)->markConfirmed($fresh->id,'user-1')); }
    public function test_valid_preview_can_be_confirmed_once_and_expired(): void { $this->seedRosterEmployee(self::NIK,self::KTP); $path=$this->makeRosterWorkbook([['nik'=>self::NIK,'ktp'=>self::KTP]]); Storage::disk('local')->put('private/roster-imports/import-2/source.xlsx',file_get_contents($path)); $actor=$this->actor('user-2'); $history=ImportHistory::create(['import_id'=>'import-2','import_type'=>ImportHistory::TYPE_ROSTER_SCHEDULE,'status'=>'queued','created_by'=>$actor->id,'file_path'=>'roster-imports/import-2/source.xlsx']); app(RosterScheduleImportPreviewService::class)->preview($history,$actor); $service=app(ImportHistoryService::class); $this->assertSame(ImportHistory::STATUS_AWAITING_CONFIRMATION,$history->fresh()->status); $this->assertTrue($service->markConfirmed($history->id,'user-2')); $this->assertFalse($service->markConfirmed($history->id,'user-2')); $service->markExpired($history->id); $this->assertSame(ImportHistory::STATUS_EXPIRED,$history->fresh()->status); }
    private function validate(array $rows): array { $path=$this->makeRosterWorkbook($rows); return app(RosterScheduleImportValidationService::class)->validate(app(RosterScheduleWorkbookReader::class)->read($path)); }
    private function actor(string $id): User { $role=DB::table('roles')->insertGetId(['permission_role'=>'HR','menu_permissions'=>json_encode(['roster_schedule'])]); return User::create(['id'=>$id,'name'=>'HR','role_id'=>$role]); }
}
