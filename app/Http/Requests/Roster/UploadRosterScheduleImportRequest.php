<?php

namespace App\Http\Requests\Roster;

use Illuminate\Foundation\Http\FormRequest;

final class UploadRosterScheduleImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasRole(['Super Admin', 'HR'])
            && $user->hasMenuAccess('roster_schedule');
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                function (string $attribute, $value, \Closure $fail): void {
                    $archive = new \ZipArchive();
                    $opened = $archive->open($value->getRealPath());
                    if ($opened !== true) {
                        $fail('File harus merupakan workbook XLSX yang valid.');

                        return;
                    }

                    if ($archive->locateName('[Content_Types].xml') === false) {
                        $archive->close();
                        $fail('File harus merupakan workbook XLSX yang valid.');

                        return;
                    }

                    $archive->close();
                },
                'max:' . (int) config('roster.import.max_kb', 10240),
            ],
        ];
    }
}
