<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\CvMakerProgressStatus;
use App\Models\Kabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Provinsi;
use App\Services\CvMaker\CvMakerCompareService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class CvMakerCompareServiceTest extends TestCase
{
    public function test_multiple_job_titles_are_applied_as_an_exact_server_side_filter(): void
    {
        $service = new CvMakerCompareService();
        $method = new \ReflectionMethod(CvMakerCompareService::class, 'applyFilters');
        $method->setAccessible(true);
        $request = Request::create('/cv-maker-compare/data', 'GET', [
            'jabatan' => ['Operator Produksi', ' Foreman ', '', ['invalid']],
        ]);

        $query = $method->invoke($service, Employee::query(), $request);

        $this->assertStringContainsString('`employees`.`jabatan` in (?, ?)', $query->toSql());
        $this->assertSame(['Operator Produksi', 'Foreman'], $query->getBindings());
    }

    public function test_progress_status_and_steps_are_applied_as_server_side_filters(): void
    {
        $service = new CvMakerCompareService();
        $method = new \ReflectionMethod(CvMakerCompareService::class, 'applyFilters');
        $method->setAccessible(true);
        $request = Request::create('/cv-maker-compare/data', 'GET', [
            'cv_progress_status' => 'in_progress',
            'cv_progress_step' => ['3', '8', '99', 'invalid'],
        ]);

        $query = $method->invoke($service, Employee::query(), $request);
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('cv_maker_progress_statuses', $sql);
        $this->assertContains('in_progress', [$request->input('cv_progress_status')]);
        $this->assertContains(3, $bindings);
        $this->assertContains(8, $bindings);
        $this->assertNotContains(99, $bindings);
    }

    public function test_update_selection_keeps_only_explicit_fields_and_sections(): void
    {
        $service = new CvMakerCompareService();
        $method = new \ReflectionMethod(CvMakerCompareService::class, 'selectedUpdateChanges');
        $method->setAccessible(true);
        $selection = $method->invoke($service, [
            'changes' => [
                ['key' => 'name'],
                ['key' => 'ktp_number'],
            ],
            'related_changes' => [
                ['key' => 'educations'],
                ['key' => 'experiences'],
            ],
            'organization_changes' => [['key' => 'organization_structure']],
        ], ['name'], ['educations'], false);

        $this->assertSame(['name'], collect($selection['changes'])->pluck('key')->all());
        $this->assertSame(['educations'], collect($selection['related_changes'])->pluck('key')->all());
        $this->assertFalse($selection['has_organization_change']);
    }

    public function test_compare_marks_safe_fields_editable_but_keeps_organization_fields_locked(): void
    {
        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Budi',
            'posisi' => 'Operator',
        ]);
        $comparison = $service->compareEmployee($employee, [
            'profile_id' => 10,
            'full_name' => 'Budi Baru',
            'position' => 'Supervisor',
        ]);
        $identity = collect($comparison['groups']['identity'])->firstWhere('key', 'name');
        $position = collect($comparison['groups']['work'])->firstWhere('key', 'position');

        $this->assertTrue($identity['editable']);
        $this->assertFalse($position['editable']);
    }

    public function test_compare_exposes_single_field_update_only_for_real_mismatch(): void
    {
        $employee = new Employee([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Nama HRIS',
            'jenis_kelamin' => 'Laki-laki',
        ]);

        $comparison = (new CvMakerCompareService())->compareEmployee($employee, [
            'profile_id' => 10,
            'full_name' => 'Nama CV Maker',
            'gender' => 'L',
        ]);
        $identity = collect($comparison['groups']['identity'])->keyBy('key');

        $this->assertTrue($identity['name']['updatable_from_cv']);
        $this->assertFalse($identity['gender']['updatable_from_cv']);
        $this->assertSame('L', $identity['gender']['edit_value']);
    }

    public function test_progress_snapshot_renderer_shows_reminder_badge_and_current_step(): void
    {
        $service = new CvMakerCompareService();
        $status = new CvMakerProgressStatus([
            'cv_profile_id' => 20,
            'current_step' => 8,
            'current_step_label' => 'Dokumen',
            'total_step_count' => 8,
            'needs_reminder' => true,
            'is_complete' => false,
            'last_activity_at' => '2026-07-01 08:00:00',
        ]);

        $html = $service->renderProgressSnapshot($status);

        $this->assertStringContainsString('Perlu Diingatkan', $html);
        $this->assertStringContainsString('Tahap 8/8 - Dokumen', $html);
        $this->assertStringContainsString('Aktivitas terakhir', $html);
    }

    public function test_missing_value_is_skipped_and_not_counted_as_mismatch(): void
    {
        $service = new CvMakerCompareService();

        $result = $service->compareField('No. HP', '08123456789', null, 'phone');

        $this->assertTrue($result['skipped']);
        $this->assertFalse($result['mismatch']);
    }

    public function test_normalized_values_do_not_create_false_mismatch(): void
    {
        $service = new CvMakerCompareService();

        $this->assertFalse($service->compareField('Gender', 'L', 'Laki-laki', 'gender')['mismatch']);
        $this->assertFalse($service->compareField('Status nikah', 'Belum Kawin', 'Belum', 'marital')['mismatch']);
        $this->assertFalse($service->compareField('No. HP', '0812-3456-7890', '+62 812 3456 7890', 'phone')['mismatch']);
        $this->assertFalse($service->compareField('Pendidikan', 'SLTA', 'SMA', 'education_level')['mismatch']);
        $this->assertFalse($service->compareField('Agama', 'ISLAM', 'Islam bilingual label', 'religion')['mismatch']);
    }

    public function test_new_cv_profile_fields_are_compared_and_identity_numbers_are_masked(): void
    {
        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112234',
            'nama_karyawan' => 'Rahmat Hidayat',
            'no_ktp' => '7401010101011234',
            'no_kk' => '7401010101015678',
            'tgl_lahir' => '1991-05-10',
            'jenis_kelamin' => 'L',
            'agama' => 'ISLAM',
            'status_perkawinan' => 'Kawin',
            'nama_ibu_kandung' => 'Siti Aminah',
            'nama_bapak' => 'Dewi Lestari',
            'tanggal_menikah' => '2018-09-15',
            'entry_date' => '2020-01-20',
        ]);

        $comparison = $service->compareEmployee($employee, [
            'profile_id' => 14,
            'full_name' => 'Rahmat Hidayat',
            'ktp_number' => '7401010101011234',
            'family_card_number' => '7401010101015678',
            'birth_date' => '1991-05-10',
            'gender' => 'Laki-laki',
            'religion' => 'Islam bilingual label',
            'marital_status' => 'Kawin',
            'mother_name' => 'Siti Aminah',
            'spouse_name' => 'Dewi Lestari',
            'marriage_date' => '2018-09-15',
            'current_job_entry_date' => '2020-01-20',
        ]);

        $identity = collect($comparison['groups']['identity'])->keyBy('key');
        $family = collect($comparison['groups']['family'])->keyBy('key');
        $work = collect($comparison['groups']['work'])->keyBy('key');

        $this->assertSame('************1234', $identity['ktp_number']['hris']);
        $this->assertSame('************5678', $identity['family_card_number']['cv']);
        $this->assertFalse($identity['religion']['mismatch']);
        $this->assertFalse($family['mother_name']['mismatch']);
        $this->assertFalse($family['spouse_name']['mismatch']);
        $this->assertFalse($family['marriage_date']['mismatch']);
        $this->assertFalse($work['entry_date']['mismatch']);
    }

    public function test_body_and_address_profile_fields_are_compared(): void
    {
        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112235',
            'nama_karyawan' => 'Andi Saputra',
            'tinggi' => '170',
            'berat' => '70',
            'golongan_darah' => 'O',
            'alamat_ktp' => 'Jalan KTP Lama',
            'alamat_domisili' => 'Jalan Domisili',
        ]);

        $comparison = $service->compareEmployee($employee, [
            'profile_id' => 15,
            'full_name' => 'Andi Saputra',
            'height_cm' => '170.0 cm',
            'weight_kg' => '72 kg',
            'blood_type' => 'O',
            'ktp_address' => 'Jalan KTP Baru',
            'address' => 'Jalan Domisili',
        ]);

        $this->assertArrayHasKey('address', $comparison['groups']);

        $identity = collect($comparison['groups']['identity'])->keyBy('key');
        $address = collect($comparison['groups']['address'])->keyBy('key');

        $this->assertArrayHasKey('height', $identity);
        $this->assertArrayHasKey('weight', $identity);
        $this->assertArrayHasKey('blood_type', $identity);
        $this->assertFalse($identity['height']['mismatch']);
        $this->assertTrue($identity['weight']['mismatch']);
        $this->assertFalse($identity['blood_type']['mismatch']);
        $this->assertTrue($address['ktp_address']['mismatch']);
        $this->assertFalse($address['domicile_address']['mismatch']);
    }

    public function test_compare_employee_counts_only_real_mismatches(): void
    {
        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112233',
            'nama_karyawan' => 'Budi Santoso',
            'tgl_lahir' => Carbon::parse('1990-01-02'),
            'jenis_kelamin' => 'L',
            'status_perkawinan' => 'Belum Kawin',
            'no_telp' => '081234567890',
            'alamat_domisili' => 'Morosi',
            'area_kerja' => 'VDNI',
            'jabatan' => 'Operator Produksi',
            'posisi' => 'Operator',
            'pendidikan_terakhir' => 'SMA',
        ]);

        $comparison = $service->compareEmployee($employee, [
            'profile_id' => 10,
            'full_name' => 'Budi Santoso',
            'birth_date' => '1990-01-02',
            'gender' => 'Laki-laki',
            'marital_status' => 'Belum',
            'phone' => '+62 812 3456 7890',
            'address' => null,
            'work_area' => 'VDNI',
            'department' => null,
            'division' => null,
            'job_title' => 'Operator Produksi',
            'position' => 'Supervisor',
            'province_name' => null,
            'regency_name' => null,
            'district_name' => null,
            'village_name' => null,
            'education_level' => 'SLTA',
            'education_institution' => null,
            'education_major' => null,
            'graduation_year' => null,
        ]);

        $this->assertSame(1, $comparison['mismatch_count']);
        $this->assertGreaterThan(1, $comparison['compared_count']);
        $this->assertSame('position', collect($comparison['groups']['work'])->firstWhere('mismatch', true)['key']);
    }

    public function test_compare_position_uses_updatable_employee_value_before_organization_fallback(): void
    {
        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112234',
            'nama_karyawan' => 'Budi Santoso',
            'posisi' => 'Supervisor',
        ]);
        $employee->setRelation('organizationPosition', new \App\Models\OrganizationPosition([
            'position_name' => 'Posisi Master Lama',
        ]));

        $comparison = $service->compareEmployee($employee, [
            'profile_id' => 10,
            'full_name' => 'Budi Santoso',
            'position' => 'Supervisor',
        ]);
        $position = collect($comparison['groups']['work'])->firstWhere('key', 'position');

        $this->assertFalse($position['mismatch']);
        $this->assertSame('Supervisor', $position['hris']);
    }

    public function test_compare_job_title_uses_updatable_employee_value_before_master_fallback(): void
    {
        $employee = new Employee([
            'nik' => 'EMP001',
            'jabatan' => 'Foreman Vitae',
        ]);
        $employee->setRelation('jobTitle', new \App\Models\JobTitle([
            'name' => 'Foreman Master Lama',
        ]));

        $comparison = (new CvMakerCompareService())->compareEmployee($employee, [
            'profile_id' => 10,
            'job_title' => 'Foreman Vitae',
        ]);
        $work = collect($comparison['groups']['work'])->keyBy('key');

        $this->assertFalse($work['job_title']['mismatch']);
    }

    public function test_location_fields_are_compared_when_both_sides_have_values(): void
    {
        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112244',
            'nama_karyawan' => 'Siti Aminah',
        ]);

        $employee->setRelation('provinsi', new Provinsi(['provinsi' => 'Sulawesi Tenggara']));
        $employee->setRelation('kabupaten', new Kabupaten(['kabupaten' => 'Konawe']));
        $employee->setRelation('kecamatan', new Kecamatan(['kecamatan' => 'Morosi']));
        $employee->setRelation('kelurahan', new Kelurahan(['kelurahan' => 'Tondowatu']));

        $comparison = $service->compareEmployee($employee, [
            'profile_id' => 11,
            'province_name' => 'Sulawesi Tenggara',
            'regency_name' => 'Konawe Selatan',
            'district_name' => 'Morosi',
            'village_name' => 'Tondowatu',
        ]);

        $location = collect($comparison['groups']['location'])->keyBy('label');

        $this->assertFalse($location['Provinsi']['mismatch']);
        $this->assertTrue($location['Kabupaten']['mismatch']);
        $this->assertFalse($location['Kecamatan']['mismatch']);
        $this->assertFalse($location['Kelurahan']['mismatch']);
    }

    public function test_location_fields_are_mismatch_when_one_side_is_empty(): void
    {
        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112245',
            'nama_karyawan' => 'Andi Saputra',
        ]);

        $employee->setRelation('provinsi', new Provinsi(['provinsi' => 'Sulawesi Tenggara']));

        $comparison = $service->compareEmployee($employee, [
            'profile_id' => 13,
            'province_name' => null,
            'regency_name' => null,
            'district_name' => null,
            'village_name' => null,
        ]);

        $location = collect($comparison['groups']['location'])->keyBy('label');

        $this->assertFalse($location['Provinsi']['skipped']);
        $this->assertTrue($location['Provinsi']['mismatch']);
    }

    public function test_build_update_preview_only_returns_valid_changed_fields(): void
    {
        config()->set('database.connections.cv_maker.database', 'cv_maker_test');
        config()->set('services.cv_maker.nik_hash_key', 'test-key');

        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112255',
            'nama_karyawan' => 'Budi Santoso',
            'tgl_lahir' => '1990-01-02',
            'jenis_kelamin' => 'L',
            'status_perkawinan' => 'Belum Kawin',
            'no_telp' => '081234567890',
            'alamat_domisili' => 'Morosi',
            'jabatan' => 'Operator Produksi',
            'posisi' => 'Operator',
            'pendidikan_terakhir' => 'SMA',
            'tanggal_kelulusan' => '2019-01-01',
        ]);

        $preview = $service->buildUpdatePreview($employee, [
            'user_id' => 7,
            'profile_id' => 12,
            'status' => 'generated',
            'updated_at' => '2026-06-19 10:00:00',
            'full_name' => 'Budi & Rekan',
            'birth_date' => '1990-01-02',
            'gender' => 'Laki-laki',
            'marital_status' => 'Belum',
            'phone' => '+62 812 3456 7890',
            'address' => 'Morosi Baru',
            'job_title' => 'Supervisor Produksi',
            'position' => 'Supervisor',
            'province_name' => 'Sulawesi Tenggara',
            'education_level' => 'S1',
            'education_institution' => null,
            'education_major' => null,
            'graduation_year' => '2020',
        ]);

        $columns = collect($preview['changes'])->pluck('column')->all();
        $skippedLabels = collect($preview['skipped'])->pluck('label')->all();
        $nameChange = collect($preview['changes'])->firstWhere('column', 'nama_karyawan');
        $graduationYearChange = collect($preview['changes'])->firstWhere('column', 'tanggal_kelulusan');

        $this->assertTrue($preview['success']);
        $this->assertContains('nama_karyawan', $columns);
        $this->assertContains('alamat_domisili', $columns);
        $this->assertContains('jabatan', $columns);
        $this->assertContains('posisi', $columns);
        $this->assertContains('pendidikan_terakhir', $columns);
        $this->assertContains('tanggal_kelulusan', $columns);
        $this->assertNotContains('jenis_kelamin', $columns);
        $this->assertNotContains('no_telp', $columns);
        $this->assertContains('Provinsi', $skippedLabels);
        $this->assertSame('Budi & Rekan', $nameChange['new']);
        $this->assertSame('2019', $graduationYearChange['old']);
        $this->assertSame('2020', $graduationYearChange['new']);
        $this->assertSame('2020-01-01', $graduationYearChange['new_raw']);
    }

    public function test_build_update_preview_includes_new_mapped_fields(): void
    {
        config()->set('database.connections.cv_maker.database', 'cv_maker_test');
        config()->set('services.cv_maker.nik_hash_key', 'test-key');

        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112266',
            'nama_karyawan' => 'Rahmat Hidayat',
            'no_ktp' => '7401010101011234',
            'no_kk' => '7401010101015678',
            'agama' => 'ISLAM',
            'nama_ibu_kandung' => 'Siti',
            'nama_bapak' => 'Dewi',
            'tanggal_menikah' => '2018-09-15',
            'entry_date' => '2020-01-20',
        ]);

        $preview = $service->buildUpdatePreview($employee, [
            'user_id' => 8,
            'profile_id' => 13,
            'status' => 'submitted',
            'updated_at' => '2026-06-24 10:00:00',
            'full_name' => 'Rahmat Hidayat',
            'ktp_number' => '7401010101019999',
            'family_card_number' => '7401010101018888',
            'religion' => 'KRISTEN PROTESTAN',
            'mother_name' => 'Siti Aminah',
            'spouse_name' => 'Dewi Lestari',
            'marriage_date' => '2019-01-15',
            'current_job_entry_date' => '2021-03-01',
        ]);

        $changes = collect($preview['changes'])->keyBy('column');

        $this->assertTrue($preview['success']);
        $this->assertArrayHasKey('no_ktp', $changes);
        $this->assertArrayHasKey('no_kk', $changes);
        $this->assertArrayHasKey('agama', $changes);
        $this->assertArrayHasKey('nama_ibu_kandung', $changes);
        $this->assertArrayHasKey('nama_bapak', $changes);
        $this->assertArrayHasKey('tanggal_menikah', $changes);
        $this->assertArrayHasKey('entry_date', $changes);
        $this->assertSame('************1234', $changes['no_ktp']['old']);
        $this->assertSame('************9999', $changes['no_ktp']['new']);
        $this->assertSame('7401010101019999', $changes['no_ktp']['new_raw']);
    }

    public function test_build_update_preview_includes_body_and_address_fields(): void
    {
        config()->set('database.connections.cv_maker.database', 'cv_maker_test');
        config()->set('services.cv_maker.nik_hash_key', 'test-key');

        $service = new CvMakerCompareService();
        $employee = new Employee([
            'nik' => '2200112267',
            'nama_karyawan' => 'Andi Saputra',
            'tinggi' => '168',
            'berat' => '70',
            'golongan_darah' => 'A',
            'alamat_ktp' => 'Jalan KTP Lama',
            'alamat_domisili' => 'Jalan Domisili Lama',
        ]);

        $preview = $service->buildUpdatePreview($employee, [
            'user_id' => 9,
            'profile_id' => 16,
            'status' => 'submitted',
            'updated_at' => '2026-06-25 10:00:00',
            'height_cm' => '170.0 cm',
            'weight_kg' => '72 kg',
            'blood_type' => 'O+',
            'ktp_address' => 'Jalan KTP Baru',
            'address' => 'Jalan Domisili Baru',
        ]);

        $changes = collect($preview['changes'])->keyBy('column');

        $this->assertTrue($preview['success']);
        $this->assertArrayHasKey('tinggi', $changes);
        $this->assertArrayHasKey('berat', $changes);
        $this->assertArrayHasKey('golongan_darah', $changes);
        $this->assertArrayHasKey('alamat_ktp', $changes);
        $this->assertArrayHasKey('alamat_domisili', $changes);
        $this->assertSame('170', $changes['tinggi']['new_raw']);
        $this->assertSame('72', $changes['berat']['new_raw']);
        $this->assertSame('O+', $changes['golongan_darah']['new_raw']);
    }

    public function test_cv_list_json_strings_are_cleaned_for_vitae_display(): void
    {
        $service = new CvMakerCompareService();
        $method = new \ReflectionMethod(CvMakerCompareService::class, 'splitCvList');
        $method->setAccessible(true);

        $items = $method->invoke($service, '["Administrasi","Komunikasi","Leadership"]');

        $this->assertSame(['Administrasi', 'Komunikasi', 'Leadership'], $items);
    }

    public function test_cv_multiline_list_values_become_clean_bullet_items(): void
    {
        $service = new CvMakerCompareService();
        $method = new \ReflectionMethod(CvMakerCompareService::class, 'splitCvList');
        $method->setAccessible(true);

        $items = $method->invoke($service, "Analisa sistem;\nMembuat alur sistem;\nDeploy aplikasi");

        $this->assertSame(['Analisa sistem', 'Membuat alur sistem', 'Deploy aplikasi'], $items);
    }

    public function test_cv_comma_text_is_not_forced_into_list_items(): void
    {
        $service = new CvMakerCompareService();
        $method = new \ReflectionMethod(CvMakerCompareService::class, 'splitCvList');
        $method->setAccessible(true);

        $items = $method->invoke($service, 'PHP, Laravel, MySQL');

        $this->assertSame(['PHP, Laravel, MySQL'], $items);
    }

    public function test_json_list_values_are_cleaned_for_compare_display(): void
    {
        $service = new CvMakerCompareService();

        $result = $service->compareField('Skill', 'Administrasi', '["Administrasi","Komunikasi"]');

        $this->assertSame('Administrasi, Komunikasi', $result['cv']);
    }
}
