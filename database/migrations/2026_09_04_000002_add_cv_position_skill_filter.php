<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class AddCvPositionSkillFilter extends Migration
{
    public function up()
    {
        $mappings = $this->loadPositionSkillCategories();

        Schema::create('cv_maker_position_skill_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('position_name', 255);
            $table->string('normalized_position', 255)->unique();
            $table->string('skill_category', 20);
            $table->timestamps();

            $table->index(['skill_category', 'normalized_position'], 'cv_position_skill_category_idx');
        });

        Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->string('cv_position', 255)->nullable()->after('cv_job_title');
            $table->string('cv_position_normalized', 255)->nullable()->after('cv_position');
            $table->index('cv_position_normalized', 'cv_progress_position_normalized_idx');
        });

        foreach (array_chunk($mappings, 200) as $chunk) {
            DB::table('cv_maker_position_skill_categories')->insert($chunk);
        }
    }

    public function down()
    {
        Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->dropIndex('cv_progress_position_normalized_idx');
            $table->dropColumn(['cv_position', 'cv_position_normalized']);
        });

        Schema::dropIfExists('cv_maker_position_skill_categories');
    }

    private function loadPositionSkillCategories(): array
    {
        $sourcePath = database_path('data/cv_maker_position_skill_categories.xlsx');

        if (!is_file($sourcePath)) {
            throw new RuntimeException('Master kategori Skill/Non Skill posisi CV Maker tidak ditemukan.');
        }

        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($sourcePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->rangeToArray('A2:G' . $sheet->getHighestDataRow(), null, true, true, false);
        $headers = array_map([$this, 'normalizeText'], array_shift($rows) ?: []);

        if ($headers !== ['NO', 'AREA', 'DEPARTEMEN', 'DIVISI', 'POSISI', 'MANAGERIAL', 'SKILLED']) {
            throw new RuntimeException('Header master kategori posisi CV Maker tidak sesuai format yang didukung.');
        }

        $now = now();
        $mappings = [];

        foreach ($rows as $index => $row) {
            $positionName = preg_replace('/\s+/u', ' ', trim((string) ($row[4] ?? '')));
            $normalizedPosition = $this->normalizeText($positionName);
            $sourceCategory = $this->normalizeText($row[6] ?? null);

            if ($normalizedPosition === '' && $sourceCategory === '') {
                continue;
            }

            $skillCategory = [
                'SKILLED' => 'skilled',
                'NON SKILLED' => 'non_skilled',
            ][$sourceCategory] ?? null;

            if ($normalizedPosition === '' || $skillCategory === null) {
                throw new RuntimeException('Data posisi/kategori tidak valid pada baris Excel ' . ($index + 3) . '.');
            }

            if (isset($mappings[$normalizedPosition])
                && $mappings[$normalizedPosition]['skill_category'] !== $skillCategory) {
                throw new RuntimeException('Kategori posisi bertentangan untuk: ' . $positionName);
            }

            $mappings[$normalizedPosition] = [
                'position_name' => $positionName,
                'normalized_position' => $normalizedPosition,
                'skill_category' => $skillCategory,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($mappings) !== 478) {
            throw new RuntimeException('Jumlah posisi unik pada master harus 478, ditemukan ' . count($mappings) . '.');
        }

        $spreadsheet->disconnectWorksheets();

        return array_values($mappings);
    }

    private function normalizeText($value): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $value)), 'UTF-8');
    }
}
