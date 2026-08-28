<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SensitiveFileStorageService
{
    private const PRIVATE_ROOT = 'private';

    public function storeUploadedFileAs(UploadedFile $file, string $relativeDirectory, string $filename): string
    {
        $directory = $this->normalizePath($relativeDirectory);
        $safeFilename = basename($filename);

        if ($directory === '' || $safeFilename === '') {
            throw new InvalidArgumentException('Invalid private file destination.');
        }

        Storage::disk('local')->putFileAs(self::PRIVATE_ROOT . '/' . $directory, $file, $safeFilename);

        return $directory . '/' . $safeFilename;
    }

    public function ensurePrivateDirectory(string $relativeDirectory): string
    {
        $directory = $this->normalizePath($relativeDirectory);

        if ($directory === '') {
            throw new InvalidArgumentException('Invalid private directory.');
        }

        $absoluteDirectory = storage_path('app/' . self::PRIVATE_ROOT . '/' . $directory);

        if (!File::exists($absoluteDirectory)) {
            File::makeDirectory($absoluteDirectory, 0755, true);
        }

        return $absoluteDirectory;
    }

    public function resolvePath(string $relativePath, array $allowedPrefixes): ?string
    {
        $normalizedPath = $this->normalizePath($relativePath);

        if (!$this->isAllowedPath($normalizedPath, $allowedPrefixes)) {
            return null;
        }

        $privatePath = storage_path('app/' . self::PRIVATE_ROOT . '/' . $normalizedPath);

        if (File::isFile($privatePath)) {
            return $privatePath;
        }

        $legacyPublicPath = public_path($normalizedPath);

        return File::isFile($legacyPublicPath) ? $legacyPublicPath : null;
    }

    public function resolvePrivatePath(string $relativePath, array $allowedPrefixes): ?string
    {
        $normalizedPath = $this->normalizePath($relativePath);

        if (!$this->isAllowedPath($normalizedPath, $allowedPrefixes)) {
            return null;
        }

        $diskPath = self::PRIVATE_ROOT . '/' . $normalizedPath;

        return Storage::disk('local')->exists($diskPath)
            ? Storage::disk('local')->path($diskPath)
            : null;
    }

    public function delete(string $relativePath, array $allowedPrefixes): void
    {
        $normalizedPath = $this->normalizePath($relativePath);

        if (!$this->isAllowedPath($normalizedPath, $allowedPrefixes)) {
            return;
        }

        foreach ([
            storage_path('app/' . self::PRIVATE_ROOT . '/' . $normalizedPath),
            public_path($normalizedPath),
        ] as $absolutePath) {
            if (File::isFile($absolutePath)) {
                File::delete($absolutePath);
            }
        }
    }

    public function deletePrivate(string $relativePath, array $allowedPrefixes): void
    {
        $normalizedPath = $this->normalizePath($relativePath);

        if (!$this->isAllowedPath($normalizedPath, $allowedPrefixes)) {
            return;
        }

        Storage::disk('local')->delete(self::PRIVATE_ROOT . '/' . $normalizedPath);
    }

    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', trim(ltrim($path, '/')));
    }

    private function isAllowedPath(string $path, array $allowedPrefixes): bool
    {
        if ($path === '' || Str::contains($path, '..')) {
            return false;
        }

        foreach ($allowedPrefixes as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
