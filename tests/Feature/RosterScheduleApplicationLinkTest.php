<?php

namespace Tests\Feature;

use App\Models\Roster;
use App\Models\RosterSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleApplicationLinkTest extends TestCase
{
    use CreatesRosterImportSchema;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createRosterImportSchema();
        $this->extendRosterApplicationSchema();
    }

    protected function tearDown(): void
    {
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_employee_can_open_own_active_schedule_context(): void
    {
        $user = $this->rosterUser('own-open', '000000001');
        $schedule = $this->scheduleFor($user->nik_karyawan);

        $this->actingAs($user)
            ->get(route('roster.create', ['roster_schedule' => $schedule->id]))
            ->assertOk()
            ->assertSee('name="roster_schedule_id" value="' . $schedule->id . '"', false)
            ->assertSee('Jadwal roster terpilih');
    }

    public function test_employee_cannot_open_another_employees_schedule(): void
    {
        $owner = $this->rosterUser('schedule-owner', '000000002');
        $other = $this->rosterUser('schedule-other', '000000003');
        $schedule = $this->scheduleFor($owner->nik_karyawan);

        $this->actingAs($other)
            ->get(route('roster.create', ['roster_schedule' => $schedule->id]))
            ->assertNotFound();
    }

    public function test_inactive_or_past_schedule_cannot_be_selected(): void
    {
        $user = $this->rosterUser('unavailable', '000000004');
        $inactive = $this->scheduleFor($user->nik_karyawan, ['is_active' => false]);
        $past = $this->scheduleFor($user->nik_karyawan, [
            'off_start' => now()->subDay()->toDateString(),
            'off_end' => now()->toDateString(),
        ]);

        $this->actingAs($user)->get(route('roster.create', ['roster_schedule' => $inactive->id]))->assertNotFound();
        $this->actingAs($user)->get(route('roster.create', ['roster_schedule' => $past->id]))->assertNotFound();
    }

    public function test_manually_realized_schedule_cannot_be_opened_or_linked_by_digital_submission(): void
    {
        $user = $this->rosterUser('manual-realized', '000000013');
        $manualActor = $this->rosterUser('manual-actor', '000000014');
        $manualSubmittedAt = now()->subHour()->startOfSecond();
        $schedule = $this->scheduleFor($user->nik_karyawan, [
            'realization_type' => RosterSchedule::REALIZATION_CUTI,
            'manual_submitted_at' => $manualSubmittedAt,
            'manual_submitted_by' => $manualActor->id,
            'manual_reference_number' => 'MANUAL/LOCKED/2026',
            'manual_submission_note' => 'Berkas manual sudah diterima HR.',
        ]);

        $this->actingAs($user)
            ->get(route('roster.create', ['roster_schedule' => $schedule->id]))
            ->assertNotFound();

        $response = $this->submit($user, [
            'roster_schedule_id' => $schedule->id,
            'tipe_rencana' => '2',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('alert.config', function (string $config): bool {
                $alert = json_decode($config, true);

                return ($alert['icon'] ?? null) === 'error'
                    && ($alert['title'] ?? null) === 'Error'
                    && ($alert['text'] ?? null) === 'Pengajuan roster gagal disimpan. Periksa kembali data dan lampiran, lalu coba lagi.'
                    && strpos($config, 'Berkas manual sudah diterima HR.') === false;
            });

        $fresh = $schedule->fresh();
        $this->assertSame(0, Roster::where('roster_schedule_id', $schedule->id)->count());
        $this->assertSame(RosterSchedule::REALIZATION_CUTI, $fresh->realization_type);
        $this->assertSame($manualActor->id, $fresh->manual_submitted_by);
        $this->assertSame($manualSubmittedAt->toDateTimeString(), $fresh->manual_submitted_at->toDateTimeString());
        $this->assertSame('MANUAL/LOCKED/2026', $fresh->manual_reference_number);
        $this->assertSame('Berkas manual sudah diterima HR.', $fresh->manual_submission_note);
    }

    public function test_store_revalidates_current_realization_instead_of_stale_schedule_state(): void
    {
        $user = $this->rosterUser('manual-race', '000000015');
        $manualActor = $this->rosterUser('manual-race-actor', '000000016');
        $schedule = $this->scheduleFor($user->nik_karyawan);
        $stalePendingSchedule = RosterSchedule::findOrFail($schedule->id);
        $manualSubmittedAt = now()->subMinute()->startOfSecond();

        RosterSchedule::whereKey($schedule->id)->update([
            'realization_type' => RosterSchedule::REALIZATION_INSENTIF,
            'manual_submitted_at' => $manualSubmittedAt,
            'manual_submitted_by' => $manualActor->id,
            'manual_reference_number' => 'MANUAL/RACE/2026',
            'manual_submission_note' => 'Realisasi berubah setelah model kandidat dibaca.',
        ]);

        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $stalePendingSchedule->realization_type);

        $this->submit($user, ['roster_schedule_id' => $schedule->id])->assertRedirect();

        $fresh = $schedule->fresh();
        $this->assertSame(0, Roster::where('roster_schedule_id', $schedule->id)->count());
        $this->assertSame(RosterSchedule::REALIZATION_INSENTIF, $fresh->realization_type);
        $this->assertSame($manualActor->id, $fresh->manual_submitted_by);
        $this->assertSame($manualSubmittedAt->toDateTimeString(), $fresh->manual_submitted_at->toDateTimeString());
        $this->assertSame('MANUAL/RACE/2026', $fresh->manual_reference_number);
        $this->assertSame('Realisasi berubah setelah model kandidat dibaca.', $fresh->manual_submission_note);
    }

    public function test_valid_cuti_submission_links_schedule_and_sets_cuti_realization(): void
    {
        $user = $this->rosterUser('cuti', '000000005');
        $schedule = $this->scheduleFor($user->nik_karyawan);

        $this->submit($user, ['roster_schedule_id' => $schedule->id, 'tipe_rencana' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('cuti_roster', ['roster_schedule_id' => $schedule->id, 'nik_karyawan' => $user->nik_karyawan]);
        $this->assertSame(RosterSchedule::REALIZATION_CUTI, $schedule->fresh()->realization_type);
    }

    public function test_valid_insentif_submission_sets_insentif_realization(): void
    {
        $user = $this->rosterUser('insentif', '000000006');
        $schedule = $this->scheduleFor($user->nik_karyawan);

        $this->submit($user, ['roster_schedule_id' => $schedule->id, 'tipe_rencana' => '2'])
            ->assertRedirect();

        $this->assertSame(RosterSchedule::REALIZATION_INSENTIF, $schedule->fresh()->realization_type);
    }

    public function test_tampered_schedule_id_cannot_link_another_employee_schedule(): void
    {
        $user = $this->rosterUser('tamper-user', '000000007');
        $other = $this->rosterUser('tamper-owner', '000000008');
        $foreignSchedule = $this->scheduleFor($other->nik_karyawan);

        $this->submit($user, ['roster_schedule_id' => $foreignSchedule->id])
            ->assertRedirect();

        $this->assertDatabaseMissing('cuti_roster', ['roster_schedule_id' => $foreignSchedule->id]);
        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $foreignSchedule->fresh()->realization_type);
    }

    public function test_pending_or_approved_application_blocks_duplicate_submission(): void
    {
        $user = $this->rosterUser('duplicate', '000000009');
        $pending = $this->scheduleFor($user->nik_karyawan);
        $this->applicationFor($pending, ['status_pengajuan' => 0, 'status_pengajuan_hrd' => 0]);

        $this->submit($user, ['roster_schedule_id' => $pending->id])->assertRedirect();
        $this->assertSame(1, Roster::where('roster_schedule_id', $pending->id)->count());

        $approved = $this->scheduleFor($user->nik_karyawan);
        $this->applicationFor($approved, ['status_pengajuan' => 1, 'status_pengajuan_hrd' => 1]);
        $this->submit($user, ['roster_schedule_id' => $approved->id])->assertRedirect();
        $this->assertSame(1, Roster::where('roster_schedule_id', $approved->id)->count());

    }

    public function test_rejected_application_allows_resubmission_under_existing_workflow_convention(): void
    {
        $user = $this->rosterUser('rejected', '000000012');
        $rejected = $this->scheduleFor($user->nik_karyawan);
        $this->applicationFor($rejected, ['status_pengajuan' => 2, 'status_pengajuan_hrd' => 0]);

        $this->submit($user, ['roster_schedule_id' => $rejected->id])->assertRedirect();

        $this->assertSame(2, Roster::where('roster_schedule_id', $rejected->id)->count());
    }

    public function test_transaction_rollback_does_not_persist_link_or_realization_after_write_failure(): void
    {
        $user = $this->rosterUser('rollback', '000000010');
        $schedule = $this->scheduleFor($user->nik_karyawan);
        DB::unprepared("CREATE TRIGGER fail_roster_period BEFORE INSERT ON periode_kerja_roster BEGIN SELECT RAISE(ABORT, 'period failure'); END;");

        $this->submit($user, ['roster_schedule_id' => $schedule->id])->assertRedirect();

        $this->assertDatabaseMissing('cuti_roster', ['roster_schedule_id' => $schedule->id]);
        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $schedule->fresh()->realization_type);
    }

    public function test_legacy_submission_without_schedule_id_remains_unlinked(): void
    {
        $user = $this->rosterUser('legacy', '000000011');

        $this->submit($user)->assertRedirect();

        $this->assertDatabaseHas('cuti_roster', [
            'nik_karyawan' => $user->nik_karyawan,
            'roster_schedule_id' => null,
        ]);
    }

    private function extendRosterApplicationSchema(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable();
        });
        Schema::table('cuti_roster', function (Blueprint $table): void {
            $table->string('nomor_surat')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->string('email')->nullable();
            $table->string('no_telp')->nullable();
            $table->timestamp('tanggal_pengajuan')->nullable();
            $table->date('tgl_mulai_cuti')->nullable();
            $table->date('tgl_mulai_cuti_berakhir')->nullable();
            $table->date('tgl_mulai_cuti_tahunan')->nullable();
            $table->date('tgl_mulai_cuti_tahunan_berakhir')->nullable();
            $table->date('tgl_mulai_off')->nullable();
            $table->date('tgl_mulai_off_berakhir')->nullable();
            $table->date('tgl_awal_kerja')->nullable();
            $table->date('tgl_akhir_kerja')->nullable();
            $table->date('tgl_keberangkatan')->nullable();
            $table->string('jam_keberangkatan')->nullable();
            $table->string('kota_awal_keberangkatan')->nullable();
            $table->string('kota_tujuan_keberangkatan')->nullable();
            $table->text('catatan_penting_keberangkatan')->nullable();
            $table->date('tgl_kepulangan')->nullable();
            $table->string('jam_kepulangan')->nullable();
            $table->string('kota_awal_kepulangan')->nullable();
            $table->string('kota_tujuan_kepulangan')->nullable();
            $table->text('catatan_penting_kepulangan')->nullable();
            $table->string('file')->nullable();
            $table->unsignedTinyInteger('status_pengajuan')->default(0);
            $table->unsignedTinyInteger('status_pengajuan_hrd')->default(0);
            $table->timestamps();
        });
        Schema::table('periode_kerja_roster', function (Blueprint $table): void {
            $table->date('periode_awal')->nullable();
            $table->date('periode_akhir')->nullable();
            $table->unsignedTinyInteger('tipe_rencana')->nullable();
            $table->text('alasan')->nullable();
            $table->string('satu')->nullable();
            $table->string('dua')->nullable();
            $table->string('tiga')->nullable();
            $table->string('empat')->nullable();
            $table->string('lima')->nullable();
            $table->date('tanggal_satu')->nullable();
            $table->date('tanggal_dua')->nullable();
            $table->date('tanggal_tiga')->nullable();
            $table->date('tanggal_empat')->nullable();
            $table->date('tanggal_lima')->nullable();
            $table->timestamps();
        });
        Schema::create('roster_off_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('nik_karyawan');
            $table->date('tanggal_off');
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
        });
    }

    private function rosterUser(string $id, string $nik): User
    {
        $this->seedRosterEmployee($nik, str_pad($nik, 16, '0', STR_PAD_LEFT), 'Karyawan ' . $nik);
        $roleId = DB::table('roles')->insertGetId([
            'permission_role' => 'Staff Roster',
            'menu_permissions' => json_encode(['roster']),
        ]);

        return User::create([
            'id' => $id,
            'name' => 'Karyawan ' . $nik,
            'email' => $id . '@example.test',
            'email_verified_at' => now(),
            'role_id' => $roleId,
            'nik_karyawan' => $nik,
        ]);
    }

    private function scheduleFor(string $nik, array $overrides = []): RosterSchedule
    {
        static $period = 1;
        $offset = $period++;

        return RosterSchedule::create(array_merge([
            'employee_nik' => $nik,
            'period_year' => (int) now()->year,
            'period_number' => $offset,
            'off_start' => now()->addDays(10 + $offset)->toDateString(),
            'off_end' => now()->addDays(12 + $offset)->toDateString(),
            'realization_type' => RosterSchedule::REALIZATION_PENDING,
            'is_active' => true,
        ], $overrides));
    }

    private function applicationFor(RosterSchedule $schedule, array $status): Roster
    {
        return Roster::create(array_merge([
            'nomor_surat' => 'existing-' . $schedule->id,
            'nik_karyawan' => $schedule->employee_nik,
            'roster_schedule_id' => $schedule->id,
            'status_pengajuan' => 0,
            'status_pengajuan_hrd' => 0,
        ], $status));
    }

    private function submit(User $user, array $overrides = [])
    {
        $start = Carbon::today()->addDays(2)->toDateString();
        $end = Carbon::today()->addDays(6)->toDateString();

        return $this->actingAs($user)->post(route('roster.store'), array_merge([
            'email' => $user->email,
            'no_telp' => '08123456789',
            'periode_awal' => $start,
            'periode_akhir' => $end,
            'tipe_rencana' => '1',
            'hari_1' => 'OFF',
            'hari_2' => 'BEKERJA',
            'hari_3' => 'BEKERJA',
            'hari_4' => 'BEKERJA',
            'hari_5' => 'BEKERJA',
            'tanggal_1' => $start,
            'tanggal_2' => Carbon::parse($start)->addDay()->toDateString(),
            'tanggal_3' => Carbon::parse($start)->addDays(2)->toDateString(),
            'tanggal_4' => Carbon::parse($start)->addDays(3)->toDateString(),
            'tanggal_5' => Carbon::parse($start)->addDays(4)->toDateString(),
            'nik_karyawan' => 'tampered-client-nik',
            'realization_type' => 'tampered-client-realization',
        ], $overrides));
    }
}
