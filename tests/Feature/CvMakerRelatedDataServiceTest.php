<?php

namespace Tests\Feature;

use App\Services\CvMaker\CvMakerRelatedDataService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CvMakerRelatedDataServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchemas();
    }

    public function test_preview_and_sync_replace_only_cv_maker_rows(): void
    {
        DB::table('employee_cv_experiences')->insert([
            $this->baseRow('EMP001', 'manual', 10) + ['position' => 'Manual Role', 'company' => 'Internal', 'sort_order' => 0],
            $this->baseRow('EMP001', 'cv_maker', 11) + ['position' => 'Old Role', 'company' => 'Old Co', 'sort_order' => 0],
        ]);

        $source = ['experiences' => [[
            'id' => 99,
            'position' => 'Developer',
            'company' => 'VDNI',
            'start_month' => '2023-03-01',
            'is_current' => true,
            'sort_order' => 0,
            'updated_at' => '2026-08-12 10:00:00',
        ]]];
        $service = new CvMakerRelatedDataService();
        $preview = $service->preview('EMP001', 7, $source);

        $this->assertSame(['experiences'], collect($preview['changes'])->pluck('key')->all());
        $this->assertSame(1, $preview['changes'][0]['old_count']);
        $this->assertSame(1, $preview['changes'][0]['new_count']);

        $service->sync('EMP001', $preview['changes']);

        $this->assertSame(1, DB::table('employee_cv_experiences')->where('source', 'manual')->count());
        $this->assertSame('Developer', DB::table('employee_cv_experiences')->where('source', 'cv_maker')->value('position'));
        $this->assertSame(99, (int) DB::table('employee_cv_experiences')->where('source', 'cv_maker')->value('source_record_id'));
    }

    public function test_empty_vitae_section_does_not_delete_existing_rows(): void
    {
        DB::table('employee_cv_certifications')->insert(
            $this->baseRow('EMP002', 'cv_maker', 20) + ['name' => 'K3', 'sort_order' => 0]
        );

        $service = new CvMakerRelatedDataService();
        $preview = $service->preview('EMP002', 8, ['certifications' => []]);
        $service->sync('EMP002', $preview['changes']);

        $this->assertSame(1, DB::table('employee_cv_certifications')->where('employee_nik', 'EMP002')->count());
        $this->assertTrue(collect($preview['skipped'])->contains('label', 'Sertifikasi & pelatihan'));
    }

    private function createSchemas(): void
    {
        $definitions = [
            'employee_cv_educations' => ['level', 'institution', 'major', 'graduation_year'],
            'employee_cv_experiences' => ['position', 'company', 'department', 'division', 'start_month', 'end_month', 'is_current', 'responsibilities'],
            'employee_cv_organizations' => ['organization_name', 'role', 'start_year', 'end_year'],
            'employee_cv_certifications' => ['name', 'issuer', 'year', 'valid_until_year', 'is_lifetime', 'type'],
            'employee_cv_languages' => ['language', 'level'],
            'employee_cv_projects' => ['name', 'year'],
        ];

        foreach ($definitions as $tableName => $fields) {
            Schema::create($tableName, function (Blueprint $table) use ($fields) {
                $table->increments('id');
                $table->string('employee_nik');
                $table->string('source');
                $table->unsignedBigInteger('source_record_id')->nullable();
                $table->unsignedBigInteger('cv_profile_id')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                foreach ($fields as $field) {
                    in_array($field, ['is_current', 'is_lifetime'], true)
                        ? $table->boolean($field)->default(false)
                        : $table->text($field)->nullable();
                }
                $table->timestamp('source_updated_at')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function baseRow(string $nik, string $source, int $sourceId): array
    {
        return [
            'employee_nik' => $nik,
            'source' => $source,
            'source_record_id' => $sourceId,
            'cv_profile_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
