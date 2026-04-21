<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

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
