<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Departemen;
use App\Models\Divisi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingRoleController extends Controller
{
    public function index()
    {
        $users = User::with('employee', 'role', 'additionalRoles')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.setting-role.index', compact('users'));
    }

    public function create()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $roles = Role::orderBy('permission_role')->get();
        $menuGroups = $this->localizedMenuGroups();
        $rolePresets = $this->localizedRolePresets();

        return view('admin.setting-role.create', compact('roles', 'menuGroups', 'rolePresets'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'permission_role' => 'required|string|max:64|unique:roles,permission_role',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
            'menu_permissions' => 'nullable|array',
            'menu_permissions.*' => ['string', Rule::in(array_keys(config('access.menus', [])))],
        ]);

        $normalizedRoleName = Role::normalizeRoleName($validated['permission_role']);
        $menuPermissions = $normalizedRoleName === 'Super Admin'
            ? array_keys(config('access.menus', []))
            : ($validated['menu_permissions'] ?? []);

        Role::create([
            'permission_role' => $validated['permission_role'],
            'description' => $validated['description'] ?: $this->defaultRoleDescription($normalizedRoleName),
            'menu_permissions' => $menuPermissions,
            'status' => $validated['status'],
        ]);

        toast()->success('success', 'Role berhasil ditambahkan.');
        return back();
    }

    public function edit($id)
    {
        $user = User::with(['role', 'additionalRoles', 'employee.divisi.departemen'])->findOrFail($id);
        $roles = Role::where('status', '1')->get();
        $allowedCompanyCodes = ['VDNI', 'VDNIP'];
        $departemens = Departemen::with([
            'perusahaan',
            'divisi' => fn($query) => $query->orderBy('nama_divisi'),
        ])
            ->whereHas('perusahaan', function ($query) use ($allowedCompanyCodes) {
                $query->whereIn('kode_perusahaan', $allowedCompanyCodes)
                    ->orWhereIn('nama_perusahaan', $allowedCompanyCodes);
            })
            ->orderBy('departemen')
            ->get();
        $menuGroups = $this->localizedMenuGroups();
        $roleAccessMap = $roles->mapWithKeys(function ($role) {
            return [
                $role->id => [
                    'name' => $role->permission_role,
                    'normalized_name' => $role->normalized_name,
                    'scope_label' => $role->scope_label,
                    'menus' => $role->resolved_menu_permissions,
                ],
            ];
        });
        $assignedDivisis = Divisi::query()
            ->whereIn('id', $user->scopedDivisionIds())
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);
        $assignedDepartemens = Departemen::query()
            ->whereIn('id', $user->scopedDepartmentIds())
            ->orderBy('departemen')
            ->get(['id', 'departemen']);
        $selectedAdditionalRoleIds = $user->additionalRoles
            ->pluck('id')
            ->map(fn($roleId) => (string) $roleId)
            ->all();

        return view('admin.setting-role.edit', compact(
            'user',
            'roles',
            'departemens',
            'menuGroups',
            'roleAccessMap',
            'assignedDivisis',
            'assignedDepartemens',
            'selectedAdditionalRoleIds'
        ));
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'permission_role' => ['required', 'string', 'max:64', Rule::unique('roles', 'permission_role')->ignore($role->id)],
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
            'menu_permissions' => 'nullable|array',
            'menu_permissions.*' => ['string', Rule::in(array_keys(config('access.menus', [])))],
        ]);

        $normalizedRoleName = Role::normalizeRoleName($validated['permission_role']);
        $menuPermissions = $normalizedRoleName === 'Super Admin'
            ? array_keys(config('access.menus', []))
            : ($validated['menu_permissions'] ?? []);

        $role->update([
            'permission_role' => $validated['permission_role'],
            'description' => $validated['description'] ?: $this->defaultRoleDescription($normalizedRoleName),
            'menu_permissions' => $menuPermissions,
            'status' => $validated['status'],
        ]);

        toast()->success('Success', 'Role berhasil diperbarui');
        return back();
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'additional_role_ids' => 'nullable|array',
            'additional_role_ids.*' => 'integer|exists:roles,id',
            'authorized_departemen_ids' => 'nullable|array',
            'authorized_departemen_ids.*' => 'integer|exists:departemens,id',
            'authorized_divisi_ids' => 'nullable|array',
            'authorized_divisi_ids.*' => 'integer|exists:divisis,id',
        ]);

        $user = User::findOrFail($id);
        $role = Role::findOrFail($validated['role_id']);
        $normalizedRoleName = Role::normalizeRoleName($role->permission_role);
        $authorizedDepartemenIds = $normalizedRoleName === 'HOD'
            ? collect($validated['authorized_departemen_ids'] ?? [])->filter()->map(fn($id) => (int) $id)->unique()->values()->all()
            : null;
        $authorizedDivisiIds = in_array($normalizedRoleName, ['HOD', 'Admin Divisi'], true)
            ? collect($validated['authorized_divisi_ids'] ?? [])->filter()->map(fn($id) => (int) $id)->unique()->values()->all()
            : null;

        $user->role_id = $role->id;
        $user->authorized_departemen_ids = $authorizedDepartemenIds;
        $user->authorized_divisi_ids = $authorizedDivisiIds;
        $user->save();
        $user->additionalRoles()->sync(
            collect($validated['additional_role_ids'] ?? [])
                ->filter(fn($roleId) => (int) $roleId !== (int) $role->id)
                ->map(fn($roleId) => (int) $roleId)
                ->unique()
                ->values()
                ->all()
        );

        toast()->success('success', 'Role berhasil diperbarui.');
        return redirect()->route('setting-role.index');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        User::where('role_id', $role->id)->update([
            'role_id' => NULL
        ]);

        $role->delete();

        toast()->success('success', 'Role berhasil dihapus.');
        return back();
    }

    private function localizedMenuGroups()
    {
        return collect(config('access.menus', []))
            ->map(function ($menu, $key) {
                $group = $menu['group'] ?? 'Dashboard';

                return array_merge($menu, [
                    'key' => $key,
                    'label' => $this->transOrFallback('access.menus.' . $key . '.label', $menu['label'] ?? $key),
                    'group' => $group,
                    'group_label' => $this->transOrFallback('access.menu_groups.' . $this->menuGroupKey($group), $group),
                    'group_style' => $this->menuGroupStyle($group),
                ]);
            })
            ->groupBy('group');
    }

    private function localizedRolePresets(): array
    {
        return collect(config('access.roles', []))
            ->map(function ($meta, $roleName) {
                $translationKey = Role::accessTranslationKey($roleName);

                return array_merge($meta, [
                    'scope_label' => $this->transOrFallback(
                        'access.roles.' . $translationKey . '.scope_label',
                        $meta['scope_label'] ?? 'Kustom'
                    ),
                    'description' => $this->transOrFallback(
                        'access.roles.' . $translationKey . '.description',
                        $meta['description'] ?? null
                    ),
                ]);
            })
            ->all();
    }

    private function defaultRoleDescription(?string $roleName): ?string
    {
        return config('access.roles.' . $roleName . '.description');
    }

    private function transOrFallback(string $key, ?string $fallback): ?string
    {
        $translated = __($key);

        return $translated === $key ? $fallback : $translated;
    }

    private function menuGroupKey(string $group): string
    {
        return [
            'Dashboard' => 'dashboard',
            'Data Master' => 'master_data',
            'Self Service' => 'self_service',
            'Approval' => 'approval',
            'Operasional' => 'operations',
            'Admin Panel' => 'admin_panel',
        ][$group] ?? str_replace([' ', '-'], '_', strtolower($group));
    }

    private function menuGroupStyle(string $group): string
    {
        return [
            'Dashboard' => 'dashboard',
            'Data Master' => 'data-master',
            'Self Service' => 'self-service',
            'Approval' => 'approval',
            'Operasional' => 'operasional',
            'Admin Panel' => 'admin-panel',
        ][$group] ?? 'dashboard';
    }
}
