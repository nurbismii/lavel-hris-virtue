<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportHistoryItem extends Model
{
    public const CATEGORY_FAILED = 'failed';
    public const CATEGORY_SKIPPED = 'skipped';
    public const CATEGORY_UPDATED = 'updated';

    protected $guarded = [];

    protected $casts = [
        'row_number' => 'integer',
        'payload' => 'array',
    ];

    public function importHistory()
    {
        return $this->belongsTo(ImportHistory::class);
    }

    public static function categoryLabels(): array
    {
        return [
            self::CATEGORY_FAILED => 'Gagal',
            self::CATEGORY_SKIPPED => 'Dilewati',
            self::CATEGORY_UPDATED => 'Update',
        ];
    }
}
