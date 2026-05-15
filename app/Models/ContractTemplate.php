<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    public const TYPE_PKWT_1 = 'pkwt_1';
    public const TYPE_TRANSLATOR = 'translator';
    public const TYPE_ADDENDUM_PKWT = 'addendum_pkwt';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_PKWT_1 => 'Kontrak PKWT 1',
            self::TYPE_TRANSLATOR => 'Kontrak Translator',
            self::TYPE_ADDENDUM_PKWT => 'Adendum PKWT',
        ];
    }

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function assets()
    {
        return $this->hasMany(ContractTemplateAsset::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->contract_type] ?? $this->contract_type;
    }
}
