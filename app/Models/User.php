<?php

namespace App\Models;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'status',
        'terakhir_login',
        'nik_karyawan',
        'role_id',
        'authorized_divisi_ids',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'terakhir_login' => 'datetime',
        'authorized_divisi_ids' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan', 'nik')->select(['nik', 'nama_karyawan', 'departemen_id', 'divisi_id', 'sisa_cuti', 'posisi', 'face_reference_path']);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new class extends VerifyEmailNotification {

            public function toMail($notifiable)
            {
                return (new MailMessage)
                    ->subject('Verifikasi Email Akun V-People')
                    ->greeting('Halo ' . $notifiable->name . ',')
                    ->line('Terima kasih telah mendaftar di sistem HRIS V-People.')
                    ->line('Silakan klik tombol berikut untuk mengaktifkan akun Anda.')
                    ->action(
                        'Verifikasi Email',
                        $this->verificationUrl($notifiable)
                    )
                    ->line('Jika Anda tidak merasa membuat akun, abaikan email ini.')
                    ->salutation('PT Virtue Dragon Nickel Industry');
            }
        });
    }

    public function getNormalizedRoleNameAttribute(): ?string
    {
        return Role::normalizeRoleName(optional($this->role)->permission_role);
    }

    public function getDisplayRoleNameAttribute(): string
    {
        return $this->normalized_role_name ?? optional($this->role)->permission_role ?? 'Belum Diatur';
    }

    public function hasRole($roles)
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $normalizedRoleName = $this->normalized_role_name;
        $actualRoleName = optional($this->role)->permission_role;

        foreach ($roles as $role) {
            $normalizedExpected = Role::normalizeRoleName($role);

            if ($normalizedRoleName && $normalizedExpected && $normalizedRoleName === $normalizedExpected) {
                return true;
            }

            if ($actualRoleName && strcasecmp($actualRoleName, $role) === 0) {
                return true;
            }
        }

        return false;
    }

    public function resolveMenuPermissions(): array
    {
        if (!$this->role) {
            return [];
        }

        if (is_array($this->role->menu_permissions)) {
            return array_values(array_unique($this->role->menu_permissions));
        }

        return config('access.default_menu_permissions.' . $this->normalized_role_name, []);
    }

    public function hasMenuAccess($menus): bool
    {
        $menus = is_array($menus) ? $menus : [$menus];

        if ($this->hasRole('Super Admin')) {
            return true;
        }

        $allowedMenus = $this->resolveMenuPermissions();

        foreach ($menus as $menu) {
            if (in_array($menu, $allowedMenus, true)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessAllEmployees(): bool
    {
        return $this->hasRole(['Super Admin', 'HR']);
    }

    public function isDepartmentScopedRole(): bool
    {
        return $this->hasRole(['HOD', 'Manager']);
    }

    public function isDivisionScopedRole(): bool
    {
        return $this->hasRole(['Supervisor', 'Admin Divisi']);
    }

    public function isSupervisorRole(): bool
    {
        return $this->hasRole('Supervisor');
    }

    public function isAdminDivisiRole(): bool
    {
        return $this->hasRole('Admin Divisi');
    }

    public function getEmployeeScopeLevel(): string
    {
        if ($this->canAccessAllEmployees()) {
            return 'all';
        }

        if ($this->isDepartmentScopedRole()) {
            return 'department';
        }

        if ($this->isDivisionScopedRole()) {
            return 'division';
        }

        return 'self';
    }

    public function scopedDivisionIds(): array
    {
        if (!$this->isDivisionScopedRole()) {
            return [];
        }

        $divisionIds = [];

        if ($this->isAdminDivisiRole()) {
            $divisionIds = array_merge(
                (array) $this->authorized_divisi_ids,
                [optional($this->employee)->divisi_id]
            );
        } elseif ($this->isSupervisorRole()) {
            $divisionIds = [optional($this->employee)->divisi_id];
        }

        return collect($divisionIds)
            ->filter(fn($id) => filled($id))
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function scopedDepartmentIds(): array
    {
        if ($this->isDepartmentScopedRole()) {
            $departemenId = optional($this->employee)->departemen_id;

            return $departemenId ? [(string) $departemenId] : [];
        }

        if (!$this->isDivisionScopedRole()) {
            return [];
        }

        $divisionIds = $this->scopedDivisionIds();

        if (empty($divisionIds)) {
            return [];
        }

        return Divisi::query()
            ->whereIn('id', $divisionIds)
            ->pluck('departemen_id')
            ->filter()
            ->map(fn($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function applyEmployeeScope(Builder $query, ?string $table = null): Builder
    {
        if ($this->canAccessAllEmployees()) {
            return $query;
        }

        if ($this->isDepartmentScopedRole()) {
            $departemenId = optional($this->employee)->departemen_id;

            return $departemenId
                ? $query->where($this->qualifyScopeColumn($table, 'departemen_id'), $departemenId)
                : $query->whereRaw('1 = 0');
        }

        if ($this->isDivisionScopedRole()) {
            $divisiIds = $this->scopedDivisionIds();

            return !empty($divisiIds)
                ? $query->whereIn($this->qualifyScopeColumn($table, 'divisi_id'), $divisiIds)
                : $query->whereRaw('1 = 0');
        }

        return $this->nik_karyawan
            ? $query->where($this->qualifyScopeColumn($table, 'nik'), $this->nik_karyawan)
            : $query->whereRaw('1 = 0');
    }

    public function applyEmployeeRelationScope(Builder $query, string $relation = 'employee'): Builder
    {
        if ($this->canAccessAllEmployees()) {
            return $query;
        }

        if ($this->isDepartmentScopedRole()) {
            $departemenId = optional($this->employee)->departemen_id;

            return $departemenId
                ? $query->whereHas($relation, fn(Builder $employeeQuery) => $employeeQuery->where('departemen_id', $departemenId))
                : $query->whereRaw('1 = 0');
        }

        if ($this->isDivisionScopedRole()) {
            $divisiIds = $this->scopedDivisionIds();

            return !empty($divisiIds)
                ? $query->whereHas($relation, fn(Builder $employeeQuery) => $employeeQuery->whereIn('divisi_id', $divisiIds))
                : $query->whereRaw('1 = 0');
        }

        return $this->nik_karyawan
            ? $query->whereHas($relation, fn(Builder $employeeQuery) => $employeeQuery->where('nik', $this->nik_karyawan))
            : $query->whereRaw('1 = 0');
    }

    public function preferredHomeRouteName(): string
    {
        if ($this->hasMenuAccess('dashboard_admin')) {
            return 'home';
        }

        if ($this->hasMenuAccess('dashboard_karyawan')) {
            return 'dashboard.karyawan';
        }

        return 'update.akun';
    }

    protected function qualifyScopeColumn(?string $table, string $column): string
    {
        return $table ? "{$table}.{$column}" : $column;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function markLastLogin(): void
    {
        $this->forceFill([
            'terakhir_login' => now(),
        ])->save();
    }
}
