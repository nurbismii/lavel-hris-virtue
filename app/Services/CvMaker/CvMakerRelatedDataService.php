<?php

namespace App\Services\CvMaker;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CvMakerRelatedDataService
{
    public const SOURCE = 'cv_maker';

    private const SECTIONS = [
        'educations' => [
            'label' => 'Riwayat pendidikan',
            'table' => 'employee_cv_educations',
            'fields' => ['level', 'institution', 'major', 'graduation_year', 'sort_order'],
            'summary' => ['level', 'institution'],
        ],
        'experiences' => [
            'label' => 'Pengalaman kerja',
            'table' => 'employee_cv_experiences',
            'fields' => ['position', 'company', 'department', 'division', 'start_month', 'end_month', 'is_current', 'responsibilities', 'sort_order'],
            'summary' => ['position', 'company'],
        ],
        'organizations' => [
            'label' => 'Organisasi',
            'table' => 'employee_cv_organizations',
            'fields' => ['organization_name', 'role', 'start_year', 'end_year', 'sort_order'],
            'summary' => ['organization_name', 'role'],
        ],
        'certifications' => [
            'label' => 'Sertifikasi & pelatihan',
            'table' => 'employee_cv_certifications',
            'fields' => ['name', 'issuer', 'year', 'valid_until_year', 'is_lifetime', 'type', 'sort_order'],
            'summary' => ['name', 'issuer'],
        ],
        'languages' => [
            'label' => 'Kemampuan bahasa',
            'table' => 'employee_cv_languages',
            'fields' => ['language', 'level', 'sort_order'],
            'summary' => ['language', 'level'],
        ],
        'projects' => [
            'label' => 'Proyek',
            'table' => 'employee_cv_projects',
            'fields' => ['name', 'year', 'sort_order'],
            'summary' => ['name', 'year'],
        ],
    ];

    public function preview(string $nik, int $profileId, array $sourceSections): array
    {
        $changes = [];
        $skipped = [];
        $comparison = [];

        foreach (self::SECTIONS as $key => $definition) {
            if (!$this->tableExists($definition['table'])) {
                $skipped[] = ['label' => $definition['label'], 'reason' => 'Tabel tujuan belum tersedia. Jalankan migration V-People.'];
                continue;
            }

            $sourceRows = $this->normalizeRows((array) ($sourceSections[$key] ?? []), $definition);
            $currentRows = DB::table($definition['table'])
                ->where('employee_nik', $nik)
                ->where('source', self::SOURCE)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get($definition['fields'])
                ->map(fn($row) => (array) $row)
                ->all();
            $currentRows = $this->normalizeRows($currentRows, $definition, false);
            $isDifferent = $this->fingerprint($currentRows) !== $this->fingerprint($sourceRows);

            $comparison[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'hris_count' => count($currentRows),
                'cv_count' => count($sourceRows),
                'hris' => $this->summarize($currentRows, $definition),
                'cv' => $this->summarize($sourceRows, $definition),
                'skipped' => empty($sourceRows),
                'mismatch' => !empty($sourceRows) && $isDifferent,
            ];

            if (empty($sourceRows)) {
                if (!empty($currentRows)) {
                    $skipped[] = [
                        'label' => $definition['label'],
                        'reason' => 'Data Vitae kosong; data V-People tidak dihapus otomatis.',
                    ];
                }
                continue;
            }

            if ($isDifferent) {
                $changes[] = [
                    'key' => $key,
                    'label' => $definition['label'],
                    'old' => $this->summarize($currentRows, $definition),
                    'new' => $this->summarize($sourceRows, $definition),
                    'old_count' => count($currentRows),
                    'new_count' => count($sourceRows),
                    'rows' => $sourceRows,
                    'profile_id' => $profileId,
                ];
            }
        }

        return compact('changes', 'skipped', 'comparison');
    }

    public function sync(string $nik, array $changes): void
    {
        $now = Carbon::now();

        foreach ($changes as $change) {
            $definition = self::SECTIONS[$change['key']] ?? null;

            if (!$definition || !$this->tableExists($definition['table'])) {
                continue;
            }

            DB::table($definition['table'])
                ->where('employee_nik', $nik)
                ->where('source', self::SOURCE)
                ->delete();

            $inserts = collect($change['rows'] ?? [])->map(function (array $row) use ($nik, $change, $now) {
                return array_merge($row, [
                    'employee_nik' => $nik,
                    'source' => self::SOURCE,
                    'cv_profile_id' => $change['profile_id'] ?? null,
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            })->all();

            if ($inserts) {
                DB::table($definition['table'])->insert($inserts);
            }
        }
    }

    private function normalizeRows(array $rows, array $definition, bool $includeSourceMetadata = true): array
    {
        return collect($rows)->map(function ($row, $index) use ($definition, $includeSourceMetadata) {
            $row = (array) $row;
            $normalized = [];

            foreach ($definition['fields'] as $field) {
                $value = $row[$field] ?? null;

                if (in_array($field, ['is_current', 'is_lifetime'], true)) {
                    $value = (bool) $value;
                } elseif ($field === 'sort_order') {
                    $value = is_numeric($value) ? (int) $value : $index;
                } elseif (is_string($value)) {
                    $value = trim($value) !== '' ? trim($value) : null;
                }

                $normalized[$field] = $value;
            }

            if ($includeSourceMetadata) {
                $normalized['source_record_id'] = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : null;
                $normalized['source_updated_at'] = $row['updated_at'] ?? null;
            }

            return $normalized;
        })->values()->all();
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function fingerprint(array $rows): string
    {
        $comparable = collect($rows)->map(function (array $row) {
            unset($row['source_record_id'], $row['source_updated_at']);

            return $row;
        })->all();

        return hash('sha256', json_encode($comparable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function summarize(array $rows, array $definition): string
    {
        if (!$rows) {
            return '0 data';
        }

        $examples = collect($rows)->take(3)->map(function (array $row) use ($definition) {
            return collect($definition['summary'])
                ->map(fn($field) => $row[$field] ?? null)
                ->filter(fn($value) => $value !== null && $value !== '')
                ->implode(' — ');
        })->filter()->implode('; ');

        return count($rows) . ' data' . ($examples !== '' ? ': ' . $examples : '');
    }
}
