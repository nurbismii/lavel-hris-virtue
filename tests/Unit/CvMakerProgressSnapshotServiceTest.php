<?php

namespace Tests\Unit;

use App\Services\CvMaker\CvMakerProgressSnapshotService;
use Carbon\Carbon;
use Tests\TestCase;

class CvMakerProgressSnapshotServiceTest extends TestCase
{
    public function test_it_returns_first_incomplete_step(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $result = $service->evaluateProgress([
            'profile_id' => 11,
            'status' => 'draft',
            'full_name' => 'Budi Santoso',
            'birth_date' => '1990-01-02',
            'birth_place' => 'Kendari',
            'gender' => 'L',
            'marital_status' => 'Belum Kawin',
            'address' => 'Morosi',
            'phone' => '081234567890',
            'email' => 'budi@example.test',
            'profile_summary' => 'Operator produksi berpengalaman.',
            'technical_skills' => '["Microsoft Excel"]',
            'updated_at' => '2026-07-01 08:00:00',
        ], [
            'educations' => [],
            'experiences' => [],
            'certifications' => [],
            'languages' => [],
            'projects' => [],
            'organizations' => [],
            'documents' => [],
        ], Carbon::parse('2026-07-02 09:00:00'));

        $this->assertFalse($result['is_complete']);
        $this->assertSame(3, $result['current_step']);
        $this->assertSame('education', $result['current_step_key']);
        $this->assertSame('Pendidikan', $result['current_step_label']);
        $this->assertSame(2, $result['completed_step_count']);
        $this->assertContains('personal', $result['completed_steps']);
        $this->assertContains('summary', $result['completed_steps']);
        $this->assertContains('education', $result['missing_steps']);
    }

    public function test_it_marks_progress_complete_when_all_eight_steps_are_complete(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $result = $service->evaluateProgress($this->completeProfile([
            'updated_at' => '2026-07-01 08:00:00',
        ]), $this->completeRelatedRows(), Carbon::parse('2026-07-02 09:00:00'));

        $this->assertTrue($result['is_complete']);
        $this->assertFalse($result['needs_reminder']);
        $this->assertSame(8, $result['current_step']);
        $this->assertSame('documents', $result['current_step_key']);
        $this->assertSame(8, $result['completed_step_count']);
        $this->assertSame([], $result['missing_steps']);
    }

    public function test_it_marks_incomplete_draft_idle_more_than_twenty_four_hours_as_needing_reminder(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $relatedRows = $this->completeRelatedRows();
        $relatedRows['documents'] = [
            ['type' => 'ktp', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
            ['type' => 'family_card', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
        ];

        $result = $service->evaluateProgress($this->completeProfile([
            'status' => 'draft',
            'updated_at' => '2026-07-01 08:00:00',
        ]), $relatedRows, Carbon::parse('2026-07-02 08:00:01'));

        $this->assertFalse($result['is_complete']);
        $this->assertTrue($result['needs_reminder']);
        $this->assertSame(8, $result['current_step']);
        $this->assertSame('documents', $result['current_step_key']);
        $this->assertSame('2026-07-01 08:00:00', $result['last_activity_at']->format('Y-m-d H:i:s'));
        $this->assertSame('Draft CV belum lengkap dan tidak ada aktivitas lebih dari 24 jam.', $result['reminder_reason']);
    }

    public function test_recent_incomplete_draft_does_not_need_reminder(): void
    {
        $service = new CvMakerProgressSnapshotService();

        $result = $service->evaluateProgress($this->completeProfile([
            'profile_summary' => null,
            'updated_at' => '2026-07-02 07:30:00',
        ]), $this->completeRelatedRows(), Carbon::parse('2026-07-02 08:00:00'));

        $this->assertFalse($result['is_complete']);
        $this->assertFalse($result['needs_reminder']);
        $this->assertSame(2, $result['current_step']);
        $this->assertSame('summary', $result['current_step_key']);
    }

    private function completeProfile(array $overrides = []): array
    {
        return array_merge([
            'profile_id' => 20,
            'status' => 'draft',
            'full_name' => 'Siti Aminah',
            'birth_date' => '1992-05-15',
            'birth_place' => 'Kendari',
            'gender' => 'P',
            'marital_status' => 'Belum Kawin',
            'address' => 'Morosi',
            'phone' => '081234567891',
            'email' => 'siti@example.test',
            'profile_summary' => 'Administrasi HR yang teliti.',
            'technical_skills' => '["Microsoft Excel","Administrasi"]',
            'updated_at' => '2026-07-01 08:00:00',
        ], $overrides);
    }

    private function completeRelatedRows(): array
    {
        return [
            'educations' => [
                ['level' => 'SMA SEDERAJAT', 'institution' => 'SMAN 1 Kendari', 'major' => 'IPA', 'graduation_year' => 2010, 'updated_at' => '2026-07-01 08:00:00'],
            ],
            'experiences' => [
                [
                    'position' => 'Admin HR',
                    'company' => 'PT VDNI',
                    'department' => 'HR',
                    'division' => 'People Ops',
                    'start_month' => '2020-01-01',
                    'end_month' => null,
                    'is_current' => 1,
                    'responsibilities' => 'Mengelola administrasi karyawan.',
                    'updated_at' => '2026-07-01 08:00:00',
                ],
            ],
            'certifications' => [],
            'languages' => [],
            'projects' => [],
            'organizations' => [],
            'documents' => [
                ['type' => 'ktp', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
                ['type' => 'family_card', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
                ['type' => 'diploma', 'uploaded_at' => '2026-07-01 08:00:00', 'updated_at' => '2026-07-01 08:00:00'],
            ],
        ];
    }
}
