<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use App\Support\ZipArchiveStatus;
use ZipArchive;

trait ValidatesZipUploads
{
    protected function validateZipUploads(Request $request, array $fields): array
    {
        $rules = [];
        $messages = [];

        foreach ($fields as $field => $config) {
            $label = $config['label'] ?? $field;
            $required = (bool) ($config['required'] ?? false);
            $maxKilobytes = (int) ($config['max'] ?? 512000);

            $rules[$field] = array_filter([
                $required ? 'required' : 'nullable',
                'file',
                'max:' . $maxKilobytes,
                function (string $attribute, $value, $fail) use ($label) {
                    if (!$value instanceof UploadedFile) {
                        return;
                    }

                    $originalExtension = strtolower((string) $value->getClientOriginalExtension());
                    $detectedExtension = strtolower((string) ($value->extension() ?: $originalExtension));

                    if (!in_array($originalExtension, ['zip'], true) && !in_array($detectedExtension, ['zip'], true)) {
                        $fail("{$label} harus berformat .zip.");
                        return;
                    }

                    $temporaryPath = $value->getRealPath();

                    if (!$temporaryPath || !is_file($temporaryPath)) {
                        $fail("{$label} tidak terbaca dengan benar.");
                        return;
                    }

                    $zip = new ZipArchive();
                    $zipStatus = $zip->open($temporaryPath);

                    if ($zipStatus !== true) {
                        $fail(ZipArchiveStatus::message($zipStatus, $label));
                        return;
                    }

                    $hasUsableEntry = false;

                    for ($index = 0; $index < $zip->numFiles; $index++) {
                        $entryName = $zip->getNameIndex($index);

                        if (!$entryName) {
                            continue;
                        }

                        $normalizedEntry = str_replace('\\', '/', $entryName);

                        if (substr($normalizedEntry, -1) === '/') {
                            continue;
                        }

                        $basename = pathinfo($normalizedEntry, PATHINFO_BASENAME);

                        if ($basename === '' || strpos($basename, '.') === 0) {
                            continue;
                        }

                        $hasUsableEntry = true;
                        break;
                    }

                    $zip->close();

                    if (!$hasUsableEntry) {
                        $fail("{$label} kosong atau tidak memiliki file yang bisa diproses.");
                    }
                },
            ]);

            $messages["{$field}.required"] = "Pilih {$label} terlebih dahulu.";
            $messages["{$field}.file"] = "{$label} tidak terbaca dengan benar.";
            $messages["{$field}.max"] = "Ukuran {$label} melebihi batas " . (int) round($maxKilobytes / 1024) . "MB.";
        }

        return Validator::make($request->all(), $rules, $messages)->validate();
    }
}
