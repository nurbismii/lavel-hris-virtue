<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Kabupaten;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Provinsi;
use App\Services\CvMaker\CvMakerCompareService;
use Carbon\Carbon;
use Tests\TestCase;

class CvMakerCompareServiceTest extends TestCase
{
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
}
