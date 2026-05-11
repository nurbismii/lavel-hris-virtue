<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrateSensitiveFilesToPrivateStorage extends Command
{
    private const ALLOWED_ROOTS = [
        'employee-documents',
        'face-reference',
        'cuti-roster',
    ];

    protected $signature = 'sensitive-files:migrate-private
        {--prefix=* : Restrict migration to one or more allowed public prefixes}
        {--limit=0 : Maximum files to process; 0 means all}
        {--dry-run : Only report files that would be copied}
        {--delete-public : Delete public copy after private copy is verified}';

    protected $description = 'Copy legacy sensitive public files into storage/app/private with a safe transition path.';

    public function handle(): int
    {
        $prefixes = $this->resolvePrefixes((array) $this->option('prefix'));
        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $deletePublic = (bool) $this->option('delete-public');

        $processed = 0;
        $copied = 0;
        $skipped = 0;
        $deleted = 0;
        $failed = 0;

        foreach ($prefixes as $prefix) {
            $sourceDirectory = public_path($prefix);

            if (!File::isDirectory($sourceDirectory)) {
                $this->warn("Skipped missing public directory: {$prefix}");
                continue;
            }

            foreach (File::allFiles($sourceDirectory) as $file) {
                if ($limit > 0 && $processed >= $limit) {
                    break 2;
                }

                $processed++;

                $relativePath = $prefix . '/' . str_replace('\\', '/', $file->getRelativePathname());
                $sourcePath = $file->getPathname();
                $targetPath = storage_path('app/private/' . $relativePath);

                if ($dryRun) {
                    $this->line("Would copy: {$relativePath}");
                    continue;
                }

                File::ensureDirectoryExists(dirname($targetPath), 0755, true);

                if (!File::isFile($targetPath)) {
                    if (!File::copy($sourcePath, $targetPath)) {
                        $failed++;
                        $this->error("Failed to copy: {$relativePath}");
                        continue;
                    }

                    $copied++;
                } else {
                    $skipped++;
                }

                if ($deletePublic) {
                    if ($this->filesMatch($sourcePath, $targetPath)) {
                        File::delete($sourcePath);
                        $deleted++;
                    } else {
                        $failed++;
                        $this->error("Public file not deleted because copied file differs: {$relativePath}");
                    }
                }
            }
        }

        $this->info(sprintf(
            'Processed: %d, copied: %d, skipped: %d, deleted_public: %d, failed: %d',
            $processed,
            $copied,
            $skipped,
            $deleted,
            $failed
        ));

        return $failed > 0 ? 1 : 0;
    }

    private function resolvePrefixes(array $requestedPrefixes): array
    {
        $requestedPrefixes = collect($requestedPrefixes)
            ->map(fn($prefix) => trim(str_replace('\\', '/', (string) $prefix), '/'))
            ->filter()
            ->values();

        if ($requestedPrefixes->isEmpty()) {
            return self::ALLOWED_ROOTS;
        }

        return $requestedPrefixes
            ->filter(fn($prefix) => $this->isAllowedPrefix($prefix))
            ->unique()
            ->values()
            ->all();
    }

    private function isAllowedPrefix(string $prefix): bool
    {
        if ($prefix === '' || Str::contains($prefix, '..')) {
            return false;
        }

        foreach (self::ALLOWED_ROOTS as $root) {
            if ($prefix === $root || Str::startsWith($prefix, $root . '/')) {
                return true;
            }
        }

        $this->warn("Ignored unsupported prefix: {$prefix}");

        return false;
    }

    private function filesMatch(string $sourcePath, string $targetPath): bool
    {
        return File::isFile($sourcePath)
            && File::isFile($targetPath)
            && File::size($sourcePath) === File::size($targetPath)
            && hash_file('sha256', $sourcePath) === hash_file('sha256', $targetPath);
    }
}
