<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class AddManagerialCategoryToCvPositionMaster extends Migration
{
    public function up()
    {
        $mappings = $this->loadPositionCategories();

        Schema::table('cv_maker_position_skill_categories', function (Blueprint $table) {
            $table->string('managerial_category', 20)->nullable()->after('skill_category');
            $table->index(
                ['managerial_category', 'normalized_position'],
                'cv_position_managerial_category_idx'
            );
        });

        foreach (array_chunk($mappings, 200) as $chunk) {
            DB::table('cv_maker_position_skill_categories')->upsert(
                $chunk,
                ['normalized_position'],
                ['managerial_category', 'updated_at']
            );
        }
    }

    public function down()
    {
        Schema::table('cv_maker_position_skill_categories', function (Blueprint $table) {
            $table->dropIndex('cv_position_managerial_category_idx');
            $table->dropColumn('managerial_category');
        });
    }

    private function loadPositionCategories(): array
    {
        $sourcePath = database_path('data/cv_maker_position_skill_categories.xlsx');

        if (!is_file($sourcePath)) {
            throw new RuntimeException('Master kategori posisi CV Maker tidak ditemukan.');
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
            $sourceManagerial = $this->normalizeText($row[5] ?? null);
            $sourceSkill = $this->normalizeText($row[6] ?? null);
            $managerialCategory = [
                'MANAGERIAL' => 'managerial',
                'NON MANAGERIAL' => 'non_managerial',
            ][$sourceManagerial] ?? null;
            $skillCategory = [
                'SKILLED' => 'skilled',
                'NON SKILLED' => 'non_skilled',
            ][$sourceSkill] ?? null;

            if ($normalizedPosition === '' && $sourceManagerial === '' && $sourceSkill === '') {
                continue;
            }

            if ($normalizedPosition === '' || $managerialCategory === null || $skillCategory === null) {
                throw new RuntimeException('Data kategori posisi tidak valid pada baris Excel ' . ($index + 3) . '.');
            }

            if (isset($mappings[$normalizedPosition])
                && ($mappings[$normalizedPosition]['managerial_category'] !== $managerialCategory
                    || $mappings[$normalizedPosition]['skill_category'] !== $skillCategory)) {
                throw new RuntimeException('Kategori posisi bertentangan untuk: ' . $positionName);
            }

            $mappings[$normalizedPosition] = [
                'position_name' => $positionName,
                'normalized_position' => $normalizedPosition,
                'skill_category' => $skillCategory,
                'managerial_category' => $managerialCategory,
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
