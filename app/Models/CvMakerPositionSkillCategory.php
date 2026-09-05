<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerPositionSkillCategory extends Model
{
    public const SKILLED = 'skilled';
    public const NON_SKILLED = 'non_skilled';
    public const MANAGERIAL = 'managerial';
    public const NON_MANAGERIAL = 'non_managerial';

    protected $fillable = [
        'position_name',
        'normalized_position',
        'skill_category',
        'managerial_category',
    ];

    public static function labels(): array
    {
        return [
            self::SKILLED => 'Skill',
            self::NON_SKILLED => 'Non Skill',
        ];
    }

    public static function managerialLabels(): array
    {
        return [
            self::MANAGERIAL => 'Managerial',
            self::NON_MANAGERIAL => 'Non Managerial',
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
