<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractClause extends Model
{
    public const KEY_CLAUSE_1 = 'klausul_1';
    public const KEY_CLAUSE_2 = 'klausul_2';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function keyOptions(): array
    {
        return [
            self::KEY_CLAUSE_1 => 'Klausul 1',
            self::KEY_CLAUSE_2 => 'Klausul 2',
        ];
    }

    public function getKeyLabelAttribute(): string
    {
        return self::keyOptions()[$this->clause_key] ?? $this->clause_key;
    }
}
