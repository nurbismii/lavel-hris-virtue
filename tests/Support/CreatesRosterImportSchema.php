<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait CreatesRosterImportSchema
{
    private array $rosterWorkbookPaths = [];

    protected function createRosterImportSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) { $table->string('nik')->primary(); $table->string('no_ktp'); $table->string('nama_karyawan'); $table->string('status_resign')->nullable(); });
        Schema::create('roles', function (Blueprint $table) { $table->id(); $table->string('permission_role'); $table->longText('menu_permissions')->nullable(); });
        Schema::create('users', function (Blueprint $table) { $table->string('id')->primary(); $table->string('name'); $table->string('email')->nullable(); $table->string('password')->nullable(); $table->unsignedBigInteger('role_id')->nullable(); $table->timestamps(); });
        Schema::create('import_histories', function (Blueprint $table) { $table->id(); $table->string('import_id', 36)->nullable(); $table->string('import_type'); $table->string('status'); $table->string('created_by')->nullable(); $table->string('file_path')->nullable(); $table->string('failure_file_path')->nullable(); $table->string('file_checksum')->nullable(); $table->longText('summary')->nullable(); $table->longText('failure_samples')->nullable(); $table->string('confirmed_by')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamp('confirmed_at')->nullable(); $table->timestamps(); });
        Schema::create('import_history_items', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('import_history_id'); $table->string('category'); $table->longText('payload')->nullable(); $table->timestamps(); });
        Schema::create('roster_schedules', function (Blueprint $table) { $table->id(); $table->string('employee_nik'); $table->unsignedSmallInteger('period_year'); $table->unsignedTinyInteger('period_number'); $table->date('off_start'); $table->string('source')->default('import'); $table->timestamps(); $table->unique(['employee_nik', 'off_start']); });
        Schema::create('roster_schedule_histories', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('roster_schedule_id')->nullable(); $table->string('employee_nik'); $table->timestamps(); });
        Schema::create('cuti_roster', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('roster_schedule_id')->nullable(); });
        Schema::create('periode_kerja_roster', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('cuti_roster_id')->nullable(); });
    }

    protected function seedRosterEmployee(string $nik, string $ktp, string $name = 'Nama HRIS', string $status = 'AKTIF'): void
    { 
        \DB::table('employees')->insert(['nik' => $nik, 'no_ktp' => $ktp, 'nama_karyawan' => $name, 'status_resign' => $status]);
    }

    protected function makeRosterWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet(); $sheet = $spreadsheet->getActiveSheet(); $sheet->setTitle('Roster');
        $sheet->fromArray([['No', 'NIK', 'No KTP', 'Nama Karyawan', 2026, 2026], ['', '', '', '', 'I', 'REMARKS']]);
        foreach ($rows as $index => $row) { $line = $index + 3; $sheet->setCellValue('A'.$line, $index + 1); $sheet->setCellValueExplicit('B'.$line, (string) ($row['nik'] ?? ''), DataType::TYPE_STRING); $sheet->setCellValueExplicit('C'.$line, (string) ($row['ktp'] ?? ''), DataType::TYPE_STRING); $sheet->setCellValue('D'.$line, $row['name'] ?? 'Nama Excel'); $sheet->setCellValue('E'.$line, $row['off_start'] ?? '2026-09-10'); $sheet->setCellValue('F'.$line, $row['remark'] ?? null); }
        $path = tempnam(sys_get_temp_dir(), 'roster-preview-') . '.xlsx'; $this->rosterWorkbookPaths[] = $path; (new Xlsx($spreadsheet))->save($path); $spreadsheet->disconnectWorksheets(); return $path;
    }

    protected function cleanRosterImportFixtures(): void { foreach ($this->rosterWorkbookPaths as $path) { if (is_file($path)) { @unlink($path); } } }
}
