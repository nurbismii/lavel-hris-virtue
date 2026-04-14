<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $table = 'roles';

    protected $guarded = [];

    protected $casts = [
        'menu_permissions' => 'array',
    ];

    public function user()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    public static function normalizeRoleName(?string $roleName): ?string
    {
        if (!$roleName) {
            return null;
        }

        foreach (config('access.roles', []) as $canonical => $meta) {
            $aliases = array_merge([$canonical], $meta['aliases'] ?? []);

            foreach ($aliases as $alias) {
                if (Str::lower($alias) === Str::lower($roleName)) {
                    return $canonical;
                }
            }
        }

        return $roleName;
    }

    public function getNormalizedNameAttribute(): ?string
    {
        return static::normalizeRoleName($this->permission_role);
    }

    public function getResolvedMenuPermissionsAttribute(): array
    {
        if (is_array($this->menu_permissions)) {
            return array_values(array_unique($this->menu_permissions));
        }

        return config('access.default_menu_permissions.' . $this->normalized_name, []);
    }

    public function getScopeLabelAttribute(): string
    {
        return config('access.roles.' . $this->normalized_name . '.scope_label', 'Kustom');
    }
}
