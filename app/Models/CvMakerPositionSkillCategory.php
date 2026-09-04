<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerPositionSkillCategory extends Model
{
    public const SKILLED = 'skilled';
    public const NON_SKILLED = 'non_skilled';

    protected $fillable = [
        'position_name',
        'normalized_position',
        'skill_category',
    ];

    public static function labels(): array
    {
        return [
            self::SKILLED => 'Skill',
            self::NON_SKILLED => 'Non Skill',
        ];
    }

    public static function normalizePosition($position): ?string
    {
        if (!is_scalar($position)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim((string) $position));

        return $normalized === '' ? null : mb_strtoupper($normalized, 'UTF-8');
    }
}
