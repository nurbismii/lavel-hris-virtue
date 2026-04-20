<?php

namespace App\Services\Karyawan;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EmployeeMediaService
{
    private const TARGET_IMAGE_BYTES = 2097152;
    private const MAX_IMAGE_DIMENSION = 2400;
    private const MIN_IMAGE_DIMENSION = 1000;
    private const MIN_JPEG_QUALITY = 45;

    private const TYPE_CONFIG = [
        'photo' => [
            'column' => 'photo_path',
            'directory' => 'employee-documents/%s/photo',
            'prefix' => 'photo',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'ktp' => [
            'column' => 'ktp_path',
            'directory' => 'employee-documents/%s/ktp',
            'prefix' => 'ktp',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        ],
        'kk' => [
            'column' => 'kk_path',
            'directory' => 'employee-documents/%s/kk',
            'prefix' => 'kk',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        ],
        'sim' => [
            'column' => 'sim_path',
            'directory' => 'employee-documents/%s/sim',
            'prefix' => 'sim',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        ],
        'sio' => [
            'column' => 'sio_path',
            'directory' => 'employee-documents/%s/sio',
            'prefix' => 'sio',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        ],
        'face_reference' => [
            'column' => 'face_reference_path',
            'directory' => 'face-reference/%s',
            'prefix' => 'reference',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
    ];

    public function storeUploadedFile(Employee $employee, UploadedFile $file, string $type, bool $allowCompression = true, ?bool &$replacedExisting = null): string
    {
        $config = $this->getTypeConfig($type);
        $relativeDirectory = sprintf($config['directory'], $employee->nik);
        $absoluteDirectory = public_path($relativeDirectory);

        if (!File::exists($absoluteDirectory)) {
            File::makeDirectory($absoluteDirectory, 0755, true);
        }

        $currentPath = $employee->{$config['column']} ?? null;
        $replacedExisting = !empty($currentPath);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');

        if ($this->shouldCompressImage($file, $allowCompression)) {
            $extension = 'jpg';
        }

        $filename = $config['prefix'] . '_' . now()->format('YmdHis') . '_' . Str::lower(Str::random(6)) . '.' . $extension;

        $newRelativePath = $relativeDirectory . '/' . $filename;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $filename;

        if (!$this->storeImageWithCompressionIfNeeded($file, $absolutePath, $allowCompression)) {
            $file->move($absoluteDirectory, $filename);
        }

        if (!empty($currentPath) && $currentPath !== $newRelativePath) {
            $this->deleteRelativePath($currentPath);
        }

        return $newRelativePath;
    }

    public function getColumnForType(string $type): string
    {
        return $this->getTypeConfig($type)['column'];
    }

    public function getAllowedExtensions(string $type): array
    {
        return $this->getTypeConfig($type)['allowed_extensions'];
    }

    public function resolveNikFromFilename(string $originalName, array $knownNiks = []): ?string
    {
        $candidates = $this->extractNikCandidatesFromFilename($originalName);

        if (empty($candidates)) {
            return null;
        }

        if (empty($knownNiks)) {
            return $candidates[0] ?? null;
        }

        $normalizedNiks = [];

        foreach ($knownNiks as $nik) {
            $normalizedNiks[strtolower((string) $nik)] = (string) $nik;
        }

        foreach ($candidates as $candidate) {
            $normalizedCandidate = strtolower((string) $candidate);

            if (isset($normalizedNiks[$normalizedCandidate])) {
                return $normalizedNiks[$normalizedCandidate];
            }
        }

        return null;
    }

    public function extractNikCandidatesFromFilename(string $originalName): array
    {
        $basename = strtolower(trim(pathinfo($originalName, PATHINFO_FILENAME)));

        if ($basename === '') {
            return [];
        }

        $tokens = preg_split('/[^a-zA-Z0-9]+/', $basename, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect(array_merge([$basename], $tokens))
            ->map(fn($token) => strtolower(trim((string) $token)))
            ->filter(fn($token) => $token !== '')
            ->unique()
            ->sortByDesc(fn($token) => strlen($token))
            ->values()
            ->all();
    }

    public function deleteRelativePath(?string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }

        $absolutePath = public_path($relativePath);

        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }
    }

    private function getTypeConfig(string $type): array
    {
        if (!isset(self::TYPE_CONFIG[$type])) {
            throw new InvalidArgumentException("Unsupported employee media type [{$type}].");
        }

        return self::TYPE_CONFIG[$type];
    }

    private function shouldCompressImage(UploadedFile $file, bool $allowCompression): bool
    {
        return $allowCompression
            && (bool) config('app.employee_media_server_side_compression', false)
            && $this->isImageFile($file)
            && $file->getSize() > self::TARGET_IMAGE_BYTES
            && extension_loaded('gd');
    }

    private function isImageFile(UploadedFile $file): bool
    {
        $mimeType = strtolower((string) $file->getMimeType());

        return Str::startsWith($mimeType, 'image/');
    }

    private function storeImageWithCompressionIfNeeded(UploadedFile $file, string $destinationPath, bool $allowCompression): bool
    {
        if (!$this->shouldCompressImage($file, $allowCompression)) {
            return false;
        }

        $binary = @file_get_contents($file->getRealPath());

        if ($binary === false) {
            return false;
        }

        $sourceImage = @imagecreatefromstring($binary);

        if (!$sourceImage) {
            return false;
        }

        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $dimensions = $this->scaleDimensions($width, $height, self::MAX_IMAGE_DIMENSION);
        $quality = 90;
        $encodedImage = null;

        try {
            while (true) {
                $canvas = imagecreatetruecolor($dimensions['width'], $dimensions['height']);

                if (!$canvas) {
                    break;
                }

                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $white);
                imagecopyresampled(
                    $canvas,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $dimensions['width'],
                    $dimensions['height'],
                    $width,
                    $height
                );

                $encodedImage = $this->encodeJpegToString($canvas, $quality);
                imagedestroy($canvas);

                if ($encodedImage === null) {
                    break;
                }

                if (strlen($encodedImage) <= self::TARGET_IMAGE_BYTES) {
                    break;
                }

                if ($quality > self::MIN_JPEG_QUALITY) {
                    $quality = max(self::MIN_JPEG_QUALITY, $quality - 8);
                    continue;
                }

                if (max($dimensions['width'], $dimensions['height']) <= self::MIN_IMAGE_DIMENSION) {
                    break;
                }

                $dimensions = [
                    'width' => max(1, (int) round($dimensions['width'] * 0.9)),
                    'height' => max(1, (int) round($dimensions['height'] * 0.9)),
                ];
                $quality = 82;
            }
        } finally {
            imagedestroy($sourceImage);
        }

        if ($encodedImage === null) {
            return false;
        }

        File::put($destinationPath, $encodedImage);

        return true;
    }

    private function scaleDimensions(int $width, int $height, int $maxDimension): array
    {
        if (max($width, $height) <= $maxDimension) {
            return compact('width', 'height');
        }

        $ratio = $maxDimension / max($width, $height);

        return [
            'width' => max(1, (int) round($width * $ratio)),
            'height' => max(1, (int) round($height * $ratio)),
        ];
    }

    private function encodeJpegToString($image, int $quality): ?string
    {
        ob_start();
        $success = imagejpeg($image, null, $quality);
        $data = ob_get_clean();

        if (!$success || $data === false) {
            return null;
        }

        return $data;
    }
}
