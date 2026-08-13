<?php

namespace App\Console\Commands;

use App\Models\JobTitleAlias;
use App\Services\Organization\OrganizationStructureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MapLegacyJobTitles extends Command
{
    protected $signature = 'organization:map-legacy-job-titles
        {--apply : Simpan hasil mapping ke employees.job_title_id}
        {--chunk=500 : Jumlah karyawan per chunk}
        {--all-statuses : Sertakan karyawan nonaktif}';

    protected $description = 'Dry-run atau terapkan mapping jabatan teks lama ke master job_titles melalui alias.';

    public function handle(OrganizationStructureService $service): int
    {
        $chunkSize = max(50, min((int) $this->option('chunk'), 2000));
        $apply = (bool) $this->option('apply');
        $aliasMap = JobTitleAlias::query()->pluck('job_title_id', 'normalized_alias');

        if ($aliasMap->isEmpty()) {
            $this->error('Alias jabatan belum tersedia. Jalankan migration master organisasi terlebih dahulu.');
            return 1;
        }

        $summary = ['checked' => 0, 'matched' => 0, 'unmatched' => 0, 'updated' => 0];
        $unmatchedSamples = collect();

        $query = DB::table('employees')
            ->select([
                'nik',
                DB::raw("TRIM(COALESCE(NULLIF(jabatan, ''), NULLIF(posisi, ''))) as legacy_title"),
            ])
            ->whereNull('job_title_id')
            ->when(!$this->option('all-statuses'), fn($builder) => $builder->where('status_resign', 'AKTIF'));

        $query->orderBy('nik')->chunkById($chunkSize, function ($employees) use (
            $service,
            $aliasMap,
            $apply,
            &$summary,
            &$unmatchedSamples
        ) {
            $matchedByTitle = [];

            foreach ($employees as $employee) {
                $summary['checked']++;
                $legacyTitle = trim((string) $employee->legacy_title);
                $normalized = $legacyTitle !== '' ? $service->normalizeTitle($legacyTitle) : '';
                $jobTitleId = $normalized !== '' ? $aliasMap->get($normalized) : null;

                if (!$jobTitleId) {
                    $summary['unmatched']++;

                    if ($legacyTitle !== '' && $unmatchedSamples->count() < 30) {
                        $unmatchedSamples->push($legacyTitle);
                    }
                    continue;
                }

                $summary['matched']++;
                $matchedByTitle[(int) $jobTitleId][] = (string) $employee->nik;
            }

            if (!$apply) {
                return;
            }

            foreach ($matchedByTitle as $jobTitleId => $employeeNiks) {
                $summary['updated'] += DB::table('employees')
                    ->whereNull('job_title_id')
                    ->whereIn('nik', $employeeNiks)
                    ->update([
                        'job_title_id' => $jobTitleId,
                        'updated_at' => now(),
                    ]);
            }
        }, 'nik', 'nik');

        $this->table(['Metric', 'Jumlah'], [
            ['Mode', $apply ? 'APPLY' : 'DRY-RUN'],
            ['Diperiksa', $summary['checked']],
            ['Cocok dengan alias', $summary['matched']],
            ['Belum terpetakan', $summary['unmatched']],
            ['Diperbarui', $summary['updated']],
        ]);

        if ($unmatchedSamples->isNotEmpty()) {
            $this->warn('Contoh jabatan yang belum terpetakan:');
            $unmatchedSamples->unique()->each(fn($title) => $this->line('- ' . $title));
        }

        if (!$apply) {
            $this->info('Tidak ada data yang diubah. Jalankan kembali dengan --apply setelah hasil dry-run direview.');
        }

        return 0;
    }
}
